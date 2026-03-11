<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\View\View;
use LaravelDaily\LaravelCharts\Classes\LaravelChart;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $leadsQuery = Lead::with(['status', 'assignedTo']);
        $newStatusId = Status::where('slug', 'new')->value('id');

        if ($user->isAgent()) {
            $leadsQuery->where('assigned_to', $user->id);
            $leadsQuery->whereIn('status_id', $this->holdingStatusIds());
        } else {
            // Admin recent activities should show worked leads only.
            $leadsQuery->whereNotNull('assigned_to');
            if ($newStatusId) {
                $leadsQuery->where('status_id', '!=', $newStatusId);
            }
        }

        $recentLeads = $leadsQuery->latest('updated_at')->limit(10)->get();

        $recentNotifications = null;
        $topAgentsLeaderboard = null;
        if ($user->isAdmin()) {
            $recentNotifications = CrmNotification::query()
                ->where('target_user_id', $user->id)
                ->orderByDesc('notify_at')
                ->limit(5)
                ->get(['id', 'type', 'title', 'message', 'action_url', 'notify_at', 'read_at']);

            $topAgentsLeaderboard = $this->buildTopAgentsLeaderboard(10);
        }

        $now = Carbon::now(app_timezone());
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd = $now->copy()->endOfDay()->utc();
        $weekStart = $now->copy()->startOfWeek()->utc();
        $weekEnd = $now->copy()->endOfWeek()->utc();
        $monthStart = $now->copy()->startOfMonth()->utc();
        $monthEnd = $now->copy()->endOfMonth()->utc();
        $lastMonth = $now->copy()->subMonth();
        $lastMonthStart = $lastMonth->copy()->startOfMonth()->utc();
        $lastMonthEnd = $lastMonth->copy()->endOfMonth()->utc();

        $stats = [
            'monthly_leads' => Lead::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'daily_leads' => Lead::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'weekly_leads' => Lead::whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'last_month' => Lead::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count(),
        ];

        if ($user->isAgent()) {
            $stats['monthly_leads'] = Lead::where('assigned_to', $user->id)->whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $stats['daily_leads'] = Lead::where('assigned_to', $user->id)->whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $stats['weekly_leads'] = Lead::where('assigned_to', $user->id)->whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $stats['last_month'] = Lead::where('assigned_to', $user->id)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        }

        $newLeadsCount = null;
        if ($user->isAdmin()) {
            $newLeadsCount = Lead::query()
                ->where(function ($q) use ($newStatusId): void {
                    $q->where('status_id', $newStatusId)->orWhereNull('assigned_to');
                })
                ->count();
        }

        $leadsCountByStatus = null;
        if ($user->isAgent()) {
            $countsByStatusId = Lead::query()
                ->where('assigned_to', $user->id)
                ->selectRaw('status_id, count(*) as lead_count')
                ->groupBy('status_id')
                ->pluck('lead_count', 'status_id')
                ->all();

            $leadsCountByStatus = Status::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Status $status) => [
                    'name' => $status->name,
                    'slug' => $status->slug,
                    'count' => (int) ($countsByStatusId[$status->id] ?? 0),
                ])
                ->values()
                ->all();
        }

        $statusChart = $this->buildLeadsStatusPieChart($user, request('leads_chart_period', 'month'));

        return view('dashboard', [
            'recentLeads' => $recentLeads,
            'recentNotifications' => $recentNotifications,
            'topAgentsLeaderboard' => $topAgentsLeaderboard,
            'stats' => $stats,
            'newLeadsCount' => $newLeadsCount,
            'leadsCountByStatus' => $leadsCountByStatus,
            'statusChart' => $statusChart,
        ]);
    }

    /**
     * Top agents leaderboard for current month (approved count, then revenue). Admin only.
     *
     * @return \Illuminate\Support\Collection<int, array{agent: User, approved: int, total: int, revenue: float, conversion_rate: float}>
     */
    private function buildTopAgentsLeaderboard(int $limit = 10)
    {
        $now = Carbon::now(app_timezone());
        $start = $now->copy()->startOfMonth()->utc();
        $end = $now->copy()->endOfMonth()->utc();
        $statusIds = Status::query()->get(['id', 'slug'])->pluck('id', 'slug')->mapWithKeys(fn ($id, $slug) => [strtolower((string) $slug) => (int) $id])->all();
        $submittedStatusId = $statusIds['submitted'] ?? null;
        $dropStatusId = $statusIds['drop'] ?? null;
        $callbackStatusId = $statusIds['callback'] ?? null;
        $maxoutStatusId = $statusIds['maxout'] ?? null;

        $agents = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as approved_count' => fn ($q) => $q->whereBetween('updated_at', [$start, $end])->when($submittedStatusId, fn ($qq) => $qq->where('status_id', $submittedStatusId)),
                'assignedLeads as cancel_count' => fn ($q) => $q->whereBetween('updated_at', [$start, $end])->when($dropStatusId, fn ($qq) => $qq->where('status_id', $dropStatusId)),
                'assignedLeads as pending_count' => fn ($q) => $q->whereBetween('updated_at', [$start, $end])->when($callbackStatusId, fn ($qq) => $qq->where('status_id', $callbackStatusId)),
                'assignedLeads as not_qualified_count' => fn ($q) => $q->whereBetween('updated_at', [$start, $end])->when($maxoutStatusId, fn ($qq) => $qq->where('status_id', $maxoutStatusId)),
            ])
            ->withSum([
                'assignedLeads as approved_revenue_sum' => fn ($q) => $q->whereBetween('updated_at', [$start, $end])->when($submittedStatusId, fn ($qq) => $qq->where('status_id', $submittedStatusId)),
            ], 'approx_debt')
            ->orderBy('name')
            ->get();

        return $agents
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
            ->take($limit);
    }

    /** @return array<int> */
    private function holdingStatusIds(): array
    {
        $slugs = Setting::getJsonArray('holding_status_slugs', []);
        if ($slugs === []) {
            return [];
        }

        return Status::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Pie chart of leads by status (excludes "new" and statuses with 0 leads). Uses LaravelDaily/laravel-charts (Chart.js).
     * Time filter: today, week, last_7_days, month, year (by leads created_at in app timezone).
     */
    private function buildLeadsStatusPieChart($user, string $period = 'month'): LaravelChart
    {
        $newStatusId = Status::where('slug', 'new')->value('id');
        $whereRaw = $newStatusId
            ? 'leads.status_id != '.(int) $newStatusId
            : '1=1';
        if ($user->isAgent()) {
            $whereRaw .= ' AND leads.assigned_to = '.(int) $user->id;
        }

        $now = Carbon::now(app_timezone());
        $rangeStart = null;
        $rangeEnd = null;
        switch ($period) {
            case 'today':
                $rangeStart = $now->copy()->startOfDay()->utc();
                $rangeEnd = $now->copy()->endOfDay()->utc();
                break;
            case 'week':
                $rangeStart = $now->copy()->startOfWeek()->utc();
                $rangeEnd = $now->copy()->endOfWeek()->utc();
                break;
            case 'last_7_days':
                $rangeStart = $now->copy()->subDays(6)->startOfDay()->utc();
                $rangeEnd = $now->copy()->endOfDay()->utc();
                break;
            case 'month':
                $rangeStart = $now->copy()->startOfMonth()->utc();
                $rangeEnd = $now->copy()->endOfMonth()->utc();
                break;
            case 'year':
                $rangeStart = $now->copy()->startOfYear()->utc();
                $rangeEnd = $now->copy()->endOfYear()->utc();
                break;
            default:
                $rangeStart = $now->copy()->startOfMonth()->utc();
                $rangeEnd = $now->copy()->endOfMonth()->utc();
        }

        $chartOptions = [
            'chart_title' => 'Leads by status',
            'report_type' => 'group_by_relationship',
            'model' => Lead::class,
            'relationship_name' => 'status',
            'group_by_field' => 'name',
            'aggregate_function' => 'count',
            'chart_type' => 'pie',
            'where_raw' => $whereRaw,
            'filter_field' => 'created_at',
            'range_date_start' => $rangeStart->format('Y-m-d H:i:s'),
            'range_date_end' => $rangeEnd->format('Y-m-d H:i:s'),
            'chart_height' => '260px',
        ];

        return new LaravelChart($chartOptions);
    }
}
