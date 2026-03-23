<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\LeadCard;
use App\Models\LeadEmail;
use App\Models\LeadImportHistory;
use App\Models\LeadNote;
use App\Models\LeadPhone;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    private const ACTIVE_STATUS_SLUG = 'new';
    private const ROUND_ROBIN_SKIPPED_LEADS_KEY = 'round_robin_skipped_leads';
    private const ROUND_ROBIN_GLOBAL_SEQUENCE_KEY = 'round_robin_global_sequence';
    private const MIN_ROUND_ROBIN_RESHOW_LEADS = 2000;
    private const MAX_ROUND_ROBIN_RESHOW_LEADS = 50000;

    /** @var array<int, string>|null */
    private ?array $holdingStatusSlugsCache = null;

    /** @return array<int, string> */
    private function holdingStatusSlugs(): array
    {
        if ($this->holdingStatusSlugsCache !== null) {
            return $this->holdingStatusSlugsCache;
        }

        $this->holdingStatusSlugsCache = Setting::getJsonArray('holding_status_slugs', []);

        return $this->holdingStatusSlugsCache;
    }

    /** @var array<string, int>|null */
    private ?array $statusIdsBySlugCache = null;
    /** @var array<string, int>|null */
    private ?array $statusIdsByNameCache = null;

    public function index(Request $request): View
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();
        $isAgent = $user->isAgent();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'string', 'in:updated_at,approx_debt,fees'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = $request->query('sort', 'updated_at');
        $order = $request->query('order', 'desc');
        if (! in_array($sort, ['updated_at', 'approx_debt', 'fees'], true)) {
            $sort = 'updated_at';
        }
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $query = Lead::with(['status', 'assignedTo', 'phones', 'emails']);
        $statusesQuery = Status::orderBy('name');
        $holdingCount = null;
        $historyLimit = $this->agentHistoryLimit();
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);

        if ($isAgent) {
            $query->where('assigned_to', $user->id);
            $query->where('status_id', '!=', $newStatusId);
            $query->where('is_dnc', false);
            $query->whereIn('status_id', $this->holdingStatusIds());
            $statusesQuery->where('slug', '!=', self::ACTIVE_STATUS_SLUG);
            $holdingCount = $this->agentHoldingCount();
        }

        if ($isAdmin && $request->filled('status')) {
            $query->where('status_id', $request->status);
        }

        if ($isAdmin) {
            $query->where('status_id', '!=', $newStatusId);
        }

        if ($isAdmin && $request->has('assigned_to')) {
            if ($request->assigned_to === '') {
                $query->whereNull('assigned_to');
            } else {
                $assigneeId = (int) $request->assigned_to;
                if ($assigneeId > 0 && User::where('id', $assigneeId)->exists()) {
                    $query->where('assigned_to', $assigneeId);
                }
            }
        }

        if ($isAgent && $request->filled('status')) {
            $holdingStatusIds = $this->holdingStatusIds();
            if (in_array((int) $request->status, $holdingStatusIds, true)) {
                $query->where('status_id', $request->status);
            }
        }

        if (
            $isAdmin
            && $request->has('dnc')
            && in_array((string) $request->query('dnc'), ['0', '1'], true)
        ) {
            $query->where('is_dnc', $request->boolean('dnc'));
        }

        if ($request->filled('date_from')) {
            $range = date_filter_utc_range($request->date_from, null);
            if ($range['start'] !== null) {
                $query->where('updated_at', '>=', $range['start']);
            }
        }

        if ($request->filled('date_to')) {
            $range = date_filter_utc_range(null, $request->date_to);
            if ($range['end'] !== null) {
                $query->where('updated_at', '<=', $range['end']);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $like = '%' . $keyword . '%';

                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereHas('phones', function ($phones) use ($like): void {
                        $phones->where('phone', 'like', $like);
                    });
            });
        }

        if ($isAdmin) {
            $statusesQuery->where('slug', '!=', self::ACTIVE_STATUS_SLUG);
        }

        $query->orderBy($sort, $order);
        $leads = $isAgent
            ? $query->limit(50)->get()
            : $query->paginate(20)->withQueryString();

        $statuses = $isAgent
            ? Status::whereIn('slug', $this->holdingStatusSlugs())->orderBy('name')->get()
            : $statusesQuery->get();

        $availableColumns = [
            ['id' => 'name', 'label' => 'Name'],
            ['id' => 'status', 'label' => 'Status'],
            ['id' => 'total_debt', 'label' => 'Total Debt'],
            ['id' => 'fees', 'label' => 'Fees'],
            ['id' => 'last_update', 'label' => 'Last Update'],
            ['id' => 'dnc', 'label' => 'DNC'],
            ['id' => 'contacts', 'label' => 'Contacts'],
        ];
        if ($isAdmin) {
            $availableColumns[] = ['id' => 'assigned_to', 'label' => 'Assigned To'];
        }
        $defaultColumns = ['name', 'status', 'total_debt', 'last_update', 'dnc', 'contacts'];
        if ($isAdmin) {
            $defaultColumns[] = 'assigned_to';
        }

        $assignableUsers = $isAdmin
            ? User::whereHas('roles', fn ($q) => $q->where('slug', 'agent'))->orderBy('name')->get()
            : collect();

        return view('leads.index', compact('leads', 'statuses', 'holdingCount', 'historyLimit', 'availableColumns', 'defaultColumns', 'assignableUsers', 'sort', 'order'));
    }

    public function adminNewLeads(Request $request): View
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:updated_at,approx_debt,fees'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = $request->query('sort', 'updated_at');
        $order = $request->query('order', 'desc');
        if (! in_array($sort, ['updated_at', 'approx_debt', 'fees'], true)) {
            $sort = 'updated_at';
        }
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $query = $this->newLeadsQuery()->with(['assignedTo', 'status', 'phones', 'emails']);

        if (
            $request->has('dnc')
            && in_array((string) $request->query('dnc'), ['0', '1'], true)
        ) {
            $query->where('is_dnc', $request->boolean('dnc'));
        }

        if ($request->filled('date_from')) {
            $range = date_filter_utc_range($request->date_from, null);
            if ($range['start'] !== null) {
                $query->where('updated_at', '>=', $range['start']);
            }
        }

        if ($request->filled('date_to')) {
            $range = date_filter_utc_range(null, $request->date_to);
            if ($range['end'] !== null) {
                $query->where('updated_at', '<=', $range['end']);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereHas('phones', function ($phones) use ($like): void {
                        $phones->where('phone', 'like', $like);
                    });
            });
        }

        $leads = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('leads.new', compact('leads', 'sort', 'order'));
    }

    public function newLeadsCount(): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $count = $this->newLeadsQuery()->count();

        return response()->json(['count' => $count]);
    }

    /** Bulk soft-delete selected leads that are still in New status (New Leads page only). */
    public function bulkDestroyNewLeads(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1', 'max:500'],
            'lead_ids.*' => ['integer', 'min:1'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['lead_ids'])));

        $deleted = Lead::query()
            ->newStatusOnly()
            ->whereIn('id', $ids)
            ->delete();

        $skipped = count($ids) - (int) $deleted;

        $message = $deleted > 0
            ? "Deleted {$deleted} lead(s)."
            : 'No matching leads were deleted.';
        if ($skipped > 0) {
            $message .= " {$skipped} selected lead(s) were skipped (not in New status).";
        }

        return redirect()->route('leads.new.index')->with('success', $message);
    }

    /** Leads that have status "new" only (used for New Leads page and count). */
    private function newLeadsQuery(): Builder
    {
        return Lead::query()->newStatusOnly();
    }

    private function isLeadNewOrUnassigned(Lead $lead): bool
    {
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);

        return (int) $lead->status_id === (int) $newStatusId || $lead->assigned_to === null;
    }

    private function notifyAdminsOfNewLeadIfApplicable(Lead $lead): void
    {
        if (! $this->isLeadNewOrUnassigned($lead)) {
            return;
        }

        $thresholdRaw = Setting::get('new_leads_notification_threshold', '');
        if ($thresholdRaw !== '' && $thresholdRaw !== null) {
            $threshold = (int) $thresholdRaw;
            $currentNewLeadsCount = $this->newLeadsQuery()->count();
            if ($currentNewLeadsCount >= $threshold) {
                return;
            }
        }

        $adminIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->pluck('id');

        $notifyAt = now();
        $title = 'New lead: ' . $lead->fullName();
        $message = 'Lead has status New or is unassigned.';
        $actionUrl = route('leads.edit', $lead);

        foreach ($adminIds as $adminId) {
            CrmNotification::query()->create([
                'created_by' => auth()->id(),
                'target_user_id' => (int) $adminId,
                'type' => 'new_lead',
                'entity_type' => 'lead',
                'entity_id' => $lead->id,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'notify_at' => $notifyAt,
                'sent_at' => $notifyAt,
                'status' => 'sent',
                'priority' => 'normal',
                'meta' => ['lead_id' => $lead->id],
            ]);
        }
    }

    public function agentQueue(Request $request): View
    {
        $activeLead = $this->agentActiveLead();
        $holdingCount = $this->agentHoldingCount();
        $historyLimit = $this->agentHistoryLimit();
        $isBlockedByHistory = $holdingCount >= $historyLimit;
        $candidateLead = null;

        if (! $activeLead && ! $isBlockedByHistory) {
            $candidateLead = $this->nextAvailableLead($request);
        }

        return view('agent.queue', [
            'activeLead' => $activeLead,
            'candidateLead' => $candidateLead,
            'holdingCount' => $holdingCount,
            'historyLimit' => $historyLimit,
            'isBlockedByHistory' => $isBlockedByHistory,
        ]);
    }

    public function agentSkip(Request $request): RedirectResponse
    {
        $request->validate([
            'lead_id' => ['required', 'integer'],
        ]);

        if ($this->agentActiveLead()) {
            return redirect()->route('agent.queue')->with('error', 'You already have an active lead. Submit it first.');
        }

        if ($this->agentHoldingCount() >= $this->agentHistoryLimit()) {
            return redirect()->route('agent.queue')->with('error', 'You reached the history limit. Update a history lead to a final status first.');
        }

        $queueStatusIds = $this->queueAssignableStatusIds();
        $isStillAvailable = Lead::query()
            ->whereKey($request->integer('lead_id'))
            ->whereNull('assigned_to')
            ->whereIn('status_id', $queueStatusIds)
            ->where('is_dnc', false)
            ->exists();

        if (! $isStillAvailable) {
            return redirect()->route('agent.queue')->with('error', 'That lead is no longer available.');
        }

        $offset = (int) $request->session()->get('agent_skip_offset', 0);
        $request->session()->put('agent_skip_offset', $offset + 1);

        $lastGlobalSequence = (int) $request->session()->get('agent_last_shown_global_sequence', 0);
        $this->markLeadSkippedGlobally($request->integer('lead_id'), $lastGlobalSequence);
        $request->session()->forget(['agent_last_shown_lead_id', 'agent_last_shown_global_sequence']);

        return redirect()->route('agent.queue');
    }

    public function agentTake(Request $request): RedirectResponse
    {
        $request->validate([
            'lead_id' => ['required', 'integer'],
        ]);

        $leadId = $request->integer('lead_id');
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);
        $queueStatusIds = $this->queueAssignableStatusIds();
        $agentId = auth()->id();

        if ($this->agentHoldingCount() >= $this->agentHistoryLimit()) {
            return redirect()->route('agent.queue')->with('error', 'You reached the history limit. Update a history lead to a final status first.');
        }

        try {
            $assignedLead = DB::transaction(function () use ($agentId, $leadId, $newStatusId, $queueStatusIds) {
                $hasActiveLead = Lead::query()
                    ->where('assigned_to', $agentId)
                    ->where('status_id', $newStatusId)
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveLead) {
                    throw new \RuntimeException('You already have an active lead.');
                }

                $lead = Lead::query()
                    ->whereKey($leadId)
                    ->whereNull('assigned_to')
                    ->whereIn('status_id', $queueStatusIds)
                    ->where('is_dnc', false)
                    ->lockForUpdate()
                    ->first();

                if (! $lead) {
                    throw new \RuntimeException('That lead is no longer available.');
                }

                $lead->assigned_to = $agentId;
                $lead->save();

                return $lead;
            });
        } catch (\Throwable $e) {
            return redirect()->route('agent.queue')->with('error', $e->getMessage());
        }

        $request->session()->forget(['agent_skipped_lead_ids', 'agent_skip_offset', 'agent_last_shown_lead_id', 'agent_last_shown_global_sequence']);

        return redirect()->route('leads.edit', $assignedLead)->with('success', 'Lead assigned to you.');
    }

    public function agentHistory(): RedirectResponse
    {
        return redirect()->route('leads.index');
    }

    public function importForm(): View
    {
        $statuses = Status::orderBy('name')->get();
        $defaultStatusId = $statuses->firstWhere('slug', self::ACTIVE_STATUS_SLUG)?->id
            ?? $statuses->first()?->id;
        $importHistories = LeadImportHistory::with(['uploadedBy', 'defaultStatus'])
            ->latest()
            ->limit(30)
            ->get();

        return view('leads.import', compact('statuses', 'importHistories', 'defaultStatusId'));
    }

    public function downloadSampleCsv(): BinaryFileResponse|StreamedResponse
    {
        $samplePath = 'templates/leads_sample.csv';
        if (Storage::exists($samplePath)) {
            return response()->download(Storage::path($samplePath), 'leads-sample.csv', [
                'Content-Type' => 'text/csv',
            ]);
        }

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'F name',
                'M name',
                'L name',
                'Address',
                'city',
                'state',
                'zip',
                'ssn',
                'Dob',
                'Debt',
                'Fees',
                'phone1',
                'phone2',
                'phone3',
                'phone4',
                'phone5',
            ]);
            fputcsv($out, [
                'John',
                'A',
                'Doe',
                '123 Main St',
                'Dallas',
                'TX',
                '75001',
                '111-22-3333',
                '1988-04-12',
                '12000.50',
                '450.00',
                '5551234567',
                '5551234568',
                '5551234569',
                '',
                '',
            ]);
            fclose($out);
        }, 'leads-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'default_status_id' => ['required', 'exists:statuses,id'],
        ]);

        $file = $request->file('file');
        $defaultStatusId = (int) $request->default_status_id;
        $timestamp = now()->format('YmdHis');
        $safeName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $safeName ?: 'upload');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $storedOriginalPath = $file->storeAs(
            'lead-imports/originals',
            "{$timestamp}-{$safeName}." . $extension
        );
        if ($storedOriginalPath === false) {
            return back()->with('error', 'Could not store uploaded CSV file.');
        }

        $history = LeadImportHistory::create([
            'uploaded_by' => auth()->id(),
            'default_status_id' => $defaultStatusId,
            'original_file_name' => (string) $file->getClientOriginalName(),
            'original_file_path' => $storedOriginalPath,
            'total_rows' => 0,
            'created_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ]);

        try {
            [$header, $rows] = $this->extractImportRows((string) $file->getRealPath(), $extension);
        } catch (\Throwable) {
            return back()->with('error', 'Could not read the uploaded file. Please upload a valid CSV/XLSX.');
        }

        if ($header === []) {
            return back()->with('error', 'Uploaded file is empty.');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $failedRows = [];
        $totalRows = 0;

        foreach ($rows as $row) {
            $row = array_pad($row, count($header), '');
            $data = array_combine($header, $row);
            if ($data === false) {
                $totalRows++;
                $errors[] = 'Row format mismatch.';
                $failedRows[] = [
                    'data' => array_fill_keys($header, ''),
                    'reason' => 'Row format mismatch.',
                ];
                continue;
            }
            $data = array_map('trim', $data);
            if (! $this->rowHasAnyValue($data)) {
                continue;
            }
            $totalRows++;

            $firstName = $this->getCsvColumn($data, ['F name', 'first_name', 'firstname', 'f name', 'f_name', 'fname']);
            $middleName = $this->getCsvColumn($data, ['M name', 'middle_name', 'middlename', 'm name', 'm_name', 'mname']);
            $lastName = $this->getCsvColumn($data, ['L name', 'last_name', 'lastname', 'l name', 'l_name', 'lname']);

            $firstName = trim($firstName . ($middleName !== '' ? ' ' . $middleName : ''));
            if ($firstName === '' || $lastName === '') {
                $reason = 'Missing first_name or last_name.';
                $errors[] = $reason . ' Row: ' . implode(', ', $row);
                $failedRows[] = [
                    'data' => $data,
                    'reason' => $reason,
                ];
                continue;
            }

            $statusId = $this->resolveStatusFromCsv($data, $defaultStatusId);
            $assignedTo = $this->resolveAssignedToFromCsv($data);
            $details = $this->getCsvColumn($data, ['details', 'notes', 'comment', 'comments']);
            $details = trim($details);
            if ($middleName !== '') {
                $details = $details !== ''
                    ? $details . ' | Middle Name: ' . $middleName
                    : 'Middle Name: ' . $middleName;
            }

            $address = $this->buildAddressFromCsv($data);
            $dateOfBirth = $this->parseDateOfBirthFromCsv($this->getCsvColumn($data, ['Dob', 'date_of_birth', 'dob', 'date of birth']));
            $csvPhones = array_slice($this->normalizeLines($this->csvPhonesFromData($data)), 0, 5);
            $csvEmails = $this->normalizeLines($this->csvEmailsFromData($data));

            try {
                DB::transaction(function () use ($data, $firstName, $lastName, $address, $dateOfBirth, $csvPhones, $csvEmails, $statusId, $assignedTo, $details, &$created, &$updated) {
                    $existingLead = $this->findExistingLeadByAddressPhone($address, $csvPhones);

                    if ($existingLead !== null) {
                        $existingLead->update([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'address' => $address,
                            'date_of_birth' => $dateOfBirth,
                            'mothers_maiden_name' => $this->getCsvColumn($data, ['mothers_maiden_name', 'mmn']),
                            'ssn' => $this->getCsvColumn($data, ['ssn']),
                            'approx_debt' => $this->parseDecimal($this->getCsvColumn($data, ['Debt', 'approx_debt', 'debt'])),
                            'fees' => $this->parseDecimal($this->getCsvColumn($data, ['Fees', 'fees', 'fee'])),
                            'details' => $details !== '' ? $details : null,
                            'is_dnc' => $this->parseBoolean($this->getCsvColumn($data, ['is_dnc', 'dnc'])),
                            'status_id' => $statusId,
                            'assigned_to' => $assignedTo,
                        ]);

                        $existingLead->phones()->delete();
                        foreach ($csvPhones as $p) {
                            $existingLead->phones()->create(['phone' => $p]);
                        }

                        $existingLead->emails()->delete();
                        foreach ($csvEmails as $e) {
                            $existingLead->emails()->create(['email' => $e]);
                        }

                        $updated++;
                    } else {
                        $lead = Lead::create([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'address' => $address,
                            'date_of_birth' => $dateOfBirth,
                            'mothers_maiden_name' => $this->getCsvColumn($data, ['mothers_maiden_name', 'mmn']),
                            'ssn' => $this->getCsvColumn($data, ['ssn']),
                            'approx_debt' => $this->parseDecimal($this->getCsvColumn($data, ['Debt', 'approx_debt', 'debt'])),
                            'fees' => $this->parseDecimal($this->getCsvColumn($data, ['Fees', 'fees', 'fee'])),
                            'details' => $details !== '' ? $details : null,
                            'is_dnc' => $this->parseBoolean($this->getCsvColumn($data, ['is_dnc', 'dnc'])),
                            'status_id' => $statusId,
                            'assigned_to' => $assignedTo,
                        ]);

                        foreach ($csvPhones as $p) {
                            $lead->phones()->create(['phone' => $p]);
                        }

                        foreach ($csvEmails as $e) {
                            $lead->emails()->create(['email' => $e]);
                        }

                        $this->notifyAdminsOfNewLeadIfApplicable($lead);
                        $created++;
                    }
                });
            } catch (\Throwable $e) {
                $reason = 'Row error: ' . $e->getMessage();
                $errors[] = $reason . ' Row: ' . implode(', ', $row);
                $failedRows[] = [
                    'data' => $data,
                    'reason' => $reason,
                ];
            }
        }

        $failedPath = null;
        if (! empty($failedRows)) {
            $failedPath = $this->storeFailedRowsCsv($history->id, $header, $failedRows);
        }

        $skipped = max(0, $totalRows - $created - $updated);
        $history->update([
            'total_rows' => $totalRows,
            'created_rows' => $created,
            'skipped_rows' => $skipped,
            'failed_rows' => count($failedRows),
            'failed_rows_file_path' => $failedPath,
        ]);

        $message = $created + $updated > 0
            ? "{$created} lead(s) created, {$updated} lead(s) updated from {$totalRows} row(s)."
            : "No leads created or updated from {$totalRows} row(s).";
        if (count($errors) > 0) {
            $message .= ' ' . count($errors) . ' row(s) skipped or failed.';
            return redirect()->route('leads.import.form')->with('success', $message)->with('import_errors', $errors);
        }
        return redirect()->route('leads.import.form')->with('success', $message);
    }

    public function downloadImportHistoryFile(LeadImportHistory $importHistory, string $type): BinaryFileResponse|RedirectResponse
    {
        if (! in_array($type, ['original', 'failed'], true)) {
            abort(404);
        }

        $path = $type === 'original'
            ? $importHistory->original_file_path
            : $importHistory->failed_rows_file_path;

        if (! $path || ! Storage::exists($path)) {
            return back()->with('error', 'Requested file is not available.');
        }

        $downloadName = $type === 'original'
            ? 'original-' . basename($path)
            : 'failed-rows-' . basename($path);

        return response()->download(Storage::path($path), $downloadName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(): StreamedResponse
    {
        $leads = Lead::with(['status', 'assignedTo', 'phones', 'emails'])->orderBy('id')->get();

        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'first_name', 'last_name', 'status', 'address', 'date_of_birth', 'mothers_maiden_name',
                'ssn', 'approx_debt', 'fees', 'details', 'is_dnc',
                'phone1', 'phone2', 'phone3', 'phone4', 'phone5',
                'email', 'assigned_to',
            ]);
            foreach ($leads as $lead) {
                $ph = $lead->phones->values();
                fputcsv($out, [
                    $lead->first_name,
                    $lead->last_name,
                    $lead->status->slug ?? '',
                    $lead->address ?? '',
                    $lead->date_of_birth?->format('Y-m-d') ?? '',
                    $lead->mothers_maiden_name ?? '',
                    $lead->ssn ?? '',
                    $lead->approx_debt ?? '',
                    $lead->fees ?? '',
                    $lead->details ?? '',
                    $lead->is_dnc ? '1' : '0',
                    $ph->get(0)?->phone ?? '',
                    $ph->get(1)?->phone ?? '',
                    $ph->get(2)?->phone ?? '',
                    $ph->get(3)?->phone ?? '',
                    $ph->get(4)?->phone ?? '',
                    $lead->emails->first()?->email ?? '',
                    $lead->assignedTo?->username ?? $lead->assignedTo?->email ?? '',
                ]);
            }
            fclose($out);
        }, 'leads-' . date('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportTxt(Request $request): StreamedResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'exists:statuses,id'],
            'dnc' => ['nullable', 'in:0,1'],
            'assigned_to' => ['nullable', 'string', 'max:20'],
        ]);

        $query = Lead::with(['status', 'assignedTo', 'phones', 'emails', 'cards'])->latest('updated_at');
        $this->applyAdminListFilters($query, $request);
        $leads = $query->get();

        return response()->streamDownload(function () use ($leads): void {
            $out = fopen('php://output', 'w');
            foreach ($leads as $index => $lead) {
                fwrite($out, $this->buildLeadTxtPayload($lead));
                if ($index < ($leads->count() - 1)) {
                    fwrite($out, PHP_EOL . str_repeat('-', 70) . PHP_EOL . PHP_EOL);
                }
            }
            fclose($out);
        }, 'leads-export-' . now()->format('Ymd-His') . '.txt', [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function downloadLeadTxt(Lead $lead): StreamedResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $lead->loadMissing(['status', 'assignedTo', 'phones', 'emails', 'cards']);
        $safeName = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $lead->fullName()) ?: 'lead');

        return response()->streamDownload(function () use ($lead): void {
            echo $this->buildLeadTxtPayload($lead);
        }, 'lead-' . $safeName . '-' . $lead->id . '.txt', [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only admin can create leads manually.');
        }

        $statuses = Status::orderBy('name')->get();
        $callbackStatusId = $this->statusIdsBySlug()['callback'] ?? null;

        return view('leads.create', compact('statuses', 'callbackStatusId'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only admin can create leads manually.');
        }

        $validated = $this->validateLeadPayload($request);

        $callbackStatusId = $this->statusIdsBySlug()['callback'] ?? null;
        if ($callbackStatusId !== null && (int) $validated['status_id'] === $callbackStatusId) {
            $callbackAtUtc = $validated['callback_at_utc'] ?? null;
            $date = $validated['callback_date'] ?? null;
            $time = $validated['callback_time'] ?? null;
            $hasUtc = $callbackAtUtc !== null && $callbackAtUtc !== '';
            $hasDateTime = trim((string) $date) !== '' && trim((string) $time) !== '';
            if (! $hasUtc && ! $hasDateTime) {
                return back()->withErrors(['callback_date' => 'Callback date and time are required when status is Callback.'])->withInput();
            }
            if ($hasUtc) {
                try {
                    $callbackAt = \Carbon\Carbon::parse($callbackAtUtc, 'UTC');
                    if ($callbackAt->isPast()) {
                        return back()->withErrors(['callback_date' => 'Callback date and time must be in the future.'])->withInput();
                    }
                } catch (\Throwable $e) {
                    return back()->withErrors(['callback_at_utc' => 'Invalid callback date/time.'])->withInput();
                }
            } elseif ($hasDateTime) {
                try {
                    $callbackAt = \Carbon\Carbon::parse($date . ' ' . $time, app_timezone());
                    if ($callbackAt->isPast()) {
                        return back()->withErrors(['callback_date' => 'Callback date and time must be in the future.'])->withInput();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        $lead = Lead::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'address' => $validated['address'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'mothers_maiden_name' => $validated['mothers_maiden_name'],
            'ssn' => $validated['ssn'],
            'approx_debt' => $validated['approx_debt'] ?? null,
            'fees' => $validated['fees'] ?? null,
            'details' => $validated['details'],
            'is_dnc' => $request->boolean('is_dnc'),
            'status_id' => $validated['status_id'],
            'assigned_to' => auth()->user()->isAgent() ? auth()->id() : null,
        ]);

        foreach ($this->normalizedPhonesFromRequest($validated['phones'] ?? null) as $phone) {
            $lead->phones()->create(['phone' => $phone]);
        }
        foreach ($this->normalizeLines($validated['emails'] ?? []) as $email) {
            $lead->emails()->create(['email' => $email]);
        }

        if ($callbackStatusId !== null && (int) $validated['status_id'] === $callbackStatusId) {
            $this->createCallbackReminderIfRequested(
                $lead,
                (int) $validated['status_id'],
                $validated['callback_date'] ?? null,
                $validated['callback_time'] ?? null,
                $validated['callback_at_utc'] ?? null
            );
        }

        $this->notifyAdminsOfNewLeadIfApplicable($lead);

        return redirect()->route('leads.index')->with('success', 'Lead created successfully.');
    }

    public function createRelated(Lead $lead): View
    {
        $this->authorizeView($lead);

        $statuses = auth()->user()->isAgent()
            ? Status::where('slug', '!=', self::ACTIVE_STATUS_SLUG)->orderBy('name')->get()
            : Status::orderBy('name')->get();

        return view('leads.related-create', [
            'lead' => $lead,
            'statuses' => $statuses,
        ]);
    }

    public function storeRelated(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);
        $validated = $this->validateLeadPayload($request);

        if (auth()->user()->isAgent() && (int) $validated['status_id'] === $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG)) {
            return back()->with('error', 'Agent cannot create related lead with New status.')->withInput();
        }

        $assignedTo = auth()->user()->isAgent()
            ? auth()->id()
            : ($lead->assigned_to ?: null);

        $relatedLead = Lead::create([
            'parent_lead_id' => $lead->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'address' => $validated['address'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'mothers_maiden_name' => $validated['mothers_maiden_name'],
            'ssn' => $validated['ssn'],
            'approx_debt' => $validated['approx_debt'] ?? null,
            'fees' => $validated['fees'] ?? null,
            'details' => $validated['details'],
            'is_dnc' => $request->boolean('is_dnc'),
            'status_id' => $validated['status_id'],
            'assigned_to' => $assignedTo,
        ]);

        foreach ($this->normalizedPhonesFromRequest($validated['phones'] ?? null) as $phone) {
            $relatedLead->phones()->create(['phone' => $phone]);
        }
        foreach ($this->normalizeLines($validated['emails'] ?? []) as $email) {
            $relatedLead->emails()->create(['email' => $email]);
        }

        $this->notifyAdminsOfNewLeadIfApplicable($relatedLead);

        return redirect()->route('leads.edit', $relatedLead)->with('success', 'Related lead added successfully.');
    }

    public function callbacksIndex(): View
    {
        $callbacks = CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->where('type', 'like', 'callback.%')
            ->where('entity_type', 'lead')
            ->orderByDesc('notify_at')
            ->paginate(20);

        $leadIds = $callbacks->getCollection()->pluck('entity_id')->unique()->filter()->values()->all();
        $leads = $leadIds !== [] ? Lead::whereIn('id', $leadIds)->get()->keyBy('id') : collect();

        return view('callbacks.index', compact('callbacks', 'leads'));
    }

    public function show(Lead $lead): View
    {
        $this->authorizeView($lead);
        $lead->load(['status', 'assignedTo', 'phones', 'emails', 'notes.createdBy', 'cards.createdBy', 'cards.updatedBy', 'creditReports.requestedBy', 'creditReports.processedBy']);
        $callbackNotifications = CrmNotification::query()
            ->where('entity_type', 'lead')
            ->where('entity_id', $lead->id)
            ->where('type', 'like', 'callback.%')
            ->with('createdBy')
            ->orderByDesc('notify_at')
            ->get();
        return view('leads.show', compact('lead', 'callbackNotifications'));
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        LeadNote::create([
            'lead_id' => $lead->id,
            'created_by' => auth()->id(),
            'note' => $validated['note'],
        ]);

        return redirect()->route('leads.edit', $lead)->with('success', 'Note added successfully.');
    }

    public function createCard(Lead $lead): View
    {
        $this->authorizeView($lead);

        return view('leads.cards.create', compact('lead'));
    }

    public function storeCard(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);
        $validated = $this->validateCardRequest($request);

        $lead->cards()->create([
            ...$validated,
            'charge_card' => $request->boolean('charge_card'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->syncLeadFeesFromCards($lead);

        return redirect()->route('leads.edit', $lead)->with('success', 'Card added successfully.');
    }

    public function editCard(Lead $lead, LeadCard $card): View
    {
        $this->authorizeView($lead);
        $this->assertCardBelongsToLead($lead, $card);

        return view('leads.cards.edit', compact('lead', 'card'));
    }

    public function updateCard(Request $request, Lead $lead, LeadCard $card): RedirectResponse
    {
        $this->authorizeView($lead);
        $this->assertCardBelongsToLead($lead, $card);
        $validated = $this->validateCardRequest($request);

        $card->update([
            ...$validated,
            'charge_card' => $request->boolean('charge_card'),
            'updated_by' => auth()->id(),
        ]);

        $this->syncLeadFeesFromCards($lead);

        return redirect()->route('leads.edit', $lead)->with('success', 'Card updated successfully.');
    }

    public function destroyCard(Lead $lead, LeadCard $card): RedirectResponse
    {
        $this->authorizeView($lead);
        $this->assertCardBelongsToLead($lead, $card);
        $card->delete();

        $this->syncLeadFeesFromCards($lead);

        return redirect()->route('leads.edit', $lead)->with('success', 'Card deleted successfully.');
    }

    private function syncLeadFeesFromCards(Lead $lead): void
    {
        $total = $lead->cards()->sum('fees');
        $lead->update(['fees' => $total]);
    }

    public function edit(Lead $lead): View
    {
        $this->authorizeView($lead);
        $lead->load(['phones', 'emails', 'notes.createdBy', 'cards.createdBy', 'cards.updatedBy', 'creditReports.requestedBy', 'creditReports.processedBy']);
        $statuses = auth()->user()->isAgent()
            ? Status::where('slug', '!=', self::ACTIVE_STATUS_SLUG)->orderBy('name')->get()
            : Status::orderBy('name')->get();
        $agents = auth()->user()->isAdmin()
            ? \App\Models\User::whereHas('roles', fn ($q) => $q->where('slug', 'agent'))->orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->displayName()])
            : collect();
        $callbackStatusId = $this->statusIdsBySlug()['callback'] ?? null;
        $callbackDate = null;
        $callbackTime = null;
        $callbackAtUtc = null;
        if ($callbackStatusId !== null && (int) $lead->status_id === (int) $callbackStatusId) {
            $latest = CrmNotification::query()
                ->where('entity_type', 'lead')
                ->where('entity_id', $lead->id)
                ->where('type', 'callback.reminder')
                ->orderByDesc('notify_at')
                ->first();
            if ($latest && ! empty($latest->meta['callback_at'])) {
                $callbackAtUtc = $latest->meta['callback_at'];
                try {
                    $at = \Carbon\Carbon::parse($callbackAtUtc);
                    $callbackDate = $at->format('Y-m-d');
                    $callbackTime = $at->format('H:i');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        return view('leads.edit', compact('lead', 'statuses', 'agents', 'callbackStatusId', 'callbackDate', 'callbackTime', 'callbackAtUtc'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);

        $validated = $this->validateLeadPayload($request, true);

        if (auth()->user()->isAgent() && (int) $validated['status_id'] === $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG)) {
            return back()->with('error', 'Agent must submit with a non-New status.')->withInput();
        }

        $callbackStatusId = $this->statusIdsBySlug()['callback'] ?? null;
        if ($callbackStatusId !== null && (int) $validated['status_id'] === $callbackStatusId) {
            $callbackAtUtc = $validated['callback_at_utc'] ?? null;
            $date = $validated['callback_date'] ?? null;
            $time = $validated['callback_time'] ?? null;
            $hasUtc = $callbackAtUtc !== null && $callbackAtUtc !== '';
            $hasDateTime = trim((string) $date) !== '' && trim((string) $time) !== '';
            if (! $hasUtc && ! $hasDateTime) {
                return back()->withErrors(['callback_date' => 'Callback date and time are required when status is Callback.'])->withInput();
            }
            if ($hasUtc) {
                try {
                    $callbackAt = \Carbon\Carbon::parse($callbackAtUtc, 'UTC');
                    if ($callbackAt->isPast()) {
                        return back()->withErrors(['callback_date' => 'Callback date and time must be in the future.'])->withInput();
                    }
                } catch (\Throwable $e) {
                    return back()->withErrors(['callback_at_utc' => 'Invalid callback date/time.'])->withInput();
                }
            } else {
                if ($date !== null && $date !== '' && $time !== null && $time !== '') {
                    try {
                        $callbackAt = \Carbon\Carbon::parse($date . ' ' . $time, app_timezone());
                        if ($callbackAt->isPast()) {
                            return back()->withErrors(['callback_date' => 'Callback date and time must be in the future.'])->withInput();
                        }
                    } catch (\Throwable $e) {
                        // validation already ensured date/time format; ignore parse errors
                    }
                }
            }
        }

        $lead->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'address' => $validated['address'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'mothers_maiden_name' => $validated['mothers_maiden_name'],
            'ssn' => $validated['ssn'],
            'approx_debt' => $validated['approx_debt'] ?? null,
            'fees' => $lead->cards()->sum('fees'),
            'details' => $validated['details'],
            'is_dnc' => $request->boolean('is_dnc'),
            'status_id' => $validated['status_id'],
            'assigned_to' => $this->shouldUnassignAfterStatusUpdate((int) $validated['status_id'])
                ? null
                : (auth()->user()->isAdmin() ? ($validated['assigned_to'] ?? null) : $lead->assigned_to),
        ]);

        $lead->phones()->delete();
        foreach ($this->normalizedPhonesFromRequest($validated['phones'] ?? null) as $phone) {
            $lead->phones()->create(['phone' => $phone]);
        }
        $lead->emails()->delete();
        foreach ($this->normalizeLines($validated['emails'] ?? []) as $email) {
            $lead->emails()->create(['email' => $email]);
        }

        $this->createCallbackReminderIfRequested(
            $lead,
            (int) $validated['status_id'],
            $validated['callback_date'] ?? null,
            $validated['callback_time'] ?? null,
            $validated['callback_at_utc'] ?? null
        );

        return redirect()->route('leads.edit', $lead)->with('success', 'Lead updated successfully.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead deleted successfully.');
    }

    private function authorizeView(Lead $lead): void
    {
        if (auth()->user()->isAgent()) {
            if ($lead->is_dnc) {
                abort(403, 'DNC leads are not available to agents.');
            }

            if ($lead->assigned_to !== auth()->id()) {
                abort(403, 'You can only view leads assigned to you.');
            }

            if (! in_array((int) $lead->status_id, $this->holdingStatusIds(), true)) {
                abort(403, 'You can only access leads in holding statuses.');
            }
        }
    }

    /** @return array<string> */
    private function normalizeLines(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                foreach (preg_split('/\r\n|\r|\n/', $item) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $out[] = $line;
                    }
                }
            }
        }
        return $out;
    }

    /** @return array<int, string> At most 5 phone numbers from form input. */
    private function normalizedPhonesFromRequest(?array $phones): array
    {
        return array_slice($this->normalizeLines($phones ?? []), 0, 5);
    }

    /**
     * Find an existing lead matching address and at least one phone number.
     * Used on CSV import to update instead of create when both match.
     */
    private function findExistingLeadByAddressPhone(string $address, array $phoneNumbers): ?Lead
    {
        $addressNorm = $this->normalizeAddressForMatch($address);
        $phoneDigitsList = array_values(array_filter(array_map(
            fn (string $p) => $this->normalizePhoneToDigits($p),
            $phoneNumbers
        )));

        if ($addressNorm === '' || $phoneDigitsList === []) {
            return null;
        }

        $leadIdsWithMatchingPhone = LeadPhone::query()
            ->get()
            ->filter(fn (LeadPhone $ph) => in_array($this->normalizePhoneToDigits($ph->phone), $phoneDigitsList, true))
            ->pluck('lead_id')
            ->unique()
            ->values()
            ->all();

        if ($leadIdsWithMatchingPhone === []) {
            return null;
        }

        $candidates = Lead::query()
            ->with('phones')
            ->whereIn('id', $leadIdsWithMatchingPhone)
            ->get();

        foreach ($candidates as $lead) {
            if ($this->normalizeAddressForMatch($lead->address ?? '') === $addressNorm) {
                return $lead;
            }
        }

        return null;
    }

    private function normalizeAddressForMatch(string $address): string
    {
        $v = trim(preg_replace('/[\s\t\n\r]+/u', ' ', $address));
        return strtolower($v);
    }

    private function normalizePhoneToDigits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    private function applyAdminListFilters(Builder $query, Request $request): void
    {
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);

        if ($request->filled('status')) {
            $query->where('status_id', (int) $request->query('status'));
        } else {
            $query->where('status_id', '!=', $newStatusId);
        }

        if ($request->has('dnc') && in_array((string) $request->query('dnc'), ['0', '1'], true)) {
            $query->where('is_dnc', $request->boolean('dnc'));
        }

        if ($request->has('assigned_to')) {
            $val = $request->query('assigned_to');
            if ($val === '' || $val === null) {
                $query->whereNull('assigned_to');
            } elseif ($val !== 'all') {
                $assigneeId = (int) $val;
                if ($assigneeId > 0 && User::where('id', $assigneeId)->exists()) {
                    $query->where('assigned_to', $assigneeId);
                }
            }
        }

        if ($request->filled('date_from')) {
            $range = date_filter_utc_range((string) $request->query('date_from'), null);
            if ($range['start'] !== null) {
                $query->where('updated_at', '>=', $range['start']);
            }
        }
        if ($request->filled('date_to')) {
            $range = date_filter_utc_range(null, (string) $request->query('date_to'));
            if ($range['end'] !== null) {
                $query->where('updated_at', '<=', $range['end']);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereHas('phones', fn (Builder $phones) => $phones->where('phone', 'like', $like));
            });
        }
    }

    private function buildLeadTxtPayload(Lead $lead): string
    {
        $phones = $lead->phones->pluck('phone')->filter()->values();
        $emails = $lead->emails->pluck('email')->filter()->values();
        $cards = $lead->cards;
        $commentLines = preg_split('/\r\n|\r|\n/', trim((string) ($lead->details ?? ''))) ?: [];
        $firstCommentLine = array_shift($commentLines) ?: '';

        $lines = [];
        $lines[] = 'Name : ' . trim($lead->first_name . ' ' . $lead->last_name);
        $phoneLabels = ['Phone', 'Alt Phone', 'Phone 3', 'Phone 4', 'Phone 5'];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = $phoneLabels[$i] . ' : ' . ($phones->get($i) ?? '');
        }
        $lines[] = 'Add : ' . ((string) ($lead->address ?? ''));
        $lines[] = 'Dob : ' . ($lead->date_of_birth?->format('m/d/Y') ?? '');
        $lines[] = 'Mmn : ' . ((string) ($lead->mothers_maiden_name ?? ''));
        $lines[] = 'Ssn : ' . ((string) ($lead->ssn ?? ''));
        $lines[] = 'Email : ' . ($emails->get(0) ?? '');
        $lines[] = '';
        $lines[] = 'Comment : ' . $firstCommentLine;
        foreach ($commentLines as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = '';

        foreach ($cards as $card) {
            $bn = (string) ($card->bank_name ?? '');
            $feesLabel = $card->fees !== null && (float) $card->fees > 0
                ? '$' . $this->formatTxtNumber((float) $card->fees)
                : '';
            if ($card->charge_card) {
                $bn .= $feesLabel !== '' ? " ( Charge Card {$feesLabel} )" : ' ( Charge Card )';
            } elseif ($feesLabel !== '') {
                $bn .= " ( Fees {$feesLabel} )";
            }

            $lines[] = 'BN : ' . $bn;
            $lines[] = 'Card holder name : ' . ((string) ($card->name_on_card ?? ''));
            $lines[] = 'BT : ' . ((string) ($card->bank_tollfree ?? ''));
            $lines[] = 'CC : ' . ((string) ($card->card_number ?? ''));
            $lines[] = 'Exp : ' . ((string) ($card->card_expiry ?? ''));
            $lines[] = 'Cvc : ' . ((string) ($card->card_cvc ?? ''));
            $lines[] = '';
            $lines[] = 'Bal : ' . $this->formatTxtNumber($card->balance);
            $lines[] = 'Av : ' . $this->formatTxtNumber($card->available_amount);
            $lines[] = 'Lp : ' . ((string) ($card->last_payment ?? ''));
            $lines[] = 'Dp : ' . ((string) ($card->due_payment ?? ''));
            $lines[] = 'Apr : ' . $this->formatTxtNumber($card->apr);
            $lines[] = 'Comment : ' . ((string) ($card->comment ?? ''));
            $lines[] = 'Fees : ' . $this->formatTxtNumber($card->fees);
            $lines[] = '';
        }

        $totalDebt = $lead->approx_debt;
        if ($totalDebt === null) {
            $totalDebt = (float) $cards->sum(fn ($card) => (float) ($card->balance ?? 0));
        }

        $lines[] = 'Tdebt : ' . $this->formatTxtNumber($totalDebt);
        $lines[] = 'Tcards : ' . $cards->count();
        $lines[] = 'Charge Amount : ' . $this->formatTxtNumber($cards->sum('fees'));
        $lines[] = '';
        $lines[] = 'Deal : ';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function formatTxtNumber(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        if ($number === 0.0) {
            return '0';
        }

        $formatted = number_format($number, 2, '.', ',');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function extractImportRows(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            return $this->readXlsxRows($path);
        }

        return $this->readCsvRows($path);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return [[], []];
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($handle);

        return [$header, $rows];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function readXlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open XLSX file.');
        }

        $sharedStrings = [];
        $sharedXmlRaw = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXmlRaw) && $sharedXmlRaw !== '') {
            $sharedXml = simplexml_load_string($sharedXmlRaw);
            if ($sharedXml !== false) {
                foreach ($sharedXml->si as $item) {
                    if (isset($item->t)) {
                        $sharedStrings[] = trim((string) $item->t);
                        continue;
                    }

                    $text = '';
                    foreach ($item->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = trim($text);
                }
            }
        }

        $sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (! is_string($sheetRaw) || $sheetRaw === '') {
            $zip->close();
            throw new \RuntimeException('XLSX first sheet not found.');
        }

        $sheetXml = simplexml_load_string($sheetRaw);
        if ($sheetXml === false) {
            $zip->close();
            throw new \RuntimeException('Invalid XLSX sheet XML.');
        }

        $sheetXml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowsXml = $sheetXml->xpath('//m:sheetData/m:row') ?: [];
        $zip->close();

        $rows = [];
        foreach ($rowsXml as $rowXml) {
            $cells = $rowXml->xpath('./m:c') ?: [];
            $line = [];
            foreach ($cells as $cell) {
                $cellType = (string) ($cell['t'] ?? '');
                $valueNode = $cell->xpath('./m:v');
                $rawValue = isset($valueNode[0]) ? trim((string) $valueNode[0]) : '';

                if ($cellType === 's' && $rawValue !== '') {
                    $line[] = $sharedStrings[(int) $rawValue] ?? '';
                    continue;
                }

                $line[] = $rawValue;
            }
            $rows[] = $line;
        }

        if ($rows === []) {
            return [[], []];
        }

        $header = array_map(fn ($value) => trim((string) $value), (array) array_shift($rows));
        $rows = array_map(
            fn ($row) => array_map(fn ($value) => trim((string) $value), (array) $row),
            $rows
        );

        return [$header, $rows];
    }

    private function normalizeCsvKey(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($key)));
    }

    private function getCsvColumn(array $data, array $possibleKeys): string
    {
        $normalizedData = [];
        foreach ($data as $key => $value) {
            $normalizedData[$this->normalizeCsvKey((string) $key)] = trim((string) $value);
        }

        foreach ($possibleKeys as $key) {
            $normalizedKey = $this->normalizeCsvKey((string) $key);
            if (isset($normalizedData[$normalizedKey]) && $normalizedData[$normalizedKey] !== '') {
                return $normalizedData[$normalizedKey];
            }
        }

        return '';
    }

    private function buildAddressFromCsv(array $data): string
    {
        $address = $this->getCsvColumn($data, ['Address', 'address', 'street_address', 'street']);
        $city = $this->getCsvColumn($data, ['city']);
        $state = $this->getCsvColumn($data, ['state']);
        $zip = $this->getCsvColumn($data, ['zip', 'zipcode', 'postal_code']);

        $location = trim(implode(', ', array_filter([$city, $state, $zip], fn ($value) => $value !== '')));

        if ($address === '') {
            return $location;
        }

        if ($location === '') {
            return $address;
        }

        return $address . ', ' . $location;
    }

    /** @return array<int, string> Up to 5 phone numbers from CSV columns. */
    private function csvPhonesFromData(array $data): array
    {
        $phones = array_values(array_filter([
            $this->getCsvColumn($data, ['phone', 'phones', 'phone1']),
            $this->getCsvColumn($data, ['phone2']),
            $this->getCsvColumn($data, ['phone3']),
            $this->getCsvColumn($data, ['phone4']),
            $this->getCsvColumn($data, ['phone5']),
        ], fn ($value) => $value !== ''));

        return array_slice($phones, 0, 5);
    }

    /** @return array<int, string> */
    private function csvEmailsFromData(array $data): array
    {
        return array_values(array_filter([
            $this->getCsvColumn($data, ['email', 'emails', 'email1']),
            $this->getCsvColumn($data, ['email2']),
            $this->getCsvColumn($data, ['email3']),
        ], fn ($value) => $value !== ''));
    }

    private function rowHasAnyValue(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{data: array<string, mixed>, reason: string}> $failedRows
     */
    private function storeFailedRowsCsv(int $historyId, array $header, array $failedRows): string
    {
        $temp = fopen('php://temp', 'r+');
        if ($temp === false) {
            throw new \RuntimeException('Unable to create failed rows CSV.');
        }

        fputcsv($temp, [...$header, 'error_reason']);
        foreach ($failedRows as $failedRow) {
            $line = [];
            foreach ($header as $column) {
                $line[] = (string) ($failedRow['data'][$column] ?? '');
            }
            $line[] = $failedRow['reason'];
            fputcsv($temp, $line);
        }

        rewind($temp);
        $content = stream_get_contents($temp);
        fclose($temp);
        if ($content === false) {
            throw new \RuntimeException('Unable to generate failed rows CSV content.');
        }

        $path = 'lead-imports/failed/' . now()->format('YmdHis') . '-history-' . $historyId . '-failed.csv';
        Storage::put($path, $content);

        return $path;
    }

    /** @return array<string, mixed> */
    private function validateCardRequest(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_tollfree' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:50'],
            'name_on_card' => ['nullable', 'string', 'max:255'],
            'card_expiry' => ['nullable', 'string', 'max:20'],
            'card_cvc' => ['nullable', 'string', 'max:10'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'available_amount' => ['nullable', 'numeric', 'min:0'],
            'last_payment' => ['nullable', 'string', 'max:255'],
            'due_payment' => ['nullable', 'string', 'max:255'],
            'apr' => ['nullable', 'numeric', 'min:0'],
            'charge_card' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string'],
            'fees' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function assertCardBelongsToLead(Lead $lead, LeadCard $card): void
    {
        if ((int) $card->lead_id !== (int) $lead->id) {
            abort(404);
        }
    }

    private function resolveStatusFromCsv(array $data, int $defaultStatusId): int
    {
        $status = $this->getCsvColumn($data, ['status', 'status_slug']);
        if ($status !== '') {
            $normalized = strtolower(trim($status));
            $slugMap = $this->statusIdsBySlug();
            if (isset($slugMap[$normalized])) {
                return $slugMap[$normalized];
            }

            $nameMap = $this->statusIdsByName();
            if (isset($nameMap[$normalized])) {
                return $nameMap[$normalized];
            }
        }
        return $defaultStatusId;
    }

    private function resolveAssignedToFromCsv(array $data): ?int
    {
        $assigned = $this->getCsvColumn($data, ['assigned_to', 'assigned_to_username', 'username']);
        if ($assigned === '') {
            return null;
        }
        $user = User::where('username', $assigned)->orWhere('email', $assigned)->first();
        return $user?->id;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse date of birth from CSV column. Handles:
     * - Trailing age in parentheses, e.g. "09/17/1959 (65)" -> age is ignored
     * - Unknown day as XX, e.g. "08/XX/1961 (64)" -> stored as first of month (1961-08-01)
     * Expects MM/DD/YYYY (or MM/XX/YYYY) after stripping age.
     */
    private function parseDateOfBirthFromCsv(?string $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);
        $value = preg_replace('/\s*\(\d+\)\s*$/', '', $value);
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\bXX\b/i', '01', $value);
        try {
            $date = \Carbon\Carbon::createFromFormat('m/d/Y', $value);
            return $date !== false ? $date->format('Y-m-d') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = preg_replace('/[^0-9.-]/', '', $value);
        return (float) $value ?: null;
    }

    private function parseBoolean(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function statusIdBySlug(string $slug): int
    {
        $statusIdsBySlug = $this->statusIdsBySlug();
        if (! isset($statusIdsBySlug[$slug])) {
            abort(500, "Status '{$slug}' is not configured.");
        }

        return $statusIdsBySlug[$slug];
    }

    /** @return array<int> */
    private function holdingStatusIds(): array
    {
        $statusIdsBySlug = $this->statusIdsBySlug();
        $ids = [];

        foreach ($this->holdingStatusSlugs() as $slug) {
            if (isset($statusIdsBySlug[$slug])) {
                $ids[] = $statusIdsBySlug[$slug];
            }
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function statusIdsBySlug(): array
    {
        if ($this->statusIdsBySlugCache !== null) {
            return $this->statusIdsBySlugCache;
        }

        $this->statusIdsBySlugCache = Status::query()
            ->get(['id', 'slug'])
            ->pluck('id', 'slug')
            ->mapWithKeys(fn ($id, $slug) => [strtolower((string) $slug) => (int) $id])
            ->all();

        return $this->statusIdsBySlugCache;
    }

    /** @return array<string, int> */
    private function statusIdsByName(): array
    {
        if ($this->statusIdsByNameCache !== null) {
            return $this->statusIdsByNameCache;
        }

        $this->statusIdsByNameCache = Status::query()
            ->get(['id', 'name'])
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => (int) $id])
            ->all();

        return $this->statusIdsByNameCache;
    }

    private function agentActiveLead(): ?Lead
    {
        return Lead::with('status')
            ->where('assigned_to', auth()->id())
            ->where('status_id', $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG))
            ->first();
    }

    private function agentHoldingCount(): int
    {
        return Lead::where('assigned_to', auth()->id())
            ->whereIn('status_id', $this->holdingStatusIds())
            ->whereNull('parent_lead_id')
            ->count();
    }

    /** @return array<string, mixed> */
    private function validateLeadPayload(Request $request, bool $includeAssignedTo = false): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'mothers_maiden_name' => ['nullable', 'string', 'max:255'],
            'ssn' => ['nullable', 'string', 'max:255'],
            'approx_debt' => ['nullable', 'numeric', 'min:0'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
            'is_dnc' => ['nullable', 'boolean'],
            'status_id' => ['required', 'exists:statuses,id'],
            'callback_date' => ['nullable', 'date'],
            'callback_time' => ['nullable', 'date_format:H:i'],
            'callback_at_utc' => ['nullable', 'string', 'max:64'],
            'phones' => ['nullable', 'array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($includeAssignedTo) {
            $rules['assigned_to'] = ['nullable', 'exists:users,id'];
        }

        return $request->validate($rules);
    }

    /**
     * Create a callback reminder only if this lead has no pending callback (no existing reminder whose callback time is still in the future).
     * Callback time can be provided as UTC ISO string (callback_at_utc) or as date+time in app timezone.
     * Returns true if a reminder was created, false if skipped because a pending callback exists.
     */
    private function createCallbackReminderIfRequested(Lead $lead, int $statusId, ?string $callbackDate, ?string $callbackTime, ?string $callbackAtUtc = null): bool
    {
        $callbackStatusId = $this->statusIdsBySlug()['callback'] ?? null;
        if ($callbackStatusId === null || $statusId !== $callbackStatusId) {
            return true;
        }

        $callbackAt = null;
        if ($callbackAtUtc !== null && $callbackAtUtc !== '') {
            try {
                $callbackAt = \Carbon\Carbon::parse($callbackAtUtc, 'UTC');
            } catch (\Throwable $e) {
                return true;
            }
        } elseif ($callbackDate !== null && $callbackDate !== '' && $callbackTime !== null && $callbackTime !== '') {
            $callbackAt = \Carbon\Carbon::parse($callbackDate . ' ' . $callbackTime, app_timezone());
        }

        if ($callbackAt === null) {
            return true;
        }

        $existing = CrmNotification::query()
            ->where('entity_type', 'lead')
            ->where('entity_id', $lead->id)
            ->where('type', 'callback.reminder')
            ->get();

        foreach ($existing as $notification) {
            $at = $notification->meta['callback_at'] ?? null;
            if ($at !== null && $at !== '' && \Carbon\Carbon::parse($at)->isFuture()) {
                return false;
            }
        }

        $minutes = (int) (Setting::get('callback_reminder_minutes', '15') ?? 15);
        $notifyAt = $callbackAt->copy()->subMinutes($minutes);
        $targetUserId = $lead->assigned_to ?? auth()->id();
        if ($targetUserId === null) {
            return true;
        }

        $callbackAtUtcString = $callbackAt->utc()->toIso8601String();

        CrmNotification::query()
            ->where('entity_type', 'lead')
            ->where('entity_id', $lead->id)
            ->where('type', 'callback.reminder')
            ->where('notify_at', '>', now())
            ->delete();

        CrmNotification::query()->create([
            'created_by' => auth()->id(),
            'target_user_id' => (int) $targetUserId,
            'type' => 'callback.reminder',
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'title' => 'Callback: ' . $lead->fullName(),
            'message' => 'Scheduled for ' . $callbackAt->copy()->setTimezone(app_timezone())->format('M j, Y g:i A') . '.',
            'action_url' => route('leads.edit', $lead),
            'notify_at' => $notifyAt,
            'sent_at' => null,
            'status' => 'sent',
            'priority' => 'normal',
            'meta' => [
                'lead_id' => $lead->id,
                'callback_at' => $callbackAtUtcString,
            ],
        ]);

        return true;
    }

    private function agentHistoryLimit(): int
    {
        $fallback = (int) env('AGENT_HISTORY_LIMIT', 50);
        $configured = (int) (Setting::get('agent_history_limit', (string) $fallback) ?? $fallback);

        return max(1, $configured);
    }

    /**
     * Next lead for the current agent using round-robin: one global list (same for all agents),
     * lead at index i is assigned to agent (i % numAgents). Skip advances this agent's slot.
     * Skipped leads are hidden globally until N more leads have been shown (minimum 2000),
     * or returned only when all non-skipped leads are exhausted.
     */
    private function nextAvailableLead(Request $request): ?Lead
    {
        $queueStatusIds = $this->queueAssignableStatusIds();

        $leadIds = Lead::query()
            ->whereNull('assigned_to')
            ->whereIn('status_id', $queueStatusIds)
            ->where('is_dnc', false)
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        if ($leadIds === []) {
            $request->session()->forget('agent_skip_offset');
            return null;
        }

        $nReshow = $this->roundRobinReshowThreshold();
        $globalSequence = $this->roundRobinGlobalSequence();
        $skipList = $this->roundRobinSkippedLeadsMap();
        $leadIdsSet = array_flip($leadIds);
        $skipList = array_intersect_key($skipList, $leadIdsSet);

        foreach ($skipList as $leadId => $skipSeq) {
            if ($globalSequence - (int) $skipSeq >= $nReshow) {
                unset($skipList[$leadId]);
            }
        }
        $this->persistRoundRobinSkippedLeadsMap($skipList);

        $availableLeadIds = array_values(array_filter($leadIds, fn ($id) => ! isset($skipList[$id])));
        if ($availableLeadIds === []) {
            // All remaining leads are in the global skipped pool, so allow them again.
            $availableLeadIds = $leadIds;
        }

        if ($availableLeadIds === []) {
            $request->session()->forget('agent_skip_offset');
            return null;
        }

        $agentIds = $this->queueAgentIds();
        if ($agentIds === []) {
            $leadId = $availableLeadIds[0];
            $globalSequence = $this->incrementRoundRobinGlobalSequence();
            $request->session()->put('agent_last_shown_lead_id', $leadId);
            $request->session()->put('agent_last_shown_global_sequence', $globalSequence);
            return Lead::with(['status', 'phones'])->find($leadId);
        }

        $currentAgentId = auth()->id();
        $myIndex = array_search((int) $currentAgentId, $agentIds, true);
        if ($myIndex === false) {
            $leadId = $availableLeadIds[0];
            $globalSequence = $this->incrementRoundRobinGlobalSequence();
            $request->session()->put('agent_last_shown_lead_id', $leadId);
            $request->session()->put('agent_last_shown_global_sequence', $globalSequence);
            return Lead::with(['status', 'phones'])->find($leadId);
        }

        $numAgents = count($agentIds);
        $skipOffset = (int) $request->session()->get('agent_skip_offset', 0);
        $positionForMe = $myIndex + ($skipOffset * $numAgents);

        if ($positionForMe >= count($availableLeadIds)) {
            $request->session()->forget('agent_skip_offset');
            return null;
        }

        $leadId = $availableLeadIds[$positionForMe];
        $globalSequence = $this->incrementRoundRobinGlobalSequence();
        $request->session()->put('agent_last_shown_lead_id', $leadId);
        $request->session()->put('agent_last_shown_global_sequence', $globalSequence);

        return Lead::with(['status', 'phones'])->find($leadId);
    }

    private function roundRobinReshowThreshold(): int
    {
        $configured = (int) (Setting::get('round_robin_leads_before_skipped_reshown', (string) self::MIN_ROUND_ROBIN_RESHOW_LEADS) ?? self::MIN_ROUND_ROBIN_RESHOW_LEADS);

        return max(self::MIN_ROUND_ROBIN_RESHOW_LEADS, min(self::MAX_ROUND_ROBIN_RESHOW_LEADS, $configured));
    }

    private function roundRobinGlobalSequence(): int
    {
        return max(0, (int) (Setting::get(self::ROUND_ROBIN_GLOBAL_SEQUENCE_KEY, '0') ?? '0'));
    }

    private function incrementRoundRobinGlobalSequence(): int
    {
        $next = $this->roundRobinGlobalSequence() + 1;
        Setting::put(self::ROUND_ROBIN_GLOBAL_SEQUENCE_KEY, (string) $next);

        return $next;
    }

    /** @return array<int, int> */
    private function roundRobinSkippedLeadsMap(): array
    {
        $raw = Setting::get(self::ROUND_ROBIN_SKIPPED_LEADS_KEY, '');
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $leadId => $skipSeq) {
            $id = (int) $leadId;
            $seq = (int) $skipSeq;
            if ($id > 0 && $seq >= 0) {
                $result[$id] = $seq;
            }
        }

        return $result;
    }

    /** @param array<int, int> $skipList */
    private function persistRoundRobinSkippedLeadsMap(array $skipList): void
    {
        if ($skipList === []) {
            Setting::put(self::ROUND_ROBIN_SKIPPED_LEADS_KEY, '');
            return;
        }

        ksort($skipList);
        Setting::put(self::ROUND_ROBIN_SKIPPED_LEADS_KEY, json_encode($skipList));
    }

    private function markLeadSkippedGlobally(int $leadId, int $lastShownGlobalSequence): void
    {
        if ($leadId <= 0) {
            return;
        }

        $skipList = $this->roundRobinSkippedLeadsMap();
        $sequence = $lastShownGlobalSequence > 0
            ? $lastShownGlobalSequence
            : $this->roundRobinGlobalSequence();
        $skipList[$leadId] = $sequence;
        $this->persistRoundRobinSkippedLeadsMap($skipList);
    }

    /** @return array<int> Ordered list of user IDs with agent role (stable order for round-robin). */
    private function queueAgentIds(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'agent'))
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();
    }

    /** @return array<int> */
    private function queueAssignableStatusIds(): array
    {
        $statusIdsBySlug = $this->statusIdsBySlug();
        $ids = [];

        foreach ([self::ACTIVE_STATUS_SLUG, 'not-interested'] as $slug) {
            if (isset($statusIdsBySlug[$slug])) {
                $ids[] = $statusIdsBySlug[$slug];
            }
        }

        return array_values(array_unique($ids));
    }

    private function shouldUnassignAfterStatusUpdate(int $statusId): bool
    {
        $notInterestedId = $this->statusIdsBySlug()['not-interested'] ?? null;

        return $notInterestedId !== null && $statusId === (int) $notInterestedId;
    }
}
