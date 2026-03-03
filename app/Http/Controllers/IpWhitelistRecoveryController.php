<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class IpWhitelistRecoveryController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        $expected = config('services.ip_whitelist_recovery.token');

        if ($expected === null || $expected === '' || $token !== $expected) {
            abort(403, 'Invalid or missing recovery token.');
        }

        $whitelist = Setting::getIpWhitelistCached();
        $currentIp = $request->ip() ?? '';

        return view('ip-whitelist-recovery.show', [
            'whitelist' => $whitelist,
            'currentIp' => $currentIp,
            'token' => $token,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $token = $request->query('token') ?? $request->input('token');
        $expected = config('services.ip_whitelist_recovery.token');

        if ($expected === null || $expected === '' || $token !== $expected) {
            abort(403, 'Invalid or missing recovery token.');
        }

        $validated = $request->validate([
            'ip_whitelist' => ['nullable', 'string'],
        ]);

        $raw = $validated['ip_whitelist'] ?? '';
        $ips = array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn (string $ip) => $ip !== ''
        );
        $ips = array_values(array_unique($ips));

        Setting::putJsonArray('ip_whitelist', $ips);
        Setting::clearIpWhitelistCache();

        Artisan::call('config:clear');

        return redirect()
            ->route('ip-whitelist.recovery.show', ['token' => $token])
            ->with('success', 'IP whitelist updated.');
    }
}
