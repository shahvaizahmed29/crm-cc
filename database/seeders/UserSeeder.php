<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $agentRole = Role::where('slug', 'agent')->first();
        $subAgentRole = Role::where('slug', 'sub_agent')->first();

        if (! $adminRole || ! $agentRole || ! $subAgentRole) {
            $this->command->warn('Run RoleSeeder first.');
            return;
        }

        $definitions = [
            [
                'role_id' => $adminRole->id,
                'state' => [
                    'name' => 'Admin',
                    'last_name' => 'User',
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'password' => Hash::make('password'),
                ],
            ],
            [
                'role_id' => $agentRole->id,
                'state' => [
                    'name' => 'Agent',
                    'last_name' => 'Fifty',
                    'username' => 'agent50',
                    'email' => 'agent50@example.com',
                    'password' => Hash::make('password'),
                ],
            ],
            [
                'role_id' => $agentRole->id,
                'state' => [
                    'name' => 'Agent',
                    'last_name' => 'Ten',
                    'username' => 'agent10',
                    'email' => 'agent10@example.com',
                    'password' => Hash::make('password'),
                ],
            ],
            [
                'role_id' => $agentRole->id,
                'state' => [
                    'name' => 'Agent',
                    'last_name' => 'Zero',
                    'username' => 'agent0',
                    'email' => 'agent0@example.com',
                    'password' => Hash::make('password'),
                ],
            ],
            [
                'role_id' => $subAgentRole->id,
                'state' => [
                    'name' => 'Sub',
                    'last_name' => 'Agent',
                    'username' => 'subagent1',
                    'email' => 'subagent1@example.com',
                    'password' => Hash::make('password'),
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $attributes = User::factory()->state($definition['state'])->raw();
            $user = User::updateOrCreate(
                ['email' => $attributes['email']],
                $attributes
            );
            $user->roles()->syncWithoutDetaching([$definition['role_id']]);
        }
    }
}
