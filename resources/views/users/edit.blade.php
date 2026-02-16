@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<x-page-header title="Edit User" :back-url="route('users.index')" back-text="Back to list" />

<form action="{{ route('users.update', $user) }}" method="POST" class="mt-6 max-w-xl space-y-4">
    @csrf
    @method('PUT')
    <x-form-field label="Name" for="name" :required="true">
        <x-input name="name" id="name" type="text" :value="old('name', $user->name)" required />
    </x-form-field>
    <x-form-field label="Last Name" for="last_name">
        <x-input name="last_name" id="last_name" type="text" :value="old('last_name', $user->last_name)" />
    </x-form-field>
    <x-form-field label="Username" for="username" :required="true">
        <x-input name="username" id="username" type="text" :value="old('username', $user->username)" required />
    </x-form-field>
    <x-form-field label="Email" for="email" :required="true">
        <x-input name="email" id="email" type="email" :value="old('email', $user->email)" required />
    </x-form-field>
    <x-form-field label="Usergroup" for="usergroup" :required="true">
        <x-select name="role_id" id="usergroup" :options="$roles->pluck('name', 'id')" :selected="old('role_id', $user->roles->first()?->id)" required />
    </x-form-field>
    <x-form-field label="New Password (leave blank to keep)" for="password">
        <x-input name="password" id="password" type="password" />
    </x-form-field>
    <x-form-field label="Confirm New Password" for="password_confirmation">
        <x-input name="password_confirmation" id="password_confirmation" type="password" />
    </x-form-field>
    <div>
        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Update User</button>
    </div>
</form>
@endsection
