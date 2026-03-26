<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;

/**
 * Sends admin notifications when a lead is "new" or unassigned (same rules as legacy LeadController logic).
 */
final class AdminNewLeadNotifier
{
    private const NEW_STATUS_SLUG = 'new';

    public function notifyIfApplicable(Lead $lead): void
    {
        if (! $this->isLeadNewOrUnassigned($lead)) {
            return;
        }

        $thresholdRaw = Setting::get('new_leads_notification_threshold', '');
        if ($thresholdRaw !== '' && $thresholdRaw !== null) {
            $threshold = (int) $thresholdRaw;
            $currentNewLeadsCount = Lead::query()->newStatusOnly()->count();
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

    private function isLeadNewOrUnassigned(Lead $lead): bool
    {
        $newStatusId = Status::where('slug', self::NEW_STATUS_SLUG)->value('id');

        return $newStatusId !== null
            && ((int) $lead->status_id === (int) $newStatusId || $lead->assigned_to === null);
    }
}
