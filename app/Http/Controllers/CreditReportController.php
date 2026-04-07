<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\CreditReport;
use App\Models\Lead;
use App\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CreditReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->abortUnlessAdmin();

        CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->where('type', 'like', 'cr.%')
            ->whereNull('read_at')
            ->where('notify_at', '<=', now())
            ->update(['read_at' => now()]);

        $status = (string) $request->query('status', 'pending');
        $allowed = [
            CreditReport::STATUS_PENDING,
            CreditReport::STATUS_RECHECK,
            CreditReport::STATUS_NOT_FOUND,
            CreditReport::STATUS_SENT,
            'all',
        ];
        if (! in_array($status, $allowed, true)) {
            $status = CreditReport::STATUS_PENDING;
        }

        $query = CreditReport::with(['lead', 'requestedBy', 'processedBy'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $reports = $query->paginate(20)->withQueryString();

        return view('credit-reports.index', compact('reports', 'status'));
    }

    public function request(Lead $lead): RedirectResponse
    {
        $this->authorizeLeadAccess($lead);

        if (! $this->leadHasCrMinimumData($lead)) {
            return redirect()->route('leads.edit', $lead)->with('error', 'Phone, first name, last name, and address are required before CR request.');
        }

        $existing = $lead->creditReports()->latest()->first();
        if ($existing && in_array($existing->status, [CreditReport::STATUS_PENDING, CreditReport::STATUS_RECHECK], true)) {
            return redirect()->route('leads.edit', $lead)->with('error', 'CR already requested. Please wait.');
        }
        if ($existing && $existing->status === CreditReport::STATUS_NOT_FOUND) {
            return redirect()->route('leads.edit', $lead)->with('error', $existing->comment ?: 'CR not found previously. Use Re-check.');
        }
        if ($existing && $existing->status === CreditReport::STATUS_SENT && $existing->report_file_path && Storage::exists($existing->report_file_path)) {
            return redirect()->route('leads.edit', $lead)->with('success', 'CR already available. Use Get Report.');
        }

        $creditReport = CreditReport::create([
            'lead_id' => $lead->id,
            'phone_number' => $lead->phones->first()?->phone,
            'status' => CreditReport::STATUS_PENDING,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        $this->notifyAdminsForCr(
            type: 'cr.requested',
            title: 'New CR request',
            message: 'A credit report request was created for ' . $lead->fullName() . '.',
            lead: $lead,
            creditReport: $creditReport
        );

        return redirect()->route('leads.edit', $lead)->with('success', 'CR request sent.');
    }

    public function recheck(Lead $lead): RedirectResponse
    {
        $this->authorizeLeadAccess($lead);

        $existing = $lead->creditReports()->latest()->first();
        if (! $existing) {
            return redirect()->route('leads.edit', $lead)->with('error', 'No CR request found for this lead.');
        }
        if ($existing->status === CreditReport::STATUS_RECHECK) {
            return redirect()->route('leads.edit', $lead)->with('error', 'CR request is already in re-check.');
        }
        if ($existing->status === CreditReport::STATUS_PENDING) {
            return redirect()->route('leads.edit', $lead)->with('error', 'CR request is already pending.');
        }
        if ($existing->status !== CreditReport::STATUS_NOT_FOUND) {
            return redirect()->route('leads.edit', $lead)->with('error', 'Re-check is only available for Not Found reports.');
        }

        $existing->update([
            'status' => CreditReport::STATUS_RECHECK,
            'comment' => null,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        $this->notifyAdminsForCr(
            type: 'cr.recheck',
            title: 'CR re-check requested',
            message: 'A CR re-check was requested for ' . $lead->fullName() . '.',
            lead: $lead,
            creditReport: $existing
        );

        return redirect()->route('leads.edit', $lead)->with('success', 'CR re-check request sent.');
    }

    public function uploadResult(Request $request, CreditReport $creditReport): RedirectResponse
    {
        $this->abortUnlessAdmin();

        $validated = $request->validate([
            'status' => ['required', 'in:' . CreditReport::STATUS_SENT . ',' . CreditReport::STATUS_NOT_FOUND],
            'comment' => ['nullable', 'string'],
            'cr_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($validated['status'] === CreditReport::STATUS_SENT && ! $request->hasFile('cr_file')) {
            return back()->with('error', 'PDF file is required when status is sent.');
        }

        $filePath = $creditReport->report_file_path;
        if ($request->hasFile('cr_file')) {
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $filePath = $request->file('cr_file')->store('crreports');
        }

        $creditReport->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
            'report_file_path' => $filePath,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', 'CR updated successfully.');
    }

    public function destroy(CreditReport $creditReport): RedirectResponse
    {
        $this->abortUnlessAdmin();

        if ($creditReport->report_file_path && Storage::exists($creditReport->report_file_path)) {
            Storage::delete($creditReport->report_file_path);
        }

        $creditReport->delete();

        return back()->with('success', 'CR request deleted.');
    }

    public function download(CreditReport $creditReport): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeLeadAccess($creditReport->lead);

        if (! $creditReport->report_file_path || ! Storage::exists($creditReport->report_file_path)) {
            return back()->with('error', 'CR file not found.');
        }

        return response()->file(Storage::path($creditReport->report_file_path));
    }

    public function pendingCount(): JsonResponse
    {
        $this->abortUnlessAdmin();

        $pendingQuery = CreditReport::query()
            ->whereIn('status', [CreditReport::STATUS_PENDING, CreditReport::STATUS_RECHECK]);

        $count = (clone $pendingQuery)->count();
        $latestPendingId = (int) ((clone $pendingQuery)->max('id') ?? 0);

        return response()->json([
            'count' => $count,
            'latest_pending_id' => $latestPendingId,
        ]);
    }

    private function authorizeLeadAccess(Lead $lead): void
    {
        if (auth()->user()->isAdmin()) {
            return;
        }

        if (! auth()->user()->isStaffAgent()) {
            abort(403);
        }

        if (auth()->user()->isAgent() && $lead->is_dnc) {
            abort(403, 'DNC leads are not available to agents.');
        }

        if ((int) $lead->assigned_to !== (int) auth()->id()) {
            abort(403, 'You can only access leads assigned to you.');
        }

        if (auth()->user()->isSubAgent() && ! (bool) $lead->is_deal_sheet) {
            abort(403, 'Sub agents can only access deal sheet leads.');
        }
    }

    private function abortUnlessAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
    }

    private function leadHasCrMinimumData(Lead $lead): bool
    {
        $hasPhone = (bool) $lead->phones->first()?->phone;
        return $hasPhone && $lead->first_name !== '' && $lead->last_name !== '' && (string) $lead->address !== '';
    }

    private function notifyAdminsForCr(string $type, string $title, string $message, Lead $lead, CreditReport $creditReport): void
    {
        $adminIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->pluck('id');

        $notifyAt = now();

        foreach ($adminIds as $adminId) {
            CrmNotification::query()->create([
                'created_by' => auth()->id(),
                'target_user_id' => (int) $adminId,
                'type' => $type,
                'entity_type' => 'credit_report',
                'entity_id' => $creditReport->id,
                'title' => $title,
                'message' => $message,
                'action_url' => route('leads.edit', $lead),
                'notify_at' => $notifyAt,
                'sent_at' => $notifyAt,
                'status' => 'sent',
                'priority' => 'normal',
                'meta' => [
                    'lead_id' => $lead->id,
                    'credit_report_id' => $creditReport->id,
                    'requested_by' => auth()->id(),
                ],
            ]);
        }
    }
}
