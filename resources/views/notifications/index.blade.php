@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="flex items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Notifications</h1>
        <p class="mt-1 text-sm text-slate-600">Your in-app alerts and reminders.</p>
    </div>
    <form action="{{ route('notifications.read-all') }}" method="POST">
        @csrf
        <button type="submit" class="inline-flex justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Mark all as read
        </button>
    </form>
</div>

<div class="mt-5 space-y-3">
    @forelse($notifications as $notification)
        <div class="rounded-xl border {{ $notification->read_at ? 'border-slate-200 bg-white' : 'border-sky-200 bg-sky-50/50' }} p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $notification->title }}</p>
                    @if($notification->message)
                        <p class="mt-1 text-sm text-slate-700">{{ $notification->message }}</p>
                    @endif
                    <p class="mt-1 text-xs text-slate-500">
                        Type: {{ $notification->type }} • Notify at: {{ $notification->notify_at?->format('Y-m-d H:i:s') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if($notification->action_url)
                        <a href="{{ route('notifications.open', $notification) }}"
                           class="inline-flex justify-center rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                            Open
                        </a>
                    @endif
                    @if(! $notification->read_at)
                        <form action="{{ route('notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Mark read
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
            No notifications yet.
        </div>
    @endforelse
</div>

@if($notifications->hasPages())
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
@endif
@endsection
