<?php

namespace App\Console\Commands;

use App\Models\CrmNotification;
use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUnentertainedCallbacksCommand extends Command
{
    protected $signature = 'callbacks:notify-unentertained';

    protected $description = 'Notify admins when a lead with Callback status has not been updated 1 hour after the callback time.';

    public function handle(): int
    {
        $callbackStatusId = Status::where('slug', 'callback')->value('id');
        if ($callbackStatusId === null) {
            return self::SUCCESS;
        }

        $oneHourAgo = now()->subHour();

        $reminders = CrmNotification::query()
            ->where('type', 'callback.reminder')
            ->where('entity_type', 'lead')
            ->whereNotNull('entity_id')
            ->orderByDesc('created_at')
            ->get();

        $latestByLead = [];
        foreach ($reminders as $n) {
            $leadId = (int) $n->entity_id;
            if (! isset($latestByLead[$leadId])) {
                $latestByLead[$leadId] = $n;
            }
        }

        $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get();
        if ($admins->isEmpty()) {
            return self::SUCCESS;
        }

        $created = 0;
        foreach ($latestByLead as $leadId => $reminder) {
            $callbackAt = $reminder->meta['callback_at'] ?? null;
            if ($callbackAt === null || $callbackAt === '') {
                continue;
            }

            try {
                $at = Carbon::parse($callbackAt);
            } catch (\Throwable $e) {
                continue;
            }

            if ($at->copy()->addHour()->isFuture()) {
                continue;
            }

            $lead = Lead::withoutTrashed()->with('status', 'assignedTo')->find($leadId);
            if (! $lead || (int) $lead->status_id !== (int) $callbackStatusId) {
                continue;
            }

            $alreadyNotified = CrmNotification::query()
                ->where('type', 'callback.not_entertained')
                ->where('entity_type', 'lead')
                ->where('entity_id', $leadId)
                ->where('meta->callback_notification_id', $reminder->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $assigneeName = $lead->assignedTo?->displayName() ?? 'Unassigned';
            $title = 'Lead not entertained by assignee';
            $message = sprintf(
                '%s (Lead #%d) had a callback at %s. Over 1 hour has passed with no status change or new callback. Assignee: %s.',
                $lead->fullName(),
                $lead->id,
                $at->format('M j, Y g:i A'),
                $assigneeName
            );
            $actionUrl = route('leads.edit', ['lead' => $lead->id]);

            foreach ($admins as $admin) {
                CrmNotification::create([
                    'created_by' => null,
                    'target_user_id' => $admin->id,
                    'type' => 'callback.not_entertained',
                    'entity_type' => 'lead',
                    'entity_id' => $lead->id,
                    'title' => $title,
                    'message' => $message,
                    'action_url' => $actionUrl,
                    'notify_at' => now(),
                    'sent_at' => null,
                    'status' => 'sent',
                    'priority' => 'high',
                    'meta' => [
                        'lead_id' => $lead->id,
                        'callback_at' => $callbackAt,
                        'callback_notification_id' => $reminder->id,
                        'assignee_id' => $lead->assigned_to,
                    ],
                ]);
                $created++;
            }
        }

        if ($created > 0) {
            $this->info("Created {$created} admin notification(s) for unentertained callbacks.");
        }

        return self::SUCCESS;
    }
}
