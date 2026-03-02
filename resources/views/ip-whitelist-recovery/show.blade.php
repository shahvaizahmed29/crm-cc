<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>IP Whitelist Recovery — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 py-12 px-4 font-sans antialiased">
    <div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-slate-900">IP Whitelist Recovery</h1>
        <p class="mt-1 text-sm text-slate-600">Add or remove IP addresses that can access the application. One IP per line. Leave empty to allow all IPs.</p>

        @if(session('success'))
            <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</p>
        @endif

        @if($errors->any())
            <ul class="mt-3 list-inside list-disc rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <p class="mt-3 text-sm font-medium text-slate-700">Your current IP: <code class="rounded bg-slate-200 px-1.5 py-0.5">{{ $currentIp ?: 'Unknown' }}</code></p>

        <form action="{{ route('ip-whitelist.recovery.update', ['token' => $token]) }}" method="POST" class="mt-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="ip_whitelist" class="block text-sm font-medium text-slate-700">Whitelisted IPs</label>
            <textarea
                name="ip_whitelist"
                id="ip_whitelist"
                rows="8"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                placeholder="127.0.0.1&#10;192.168.1.1"
            >{{ old('ip_whitelist', implode("\n", $whitelist)) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">One IP per line. Comma-separated is also accepted.</p>
            <button type="submit" class="mt-4 rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Save IP whitelist</button>
        </form>

        <p class="mt-6 border-t border-slate-200 pt-4 text-xs text-slate-500">Keep this URL and token secret. Anyone with the token can change the whitelist. Add your IP above and save to regain access if locked out.</p>
    </div>
</body>
</html>
