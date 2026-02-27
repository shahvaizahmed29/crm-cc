<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const DEFAULT_HOLDING_SLUGS = [
        'need-to-reconnect',
        'callback',
        'maxout',
        'drop',
    ];

    public function index(): View
    {
        $agentHistoryLimit = Setting::get('agent_history_limit', '50');
        $holdingStatusSlugs = Setting::getJsonArray('holding_status_slugs', self::DEFAULT_HOLDING_SLUGS);
        $crSoundNotificationsEnabled = Setting::get('cr_sound_notifications_enabled', '1') === '1';
        $callbackReminderMinutes = (int) (Setting::get('callback_reminder_minutes', '15') ?? '15');
        $statuses = Status::orderBy('name')->get(['id', 'name', 'slug']);

        return view('settings.index', [
            'agentHistoryLimit' => $agentHistoryLimit,
            'holdingStatusSlugs' => $holdingStatusSlugs,
            'crSoundNotificationsEnabled' => $crSoundNotificationsEnabled,
            'callbackReminderMinutes' => $callbackReminderMinutes,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agent_history_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'holding_status_slugs' => ['nullable', 'array'],
            'holding_status_slugs.*' => ['string', 'exists:statuses,slug'],
            'cr_sound_notifications_enabled' => ['required', 'boolean'],
            'callback_reminder_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        Setting::put('agent_history_limit', (string) $validated['agent_history_limit']);

        $slugs = array_values($validated['holding_status_slugs'] ?? []);
        Setting::putJsonArray('holding_status_slugs', $slugs);
        Setting::put('cr_sound_notifications_enabled', $validated['cr_sound_notifications_enabled'] ? '1' : '0');
        Setting::put('callback_reminder_minutes', (string) $validated['callback_reminder_minutes']);

        return redirect()->route('settings.index')->with('success', 'Settings saved.');
    }
}
