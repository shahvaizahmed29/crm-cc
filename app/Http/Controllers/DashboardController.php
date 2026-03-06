<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Setting;
use App\Models\Status;
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

        $stats = [
            'monthly_leads' => Lead::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'daily_leads' => Lead::whereDate('created_at', now()->toDateString())->count(),
            'weekly_leads' => Lead::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'last_month' => Lead::whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->count(),
        ];

        if ($user->isAgent()) {
            $stats['monthly_leads'] = Lead::where('assigned_to', $user->id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
            $stats['daily_leads'] = Lead::where('assigned_to', $user->id)->whereDate('created_at', now()->toDateString())->count();
            $stats['weekly_leads'] = Lead::where('assigned_to', $user->id)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
            $stats['last_month'] = Lead::where('assigned_to', $user->id)->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->count();
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
