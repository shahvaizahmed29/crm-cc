<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
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

        foreach ($statuses as $data) {
            $status = Status::firstOrCreate(['slug' => $data['slug']], $data);
            $status->roles()->syncWithoutDetaching([$adminRole->id, $agentRole->id]);
        }
    }
}
