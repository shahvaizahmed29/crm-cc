<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const DEFAULT_HOLDING_SLUGS = [
        'need-to-reconnect',
        'callback',
        'maxout',
        'drop',
    ];

    /**
     * Timezone options for the dropdown: Carbon/PHP valid identifiers with human-readable labels.
     * Format: "City / Region (+5:00)" e.g. "Karachi / Asia (+5:00)".
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();
        $options = [];

        foreach ($identifiers as $id) {
            $zone = new \DateTimeZone($id);
            $dt = new \DateTime('now', $zone);
            $offsetSeconds = $dt->getOffset();
            $hours = (int) floor($offsetSeconds / 3600);
            $minutes = (int) abs(($offsetSeconds % 3600) / 60);
            $offsetStr = sprintf('%+03d:%02d', $hours, $minutes);

            $parts = explode('/', $id);
            $region = $parts[0] ?? $id;
            $city = end($parts);
            if ($city !== $region && $city !== $id) {
                $label = $city . ' / ' . $region . ' (' . $offsetStr . ')';
            } else {
                $label = $id . ' (' . $offsetStr . ')';
            }

            $options[] = ['value' => $id, 'label' => $label, 'offset' => $offsetSeconds];
        }

        usort($options, static function (array $a, array $b): int {
            if ($a['offset'] !== $b['offset']) {
                return $a['offset'] <=> $b['offset'];
            }
            return strcasecmp($a['label'], $b['label']);
        });

        return array_map(static fn (array $o): array => ['value' => $o['value'], 'label' => $o['label']], $options);
    }

    public function index(): View
    {
        $agentHistoryLimit = Setting::get('agent_history_limit', '50');
        $roundRobinLeadsBeforeSkippedReshown = max(1, min(5000, (int) (Setting::get('round_robin_leads_before_skipped_reshown', '5') ?? 5)));
        $holdingStatusSlugs = Setting::getJsonArray('holding_status_slugs', self::DEFAULT_HOLDING_SLUGS);
        $crSoundNotificationsEnabled = Setting::get('cr_sound_notifications_enabled', '1') === '1';
        $callbackReminderMinutes = (int) (Setting::get('callback_reminder_minutes', '15') ?? '15');
        $newLeadsNotificationThreshold = Setting::get('new_leads_notification_threshold', '');
        $appTimezone = Setting::get('app_timezone', config('app.timezone')) ?: config('app.timezone');
        $ipWhitelist = Setting::getIpWhitelistCached();
        $statuses = Status::orderBy('name')->get(['id', 'name', 'slug']);
        $timezoneOptions = $this->timezoneOptions();

        return view('settings.index', [
            'agentHistoryLimit' => $agentHistoryLimit,
            'roundRobinLeadsBeforeSkippedReshown' => $roundRobinLeadsBeforeSkippedReshown,
            'holdingStatusSlugs' => $holdingStatusSlugs,
            'crSoundNotificationsEnabled' => $crSoundNotificationsEnabled,
            'callbackReminderMinutes' => $callbackReminderMinutes,
            'newLeadsNotificationThreshold' => $newLeadsNotificationThreshold,
            'appTimezone' => $appTimezone,
            'ipWhitelist' => $ipWhitelist,
            'statuses' => $statuses,
            'timezoneOptions' => $timezoneOptions,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agent_history_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'round_robin_leads_before_skipped_reshown' => ['required', 'integer', 'min:1', 'max:5000'],
            'holding_status_slugs' => ['nullable', 'array'],
            'holding_status_slugs.*' => ['string', 'exists:statuses,slug'],
            'cr_sound_notifications_enabled' => ['required', 'boolean'],
            'callback_reminder_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'new_leads_notification_threshold' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'app_timezone' => ['required', 'string', 'timezone'],
            'ip_whitelist' => ['nullable', 'string'],
        ]);

        Setting::put('agent_history_limit', (string) $validated['agent_history_limit']);
        Setting::put('round_robin_leads_before_skipped_reshown', (string) max(1, min(5000, $validated['round_robin_leads_before_skipped_reshown'])));

        $slugs = array_values($validated['holding_status_slugs'] ?? []);
        Setting::putJsonArray('holding_status_slugs', $slugs);
        Setting::put('cr_sound_notifications_enabled', $validated['cr_sound_notifications_enabled'] ? '1' : '0');
        Setting::put('callback_reminder_minutes', (string) $validated['callback_reminder_minutes']);
        $threshold = array_key_exists('new_leads_notification_threshold', $validated) && $validated['new_leads_notification_threshold'] !== null && $validated['new_leads_notification_threshold'] !== ''
            ? (string) $validated['new_leads_notification_threshold']
            : '';
        Setting::put('new_leads_notification_threshold', $threshold);

        Setting::put('app_timezone', $validated['app_timezone']);

        $ipRaw = $validated['ip_whitelist'] ?? '';
        $ipList = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $ipRaw)),
            fn (string $ip) => $ip !== ''
        )));
        Setting::putJsonArray('ip_whitelist', $ipList);
        Setting::clearIpWhitelistCache();

        Artisan::call('config:clear');

        return redirect()->route('settings.index')->with('success', 'Settings saved.');
    }
}
