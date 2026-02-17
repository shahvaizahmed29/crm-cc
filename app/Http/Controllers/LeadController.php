<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadCard;
use App\Models\LeadEmail;
use App\Models\LeadImportHistory;
use App\Models\LeadNote;
use App\Models\LeadPhone;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    private const ACTIVE_STATUS_SLUG = 'new';

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
        ]);

        $query = Lead::with(['status', 'assignedTo', 'phones', 'emails']);
        $statusesQuery = Status::orderBy('name');
        $holdingCount = null;
        $historyLimit = $this->agentHistoryLimit();
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);

        if ($isAgent) {
            $query->where('assigned_to', $user->id);
            $query->where('status_id', '!=', $newStatusId);
            $query->where('is_dnc', false);
            $statusesQuery->where('slug', '!=', self::ACTIVE_STATUS_SLUG);
            $holdingCount = $this->agentHoldingCount();
        }

        if ($isAdmin && $request->filled('status')) {
            $query->where('status_id', $request->status);
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
            $query->whereDate('updated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
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

        if ($isAdmin && ! $request->filled('status')) {
            $query->where('status_id', '!=', $newStatusId);
            $statusesQuery->where('slug', '!=', self::ACTIVE_STATUS_SLUG);
        }

        $query->latest('updated_at');
        $leads = $isAgent
            ? $query->limit(50)->get()
            : $query->paginate(20)->withQueryString();

        $statuses = $isAgent
            ? Status::whereIn('slug', $this->holdingStatusSlugs())->orderBy('name')->get()
            : $statusesQuery->get();

        return view('leads.index', compact('leads', 'statuses', 'holdingCount', 'historyLimit'));
    }

    public function adminNewLeads(Request $request): View
    {
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);
        $leads = Lead::with(['assignedTo', 'phones', 'emails'])
            ->where('status_id', $newStatusId)
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('leads.new', compact('leads'));
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

        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);
        $isStillAvailable = Lead::query()
            ->whereKey($request->integer('lead_id'))
            ->whereNull('assigned_to')
            ->where('status_id', $newStatusId)
            ->where('is_dnc', false)
            ->exists();

        if (! $isStillAvailable) {
            return redirect()->route('agent.queue')->with('error', 'That lead is no longer available.');
        }

        $skipped = $request->session()->get('agent_skipped_lead_ids', []);
        $skipped[] = $request->integer('lead_id');
        $skipped = array_values(array_unique($skipped));
        if (count($skipped) > 200) {
            $skipped = array_slice($skipped, -200);
        }
        $request->session()->put('agent_skipped_lead_ids', $skipped);

        return redirect()->route('agent.queue');
    }

    public function agentTake(Request $request): RedirectResponse
    {
        $request->validate([
            'lead_id' => ['required', 'integer'],
        ]);

        $leadId = $request->integer('lead_id');
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);
        $agentId = auth()->id();

        if ($this->agentHoldingCount() >= $this->agentHistoryLimit()) {
            return redirect()->route('agent.queue')->with('error', 'You reached the history limit. Update a history lead to a final status first.');
        }

        try {
            $assignedLead = DB::transaction(function () use ($agentId, $leadId, $newStatusId) {
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
                    ->where('status_id', $newStatusId)
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

        $request->session()->forget('agent_skipped_lead_ids');

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
                'phone1',
                'phone2',
                'phone3',
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
                '5551234567',
                '5551234568',
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

            $firstName = $this->getCsvColumn($data, ['first_name', 'firstname', 'f name', 'f_name', 'fname']);
            $middleName = $this->getCsvColumn($data, ['middle_name', 'middlename', 'm name', 'm_name', 'mname']);
            $lastName = $this->getCsvColumn($data, ['last_name', 'lastname', 'l name', 'l_name', 'lname']);

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

            try {
                DB::transaction(function () use ($data, $firstName, $lastName, $statusId, $assignedTo, $details, &$created) {
                    $lead = Lead::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'address' => $this->buildAddressFromCsv($data),
                        'date_of_birth' => $this->parseDate($this->getCsvColumn($data, ['date_of_birth', 'dob', 'date of birth'])),
                        'mothers_maiden_name' => $this->getCsvColumn($data, ['mothers_maiden_name', 'mmn']),
                        'ssn' => $this->getCsvColumn($data, ['ssn']),
                        'approx_debt' => $this->parseDecimal($this->getCsvColumn($data, ['approx_debt', 'debt'])),
                        'details' => $details !== '' ? $details : null,
                        'is_dnc' => $this->parseBoolean($this->getCsvColumn($data, ['is_dnc', 'dnc'])),
                        'status_id' => $statusId,
                        'assigned_to' => $assignedTo,
                    ]);

                    foreach ($this->normalizeLines($this->csvPhonesFromData($data)) as $p) {
                        $lead->phones()->create(['phone' => $p]);
                    }

                    foreach ($this->normalizeLines($this->csvEmailsFromData($data)) as $e) {
                        $lead->emails()->create(['email' => $e]);
                    }

                    $created++;
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

        $skipped = max(0, $totalRows - $created);
        $history->update([
            'total_rows' => $totalRows,
            'created_rows' => $created,
            'skipped_rows' => $skipped,
            'failed_rows' => count($failedRows),
            'failed_rows_file_path' => $failedPath,
        ]);

        $message = "{$created} lead(s) imported from {$totalRows} row(s).";
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
                'ssn', 'approx_debt', 'details', 'is_dnc', 'phone', 'email', 'assigned_to',
            ]);
            foreach ($leads as $lead) {
                fputcsv($out, [
                    $lead->first_name,
                    $lead->last_name,
                    $lead->status->slug ?? '',
                    $lead->address ?? '',
                    $lead->date_of_birth?->format('Y-m-d') ?? '',
                    $lead->mothers_maiden_name ?? '',
                    $lead->ssn ?? '',
                    $lead->approx_debt ?? '',
                    $lead->details ?? '',
                    $lead->is_dnc ? '1' : '0',
                    $lead->phones->first()?->phone ?? '',
                    $lead->emails->first()?->email ?? '',
                    $lead->assignedTo?->username ?? $lead->assignedTo?->email ?? '',
                ]);
            }
            fclose($out);
        }, 'leads-' . date('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function create(): View
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only admin can create leads manually.');
        }

        $statuses = Status::orderBy('name')->get();
        return view('leads.create', compact('statuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Only admin can create leads manually.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'mothers_maiden_name' => ['nullable', 'string', 'max:255'],
            'ssn' => ['nullable', 'string', 'max:255'],
            'approx_debt' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
            'is_dnc' => ['nullable', 'boolean'],
            'status_id' => ['required', 'exists:statuses,id'],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = Lead::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'address' => $validated['address'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'mothers_maiden_name' => $validated['mothers_maiden_name'],
            'ssn' => $validated['ssn'],
            'approx_debt' => $validated['approx_debt'] ?? null,
            'details' => $validated['details'],
            'is_dnc' => $request->boolean('is_dnc'),
            'status_id' => $validated['status_id'],
            'assigned_to' => auth()->user()->isAgent() ? auth()->id() : null,
        ]);

        foreach ($this->normalizeLines($validated['phones'] ?? []) as $phone) {
            $lead->phones()->create(['phone' => $phone]);
        }
        foreach ($this->normalizeLines($validated['emails'] ?? []) as $email) {
            $lead->emails()->create(['email' => $email]);
        }

        return redirect()->route('leads.index')->with('success', 'Lead created successfully.');
    }

    public function show(Lead $lead): View
    {
        $this->authorizeView($lead);
        $lead->load(['status', 'assignedTo', 'phones', 'emails', 'notes.createdBy', 'cards.createdBy', 'cards.updatedBy', 'creditReports.requestedBy', 'creditReports.processedBy']);
        return view('leads.show', compact('lead'));
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

        return redirect()->route('leads.edit', $lead)->with('success', 'Card updated successfully.');
    }

    public function destroyCard(Lead $lead, LeadCard $card): RedirectResponse
    {
        $this->authorizeView($lead);
        $this->assertCardBelongsToLead($lead, $card);
        $card->delete();

        return redirect()->route('leads.edit', $lead)->with('success', 'Card deleted successfully.');
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
        return view('leads.edit', compact('lead', 'statuses', 'agents'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeView($lead);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'mothers_maiden_name' => ['nullable', 'string', 'max:255'],
            'ssn' => ['nullable', 'string', 'max:255'],
            'approx_debt' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
            'is_dnc' => ['nullable', 'boolean'],
            'status_id' => ['required', 'exists:statuses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['nullable', 'string', 'max:255'],
        ]);

        if (auth()->user()->isAgent() && (int) $validated['status_id'] === $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG)) {
            return back()->with('error', 'Agent must submit with a non-New status.')->withInput();
        }

        $lead->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'address' => $validated['address'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'mothers_maiden_name' => $validated['mothers_maiden_name'],
            'ssn' => $validated['ssn'],
            'approx_debt' => $validated['approx_debt'] ?? null,
            'details' => $validated['details'],
            'is_dnc' => $request->boolean('is_dnc'),
            'status_id' => $validated['status_id'],
            'assigned_to' => auth()->user()->isAdmin() ? ($validated['assigned_to'] ?? null) : $lead->assigned_to,
        ]);

        $lead->phones()->delete();
        foreach ($this->normalizeLines($validated['phones'] ?? []) as $phone) {
            $lead->phones()->create(['phone' => $phone]);
        }
        $lead->emails()->delete();
        foreach ($this->normalizeLines($validated['emails'] ?? []) as $email) {
            $lead->emails()->create(['email' => $email]);
        }

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

            $activeLeadId = Lead::query()
                ->where('assigned_to', auth()->id())
                ->where('status_id', $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG))
                ->value('id');

            if ($activeLeadId && (int) $activeLeadId !== (int) $lead->id) {
                abort(403, 'Finish your active lead before opening another lead.');
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
        $address = $this->getCsvColumn($data, ['address', 'street_address', 'street']);
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

    /** @return array<int, string> */
    private function csvPhonesFromData(array $data): array
    {
        return array_values(array_filter([
            $this->getCsvColumn($data, ['phone', 'phones', 'phone1']),
            $this->getCsvColumn($data, ['phone2']),
            $this->getCsvColumn($data, ['phone3']),
        ], fn ($value) => $value !== ''));
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
            ->count();
    }

    private function agentHistoryLimit(): int
    {
        $fallback = (int) env('AGENT_HISTORY_LIMIT', 50);
        $configured = (int) (Setting::get('agent_history_limit', (string) $fallback) ?? $fallback);

        return max(1, $configured);
    }

    private function nextAvailableLead(Request $request): ?Lead
    {
        $newStatusId = $this->statusIdBySlug(self::ACTIVE_STATUS_SLUG);
        $skipped = $request->session()->get('agent_skipped_lead_ids', []);

        $query = Lead::with('status')
            ->whereNull('assigned_to')
            ->where('status_id', $newStatusId)
            ->where('is_dnc', false)
            ->orderBy('id');

        if (! empty($skipped)) {
            $query->whereNotIn('id', $skipped);
        }

        $lead = $query->first();

        if (! $lead && ! empty($skipped)) {
            $request->session()->forget('agent_skipped_lead_ids');
            $lead = Lead::with('status')
                ->whereNull('assigned_to')
                ->where('status_id', $newStatusId)
                ->where('is_dnc', false)
                ->orderBy('id')
                ->first();
        }

        return $lead;
    }
}
