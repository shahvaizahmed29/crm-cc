<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\LeadCard;
use App\Models\Status;
use App\Models\User;
use App\Services\LeadTxtExportParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DealSheetController extends Controller
{
    private const STATUS_SLUG = 'deal-sheet-uploaded';
    private const PREVIEW_CACHE_TTL_MINUTES = 30;

    public function index(): View
    {
        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        $newStatusId = Status::where('slug', 'new')->value('id');
        $previewToken = trim((string) request()->query('preview', ''));
        $preview = null;
        if ($previewToken !== '') {
            $cached = Cache::get($this->previewCacheKey((int) auth()->id(), $previewToken));
            if (is_array($cached) && (($cached['created_by'] ?? null) === (int) auth()->id())) {
                $preview = $cached;
            }
        }

        $leads = Lead::query()
            ->with(['status', 'assignedTo', 'phones'])
            ->when($statusId, fn ($q) => $q->where('status_id', $statusId))
            ->when(! $statusId, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $unassignedCount = Lead::query()
            ->when($statusId, fn ($q) => $q->where('status_id', $statusId))
            ->when(! $statusId, fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNull('assigned_to')
            ->count();

        $agents = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'sub_agent'))
            ->orderBy('name')
            ->get();

        return view('deal-sheets.index', [
            'leads' => $leads,
            'agents' => $agents,
            'dealSheetStatusId' => $statusId,
            'newStatusId' => $newStatusId,
            'preview' => $preview,
            'previewToken' => $previewToken !== '' ? $previewToken : null,
            'unassignedCount' => $unassignedCount,
        ]);
    }

    /**
     * Step 1: parse uploads and cache preview data.
     */
    public function store(Request $request, LeadTxtExportParser $parser): RedirectResponse
    {
        $validated = $request->validate([
            'deal_sheets' => ['required', 'array', 'min:1', 'max:100'],
            'deal_sheets.*' => ['file', 'max:10240'],
            'import_status' => ['required', 'string', 'in:new,deal-sheet-uploaded'],
        ]);

        $importSlug = $validated['import_status'];
        $targetStatusId = Status::where('slug', $importSlug)->value('id');
        if ($targetStatusId === null) {
            return back()->withErrors([
                'import_status' => 'The selected status is not configured. Run migrations and seeders.',
            ])->withInput();
        }

        $files = $request->file('deal_sheets', []);
        $warnings = [];
        $filesPrepared = 0;
        $totalParsedLeads = 0;
        $preparedFiles = [];

        foreach ($files as $file) {
            if (! $file->isValid()) {
                $warnings[] = ($file->getClientOriginalName() ?? 'File') . ': upload failed.';

                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext !== 'txt') {
                $warnings[] = ($file->getClientOriginalName() ?? 'File') . ': must be a .txt file.';

                continue;
            }

            $raw = (string) file_get_contents($file->getRealPath() ?: '');
            $blocks = $parser->parseFile($raw);
            if ($blocks === []) {
                $warnings[] = ($file->getClientOriginalName() ?? 'File') . ': could not parse (use the same TXT format as lead export).';

                continue;
            }

            $storedPath = $file->store('deal-sheets/pending', 'local');
            $preparedFiles[] = [
                'original_name' => (string) ($file->getClientOriginalName() ?: 'deal-sheet.txt'),
                'stored_path' => $storedPath,
                'lead_count' => count($blocks),
                'blocks' => $blocks,
            ];
            $totalParsedLeads += count($blocks);
            $filesPrepared++;
        }

        if ($totalParsedLeads === 0) {
            return back()->withErrors([
                'deal_sheets' => $warnings !== []
                    ? implode(' ', $warnings)
                    : 'No leads were parsed. Add at least one valid .txt file.',
            ])->withInput();
        }

        $previewToken = (string) Str::uuid();
        Cache::put(
            $this->previewCacheKey((int) auth()->id(), $previewToken),
            [
                'created_by' => (int) auth()->id(),
                'import_status' => $importSlug,
                'target_status_id' => (int) $targetStatusId,
                'created_at' => now()->toIso8601String(),
                'files_prepared' => $filesPrepared,
                'total_leads' => $totalParsedLeads,
                'files' => $preparedFiles,
                'warnings' => $warnings,
            ],
            now()->addMinutes(self::PREVIEW_CACHE_TTL_MINUTES)
        );

        return redirect()->route('deal-sheets.index', ['preview' => $previewToken]);
    }

    /**
     * Step 2: confirm preview and import.
     */
    public function importPreview(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => ['required', 'string'],
        ]);

        $token = $validated['preview_token'];
        $cacheKey = $this->previewCacheKey((int) auth()->id(), $token);
        $preview = Cache::get($cacheKey);
        if (! is_array($preview) || (($preview['created_by'] ?? null) !== (int) auth()->id())) {
            return redirect()->route('deal-sheets.index')->withErrors([
                'deal_sheets' => 'Preview expired. Please upload files again.',
            ]);
        }

        $targetStatusId = (int) ($preview['target_status_id'] ?? 0);
        $importSlug = (string) ($preview['import_status'] ?? '');
        $isDealSheetLead = $importSlug === self::STATUS_SLUG;
        $files = is_array($preview['files'] ?? null) ? $preview['files'] : [];
        $adminId = auth()->id();
        $totalCreated = 0;
        $filesImported = 0;
        $warnings = is_array($preview['warnings'] ?? null) ? $preview['warnings'] : [];

        foreach ($files as $fileData) {
            $storedPath = (string) ($fileData['stored_path'] ?? '');
            $blocks = is_array($fileData['blocks'] ?? null) ? $fileData['blocks'] : [];
            if ($storedPath === '' || $blocks === []) {
                continue;
            }

            foreach ($blocks as $data) {
                if (! is_array($data)) {
                    continue;
                }
                DB::transaction(function () use ($data, $storedPath, $targetStatusId, $adminId, $isDealSheetLead): void {
                    $lead = Lead::create([
                        'first_name' => $data['first_name'] ?? 'Unknown',
                        'last_name' => ($data['last_name'] ?? '') !== '' ? (string) $data['last_name'] : '—',
                        'address' => $data['address'] ?? null,
                        'date_of_birth' => $data['date_of_birth'] ?? null,
                        'mothers_maiden_name' => $data['mothers_maiden_name'] ?? null,
                        'ssn' => $data['ssn'] ?? null,
                        'approx_debt' => $data['approx_debt'] ?? null,
                        'fees' => $data['fees'] ?? null,
                        'details' => $data['details'] ?? null,
                        'is_dnc' => false,
                        'status_id' => $targetStatusId,
                        'assigned_to' => null,
                        'deal_sheet_source_path' => $storedPath,
                        'is_deal_sheet' => $isDealSheetLead,
                        'skipped_at_sequence' => null,
                    ]);

                    foreach (($data['phones'] ?? []) as $phone) {
                        if (is_string($phone) && trim($phone) !== '') {
                            $lead->phones()->create(['phone' => $phone]);
                        }
                    }
                    if (! empty($data['email']) && is_string($data['email'])) {
                        $lead->emails()->create(['email' => $data['email']]);
                    }

                    foreach (($data['cards'] ?? []) as $c) {
                        if (! is_array($c)) {
                            continue;
                        }
                        LeadCard::create([
                            'lead_id' => $lead->id,
                            'bank_name' => $c['bank_name'] ?? null,
                            'bank_tollfree' => $c['bank_tollfree'] ?? null,
                            'card_number' => $c['card_number'] ?? null,
                            'name_on_card' => $c['name_on_card'] ?? null,
                            'card_expiry' => $c['card_expiry'] ?? null,
                            'card_cvc' => $c['card_cvc'] ?? null,
                            'balance' => $c['balance'] ?? null,
                            'available_amount' => $c['available_amount'] ?? null,
                            'last_payment' => $c['last_payment'] ?? null,
                            'due_payment' => $c['due_payment'] ?? null,
                            'apr' => $c['apr'] ?? null,
                            'charge_card' => (bool) ($c['charge_card'] ?? false),
                            'comment' => $c['comment'] ?? null,
                            'fees' => $c['fees'] ?? null,
                            'created_by' => $adminId,
                            'updated_by' => $adminId,
                        ]);
                    }

                    $sumFees = (float) $lead->cards()->sum('fees');
                    if ($sumFees > 0) {
                        $lead->update(['fees' => round($sumFees, 2)]);
                    }
                });

                $totalCreated++;
            }

            $filesImported++;
        }

        Cache::forget($cacheKey);

        if ($totalCreated === 0) {
            return redirect()->route('deal-sheets.index')->withErrors([
                'deal_sheets' => 'No leads were imported from preview. Please upload files again.',
            ]);
        }

        $message = $totalCreated === 1
            ? 'Imported 1 lead from '.$filesImported.' file(s).'
            : "Imported {$totalCreated} leads from {$filesImported} file(s).";

        $this->notifyAdminsOfBulkImport($totalCreated, $filesImported, $importSlug);

        $redirect = redirect()->route('deal-sheets.index')->with('success', $message);
        if ($warnings !== []) {
            $redirect->with('import_warnings', $warnings);
        }

        return $redirect;
    }

    private function notifyAdminsOfBulkImport(int $totalCreated, int $filesImported, string $importSlug): void
    {
        $adminIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $statusLabel = $importSlug === 'new' ? 'New' : 'Deal sheet uploaded';
        $notifyAt = now();
        $title = 'Bulk deal sheet import completed';
        $message = "Imported {$totalCreated} lead(s) from {$filesImported} file(s) as {$statusLabel} at {$notifyAt->format('Y-m-d H:i:s')}.";

        foreach ($adminIds as $adminId) {
            CrmNotification::query()->create([
                'created_by' => auth()->id(),
                'target_user_id' => (int) $adminId,
                'type' => 'deal_sheet.bulk_import',
                'entity_type' => 'lead',
                'entity_id' => null,
                'title' => $title,
                'message' => $message,
                'action_url' => route('deal-sheets.index'),
                'notify_at' => $notifyAt,
                'sent_at' => $notifyAt,
                'status' => 'sent',
                'priority' => 'normal',
                'meta' => [
                    'imported_leads' => $totalCreated,
                    'imported_files' => $filesImported,
                    'import_status' => $importSlug,
                ],
            ]);
        }
    }

    private function previewCacheKey(int $userId, string $token): string
    {
        return "deal_sheet_preview:{$userId}:{$token}";
    }

    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        if ($statusId === null || (int) $lead->status_id !== (int) $statusId) {
            abort(404);
        }

        if ($request->input('assigned_to') === '' || $request->input('assigned_to') === null) {
            $request->merge(['assigned_to' => null]);
        }

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assigneeId = $validated['assigned_to'] ?? null;
        if ($assigneeId !== null) {
            $isSubAgent = User::query()
                ->whereKey($assigneeId)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'sub_agent'))
                ->exists();
            if (! $isSubAgent) {
                return back()->withErrors(['assigned_to' => 'Assignee must be a sub agent.']);
            }
        }

        $lead->update(['assigned_to' => $assigneeId]);

        return back()->with('success', 'Assignment updated.');
    }

    public function assignBulk(Request $request): RedirectResponse
    {
        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        if ($statusId === null) {
            return back()->withErrors([
                'bulk_assigned_to' => 'Deal sheet uploaded status is not configured.',
            ]);
        }

        $validated = $request->validate([
            'bulk_assigned_to' => ['required', 'integer', 'exists:users,id'],
            'bulk_lead_count' => ['required', 'integer', 'min:1', 'max:5000'],
            'bulk_order' => ['required', 'string', 'in:latest,oldest'],
        ]);

        $assigneeId = (int) $validated['bulk_assigned_to'];
        $requestedCount = (int) $validated['bulk_lead_count'];
        $bulkOrder = (string) $validated['bulk_order'];
        $isSubAgent = User::query()
            ->whereKey($assigneeId)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'sub_agent'))
            ->exists();
        if (! $isSubAgent) {
            return back()->withErrors([
                'bulk_assigned_to' => 'Assignee must be a sub agent.',
            ])->withInput();
        }

        $assignedCount = DB::transaction(function () use ($assigneeId, $requestedCount, $statusId, $bulkOrder): int {
            $leadQuery = Lead::query()
                ->where('status_id', (int) $statusId)
                ->whereNull('assigned_to')
                ->limit($requestedCount)
                ->lockForUpdate();
            if ($bulkOrder === 'oldest') {
                $leadQuery->orderBy('created_at')->orderBy('id');
            } else {
                $leadQuery->orderByDesc('created_at')->orderByDesc('id');
            }

            $leadIds = $leadQuery->pluck('id')->all();

            if ($leadIds === []) {
                return 0;
            }

            return Lead::query()
                ->whereIn('id', $leadIds)
                ->whereNull('assigned_to')
                ->update(['assigned_to' => $assigneeId]);
        });

        if ($assignedCount <= 0) {
            return back()->with('error', 'No unassigned deal sheet leads were available for bulk assignment.');
        }

        $suffix = $assignedCount < $requestedCount
            ? " ({$requestedCount} requested)."
            : '.';

        return back()->with('success', "Assigned {$assignedCount} lead(s) to selected sub agent{$suffix}");
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        if ($statusId === null || (int) $lead->status_id !== (int) $statusId) {
            abort(404);
        }

        $lead->delete();

        return back()->with('success', 'Deal sheet lead deleted.');
    }
}
