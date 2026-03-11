<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\View\View;

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
        if ($user->isAdmin()) {
            $recentNotifications = CrmNotification::query()
                ->where('target_user_id', $user->id)
                ->orderByDesc('notify_at')
                ->limit(10)
                ->get(['id', 'type', 'title', 'message', 'action_url', 'notify_at', 'read_at']);
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

        return view('dashboard', [
            'recentLeads' => $recentLeads,
            'recentNotifications' => $recentNotifications,
            'stats' => $stats,
            'newLeadsCount' => $newLeadsCount,
            'leadsCountByStatus' => $leadsCountByStatus,
        ]);
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
}
