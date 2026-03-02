<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIpWhitelisted
{
    /**
     * Path prefix for the recovery route (always allowed so admins can restore access).
     */
    private const RECOVERY_PATH = 'ip-whitelist-recovery';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::RECOVERY_PATH) || $request->is(self::RECOVERY_PATH . '/*')) {
            return $next($request);
        }

        $whitelist = Setting::getIpWhitelistCached();

        if ($whitelist === []) {
            return $next($request);
        }

        $whitelist = array_map('trim', $whitelist);
        $whitelist = array_filter($whitelist, fn (string $ip) => $ip !== '');
        $clientIp = $request->ip();

        if ($clientIp === null) {
            abort(403, 'Access denied by IP whitelist.');
        }

        if (in_array($clientIp, $whitelist, true)) {
            return $next($request);
        }

        abort(403, 'Your IP address is not whitelisted. Use the IP whitelist recovery URL to add it.');
    }
}
