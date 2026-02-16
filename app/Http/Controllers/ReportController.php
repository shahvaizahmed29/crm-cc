<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function sales(Request $request): View
    {
        $this->abortUnlessAdmin();

        [$start, $end, $monthLabel] = $this->resolveMonthWindow($request);
        $statusIds = $this->statusIdsBySlug();

        $submittedStatusId = $statusIds['submitted'] ?? null;
        $dropStatusId = $statusIds['drop'] ?? null;
        $callbackStatusId = $statusIds['callback'] ?? null;
        $maxoutStatusId = $statusIds['maxout'] ?? null;

        $agents = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as approved_count' => fn ($q) => $q
                    ->whereBetween('updated_at', [$start, $end])
                    ->when($submittedStatusId, fn ($qq) => $qq->where('status_id', $submittedStatusId)),
                'assignedLeads as cancel_count' => fn ($q) => $q
                    ->whereBetween('updated_at', [$start, $end])
                    ->when($dropStatusId, fn ($qq) => $qq->where('status_id', $dropStatusId)),
                'assignedLeads as pending_count' => fn ($q) => $q
                    ->whereBetween('updated_at', [$start, $end])
                    ->when($callbackStatusId, fn ($qq) => $qq->where('status_id', $callbackStatusId)),
                'assignedLeads as not_qualified_count' => fn ($q) => $q
                    ->whereBetween('updated_at', [$start, $end])
                    ->when($maxoutStatusId, fn ($qq) => $qq->where('status_id', $maxoutStatusId)),
            ])
            ->withSum([
                'assignedLeads as approved_revenue_sum' => fn ($q) => $q
                    ->whereBetween('updated_at', [$start, $end])
                    ->when($submittedStatusId, fn ($qq) => $qq->where('status_id', $submittedStatusId)),
            ], 'approx_debt')
            ->orderBy('name')
            ->get();

        $leaderboard = $agents
            ->map(function (User $agent): array {
                $approved = (int) $agent->approved_count;
                $cancel = (int) $agent->cancel_count;
                $pending = (int) $agent->pending_count;
                $notQualified = (int) $agent->not_qualified_count;
                $total = $approved + $cancel + $pending + $notQualified;
                $revenue = (float) ($agent->approved_revenue_sum ?? 0);
                $conversionRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0.0;

                return [
                    'agent' => $agent,
                    'approved' => $approved,
                    'total' => $total,
                    'revenue' => $revenue,
                    'conversion_rate' => $conversionRate,
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sort(function (array $a, array $b): int {
                $approvedCompare = $b['approved'] <=> $a['approved'];
                if ($approvedCompare !== 0) {
                    return $approvedCompare;
                }

                return $b['revenue'] <=> $a['revenue'];
            })
            ->values()
            ->take(10);

        return view('reports.sales', [
            'agents' => $agents,
            'leaderboard' => $leaderboard,
            'monthLabel' => $monthLabel,
            'monthValue' => $start->format('Y-m'),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveMonthWindow(Request $request): array
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [$start, $end, $start->format('F Y')];
    }

    /**
     * @return array<string, int>
     */
    private function statusIdsBySlug(): array
    {
        return Status::query()
            ->get(['id', 'slug'])
            ->pluck('id', 'slug')
            ->mapWithKeys(fn ($id, $slug) => [strtolower((string) $slug) => (int) $id])
            ->all();
    }

    private function abortUnlessAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}
