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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DealSheetController extends Controller
{
    private const STATUS_SLUG = 'deal-sheet-uploaded';

    public function index(): View
    {
        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        $newStatusId = Status::where('slug', 'new')->value('id');

        $leads = Lead::query()
            ->with(['status', 'assignedTo', 'phones'])
            ->when($statusId, fn ($q) => $q->where('status_id', $statusId))
            ->when(! $statusId, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $agents = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'agent'))
            ->orderBy('name')
            ->get();

        return view('deal-sheets.index', [
            'leads' => $leads,
            'agents' => $agents,
            'dealSheetStatusId' => $statusId,
            'newStatusId' => $newStatusId,
        ]);
    }

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
        $totalCreated = 0;
        $filesImported = 0;
        $adminId = auth()->id();

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

            $path = $file->store('deal-sheets', 'local');

            foreach ($blocks as $data) {
                DB::transaction(function () use ($data, $path, $targetStatusId, $adminId): void {
                    $lead = Lead::create([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'] !== '' ? $data['last_name'] : '—',
                        'address' => $data['address'],
                        'date_of_birth' => $data['date_of_birth'],
                        'mothers_maiden_name' => $data['mothers_maiden_name'],
                        'ssn' => $data['ssn'],
                        'approx_debt' => $data['approx_debt'],
                        'fees' => $data['fees'],
                        'details' => $data['details'],
                        'is_dnc' => false,
                        'status_id' => $targetStatusId,
                        'assigned_to' => null,
                        'deal_sheet_source_path' => $path,
                        'skipped_at_sequence' => null,
                    ]);

                    foreach ($data['phones'] as $phone) {
                        $lead->phones()->create(['phone' => $phone]);
                    }
                    if (! empty($data['email'])) {
                        $lead->emails()->create(['email' => $data['email']]);
                    }

                    foreach ($data['cards'] as $c) {
                        LeadCard::create([
                            'lead_id' => $lead->id,
                            'bank_name' => $c['bank_name'] ?: null,
                            'bank_tollfree' => $c['bank_tollfree'] ?: null,
                            'card_number' => $c['card_number'] ?: null,
                            'name_on_card' => $c['name_on_card'] ?: null,
                            'card_expiry' => $c['card_expiry'] ?: null,
                            'card_cvc' => $c['card_cvc'] ?: null,
                            'balance' => $c['balance'],
                            'available_amount' => $c['available_amount'],
                            'last_payment' => $c['last_payment'] ?: null,
                            'due_payment' => $c['due_payment'] ?: null,
                            'apr' => $c['apr'],
                            'charge_card' => (bool) ($c['charge_card'] ?? false),
                            'comment' => $c['comment'] ?: null,
                            'fees' => $c['fees'],
                            'created_by' => $adminId,
                            'updated_by' => $adminId,
                        ]);
                    }

                    $lead->refresh();
                    $sumFees = (float) $lead->cards()->sum('fees');
                    if ($sumFees > 0) {
                        $lead->update(['fees' => round($sumFees, 2)]);
                    }

                });

                $totalCreated++;
            }

            $filesImported++;
        }

        if ($totalCreated === 0) {
            return back()->withErrors([
                'deal_sheets' => $warnings !== []
                    ? implode(' ', $warnings)
                    : 'No leads were created. Add at least one valid .txt file.',
            ])->withInput();
        }

        $message = $totalCreated === 1
            ? 'Imported 1 lead from '.$filesImported.' file(s).'
            : "Imported {$totalCreated} leads from {$filesImported} file(s).";

        $redirect = redirect()
            ->route('deal-sheets.index')
            ->with('success', $message);

        if ($warnings !== []) {
            $redirect->with('import_warnings', $warnings);
        }

        $this->notifyAdminsOfBulkImport($totalCreated, $filesImported, $importSlug);

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
            $isAgent = User::query()
                ->whereKey($assigneeId)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'agent'))
                ->exists();
            if (! $isAgent) {
                return back()->withErrors(['assigned_to' => 'Assignee must be an agent.']);
            }
        }

        $lead->update(['assigned_to' => $assigneeId]);

        return back()->with('success', 'Assignment updated.');
    }
}
