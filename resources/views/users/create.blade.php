@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<x-page-header title="Add New User" :back-url="route('users.index')" back-text="Back to list" />
<p class="mt-1 text-sm text-slate-600">Enter all possible information.</p>

<form action="{{ route('users.store') }}" method="POST" class="mt-6 max-w-xl space-y-4">
    @csrf
    <x-form-field label="Name" for="name" :required="true">
        <x-input name="name" id="name" type="text" :value="old('name')" required />
    </x-form-field>
    <x-form-field label="Last Name" for="last_name">
        <x-input name="last_name" id="last_name" type="text" :value="old('last_name')" />
    </x-form-field>
    <x-form-field label="Username" for="username" :required="true">
        <x-input name="username" id="username" type="text" :value="old('username')" required />
    </x-form-field>
    <x-form-field label="Email" for="email" :required="true">
        <x-input name="email" id="email" type="email" :value="old('email')" required />
    </x-form-field>
    <x-form-field label="Usergroup" for="usergroup" :required="true">
        <x-select name="role_id" id="usergroup" :options="$roles->pluck('name', 'id')" :selected="old('role_id')" placeholder="— Select One —" required />
    </x-form-field>
    <x-form-field label="Password" for="password" :required="true">
        <x-input name="password" id="password" type="password" required />
    </x-form-field>
    <x-form-field label="Confirm Password" for="password_confirmation" :required="true">
        <x-input name="password_confirmation" id="password_confirmation" type="password" required />
    </x-form-field>
    <div>
        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Add User</button>
    </div>
</form>
@endsection
