<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->visible()
            ->orderByRaw('read_at IS NULL DESC')
            ->latest('notify_at')
            ->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $query = CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->visible()
            ->unread();

        $typePrefix = trim((string) $request->query('type_prefix'));
        if ($typePrefix !== '') {
            $query->where('type', 'like', $typePrefix . '.%');
        }

        return response()->json([
            'count' => $query->count(),
        ]);
    }

    /**
     * Recent notifications for the navbar dropdown. Count = unread only (visible by time); items = all recent notifications (read, unread, and scheduled) so old/scheduled ones are always visible like Facebook.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min(25, max(10, (int) $request->query('limit', 15)));

        $notifications = CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('notify_at')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'message', 'action_url', 'notify_at', 'read_at']);

        $count = CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->visible()
            ->unread()
            ->count();

        return response()->json([
            'count' => $count,
            'items' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message ? Str::limit($n->message, 80) : null,
                'action_url' => $n->action_url,
                'open_url' => route('notifications.open', $n),
                'notify_at' => $n->notify_at?->toIso8601String(),
                'notify_at_human' => $n->notify_at?->diffForHumans(),
                'read_at' => $n->read_at?->toIso8601String(),
            ]),
        ]);
    }

    public function open(CrmNotification $crmNotification): RedirectResponse
    {
        $this->authorizeNotification($crmNotification);

        if ($crmNotification->read_at === null) {
            $crmNotification->update(['read_at' => now()]);
        }

        if ($crmNotification->action_url && $this->userCanAccessActionUrl($crmNotification)) {
            return redirect()->to($crmNotification->action_url);
        }

        if ($crmNotification->action_url && ! $this->userCanAccessActionUrl($crmNotification)) {
            return redirect()->route('notifications.index')
                ->with('error', 'You no longer have access to that resource.');
        }

        return redirect()->route('notifications.index');
    }

    public function markRead(CrmNotification $crmNotification): RedirectResponse
    {
        $this->authorizeNotification($crmNotification);

        if ($crmNotification->read_at === null) {
            $crmNotification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(): RedirectResponse
    {
        CrmNotification::query()
            ->where('target_user_id', auth()->id())
            ->visible()
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    private function authorizeNotification(CrmNotification $crmNotification): void
    {
        if ((int) $crmNotification->target_user_id !== (int) auth()->id()) {
            abort(403);
        }
    }

    /**
     * Ensure the user still has permission to access the notification's target (e.g. lead).
     * Prevents redirecting to a lead the user can no longer view (e.g. DNC, reassigned).
     * For agents opening a callback notification, the lead's current status must be one of the holding status slugs.
     */
    private function userCanAccessActionUrl(CrmNotification $crmNotification): bool
    {
        if ($crmNotification->entity_type !== 'lead' || empty($crmNotification->entity_id)) {
            return true;
        }

        $lead = Lead::withoutTrashed()->with('status')->find($crmNotification->entity_id);
        if (! $lead) {
            return false;
        }

        if (auth()->user()->isAgent()) {
            if ($lead->is_dnc) {
                return false;
            }
            if ((int) $lead->assigned_to !== (int) auth()->id()) {
                return false;
            }

            // Callback notifications: agent may only open the lead if its status is still a holding status.
            if (str_starts_with((string) $crmNotification->type, 'callback.')) {
                $holdingSlugs = Setting::getJsonArray('holding_status_slugs', []);
                $currentSlug = $lead->status?->slug ?? '';
                if ($currentSlug === '' || ! in_array($currentSlug, $holdingSlugs, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
