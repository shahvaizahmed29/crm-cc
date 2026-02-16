<?php

namespace Database\Seeders;

use App\Models\SessionTime;
use App\Models\User;
use Illuminate\Database\Seeder;

class SessionTimeSeeder extends Seeder
{
    public function run(): void
    {
        $agents = User::whereHas('roles', fn ($q) => $q->where('slug', 'agent'))->get();

        if ($agents->isEmpty()) {
            return;
        }

        foreach ($agents->take(2) as $user) {
            SessionTime::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'started_at' => now()->subHours(4)->startOfHour(),
                ],
                ['ended_at' => null]
            );
        }
    }
}
