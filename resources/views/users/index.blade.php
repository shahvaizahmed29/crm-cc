@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Add New User</h1>
        <p class="mt-1 text-sm text-slate-600">Enter all possible information.</p>
    </div>
    <a href="{{ route('users.create') }}" class="inline-flex justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Add User</a>
</div>

<div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
    <div class="lg:col-span-1">
        <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
            <p class="text-sm text-slate-600">Create new users and assign them a role (Administrator or Agent). More roles can be added later (e.g. dialer-agent, closer-agent).</p>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Username</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Usergroup</th>
                        <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($users as $user)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ $user->name }} {{ $user->last_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $user->username ?? $user->email }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $user->roles->first()?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <a href="{{ route('users.edit', $user) }}" class="rounded bg-emerald-600 px-2 py-1 font-medium text-white hover:bg-emerald-500">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No users yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @if($users->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
