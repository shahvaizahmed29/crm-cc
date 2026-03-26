<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        ]);
    }

    public function store(Request $request, LeadTxtExportParser $parser): RedirectResponse
    {
        $request->validate([
            'deal_sheet' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('deal_sheet');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'txt') {
            return back()->withErrors([
                'deal_sheet' => 'Upload a .txt file in the same format as the lead TXT export (Download TXT from a lead or leads list).',
            ]);
        }

        $statusId = Status::where('slug', self::STATUS_SLUG)->value('id');
        if ($statusId === null) {
            return back()->withErrors(['deal_sheet' => 'Status "Deal sheet uploaded" is missing. Run migrations and seeders.']);
        }

        $raw = (string) file_get_contents($file->getRealPath() ?: '');
        $blocks = $parser->parseFile($raw);
        if ($blocks === []) {
            return back()->withErrors([
                'deal_sheet' => 'Could not parse the file. It must match the TXT format produced by the admin "Download TXT" / single-lead export.',
            ]);
        }

        $path = $file->store('deal-sheets', 'local');
        $adminId = auth()->id();
        $created = 0;

        foreach ($blocks as $data) {
            DB::transaction(function () use ($data, $path, $statusId, $adminId, &$created): void {
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
                    'status_id' => $statusId,
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

                $created++;
            });
        }

        return redirect()
            ->route('deal-sheets.index')
            ->with('success', $created === 1
                ? 'Deal sheet imported: 1 lead created.'
                : "Deal sheet imported: {$created} leads created.");
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
