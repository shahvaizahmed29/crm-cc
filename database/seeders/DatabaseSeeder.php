<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,      // roles (admin, agent)
            StatusSeeder::class,    // statuses + status_role (which roles can use which statuses)
            UserSeeder::class,      // users + role_user (admin + agents with roles)
            LeadSeeder::class,      // sample leads (with status_id, assigned_to, phones, emails)
            SessionTimeSeeder::class, // sample session_times (attendance) for agents
            SettingsSeeder::class,  // settings (agent_history_limit, holding_status_slugs)
        ]);
    }
}
