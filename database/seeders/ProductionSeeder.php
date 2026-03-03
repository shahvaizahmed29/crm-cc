<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Minimum required data for production: roles, statuses, settings, and one admin.
     * Idempotent: safe to run multiple times; existing admin password is not overwritten.
     */
    public function run(): void
    {
        $this->seedRoles();
        $this->seedStatuses();
        $this->seedSettings();
        $this->seedAdmin();
    }

    private function seedRoles(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin'],
            ['name' => 'Agent', 'slug' => 'agent'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }

    private function seedStatuses(): void
    {
        $statuses = [
            ['name' => 'New', 'slug' => 'new'],
            ['name' => 'Callback', 'slug' => 'callback'],
            ['name' => 'Need To Reconnect', 'slug' => 'need-to-reconnect'],
            ['name' => 'Need To Unblock', 'slug' => 'need-to-unblock'],
            ['name' => 'Rework', 'slug' => 'rework'],
            ['name' => 'Refund', 'slug' => 'refund'],
            ['name' => 'Pre Auth', 'slug' => 'pre-auth'],
            ['name' => 'Payoff', 'slug' => 'payoff'],
            ['name' => 'Maxout', 'slug' => 'maxout'],
            ['name' => 'Drop', 'slug' => 'drop'],
            ['name' => 'Not Interested', 'slug' => 'not-interested'],
            ['name' => 'Submitted', 'slug' => 'submitted'],
        ];

        $adminRole = Role::where('slug', 'admin')->first();
        $agentRole = Role::where('slug', 'agent')->first();

        if (! $adminRole || ! $agentRole) {
            $this->command->error('Roles not found. Run seedRoles first.');
            return;
        }

        foreach ($statuses as $data) {
            $status = Status::firstOrCreate(['slug' => $data['slug']], $data);
            $status->roles()->syncWithoutDetaching([$adminRole->id, $agentRole->id]);
        }
    }

    private function seedSettings(): void
    {
        $defaults = [
            'agent_history_limit' => '50',
            'cr_sound_notifications_enabled' => '1',
            'callback_reminder_minutes' => '15',
            'holding_status_slugs' => json_encode([
                'need-to-reconnect',
                'callback',
                'maxout',
                'drop',
            ]),
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function seedAdmin(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        if (!$adminRole) {
            $this->command->error('Admin role not found.');
            return;
        }

        $email = 'jonny@bestccever.com';
        $password = 'jonn1950@cccrm@2026!!';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Nabeel',
                'last_name' => 'Admin',
                'username' => 'nabeel',
                'email' => $email,
                'password' => Hash::make($password),
            ]
        );

        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        if ($user->wasRecentlyCreated) {
            $this->command->info("Admin created: {$email}");
        } else {
            $this->command->info("Admin already exists: {$email} (password not changed).");
        }
    }
}
