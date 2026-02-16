<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadCard;
use App\Models\LeadEmail;
use App\Models\LeadPhone;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $statusNew = Status::where('slug', 'new')->first();
        $holdingStatuses = Status::whereIn('slug', [
            'need-to-reconnect',
            'callback',
            'maxout',
            'drop',
        ])->get()->values();

        if (! $statusNew || $holdingStatuses->isEmpty()) {
            $this->command->warn('Run RoleSeeder and StatusSeeder first.');
            return;
        }

        $agent50 = User::where('username', 'agent50')->first();
        $agent10 = User::where('username', 'agent10')->first();
        $agent0 = User::where('username', 'agent0')->first();

        if (! $agent50 || ! $agent10 || ! $agent0) {
            $this->command->warn('Run UserSeeder first (agent50, agent10, agent0 are required).');
            return;
        }

        // Keep this deterministic so the queue behavior is always testable.
        DB::table('lead_cards')->delete();
        DB::table('lead_phones')->delete();
        DB::table('lead_emails')->delete();
        Lead::query()->delete();

        $createHoldingLeads = function (User $agent, int $count, string $prefix) use ($holdingStatuses): void {
            for ($i = 1; $i <= $count; $i++) {
                $status = $holdingStatuses[($i - 1) % $holdingStatuses->count()];

                $lead = Lead::factory()->create([
                    'first_name' => $prefix,
                    'last_name' => 'Lead ' . $i,
                    'status_id' => $status->id,
                    'assigned_to' => $agent->id,
                    'details' => $prefix . ' history lead #' . $i,
                    'is_dnc' => $i % 10 === 0,
                ]);

                LeadPhone::create([
                    'lead_id' => $lead->id,
                    'phone' => '555-8' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                ]);

                LeadEmail::create([
                    'lead_id' => $lead->id,
                    'email' => strtolower($prefix) . '.lead' . $i . '@example.com',
                ]);

                if ($i % 5 === 0) {
                    LeadCard::create([
                        'lead_id' => $lead->id,
                        'bank_name' => 'Sample Bank',
                        'bank_tollfree' => '800555000' . ($i % 10),
                        'card_number' => '411111111111' . str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                        'name_on_card' => $prefix . ' Lead ' . $i,
                        'card_expiry' => '12/30',
                        'card_cvc' => '123',
                        'balance' => 1000 + ($i * 10),
                        'available_amount' => 500 + ($i * 5),
                        'last_payment' => '150',
                        'due_payment' => '75',
                        'apr' => 19.99,
                        'charge_card' => $i % 2 === 0,
                        'comment' => 'Seeded card for testing.',
                        'created_by' => $agent->id,
                        'updated_by' => $agent->id,
                    ]);
                }
            }
        };

        // Agent with 50 holding leads (must be blocked from new queue items).
        $createHoldingLeads($agent50, 50, 'Agent50');

        // Agent with 10 holding leads (still allowed to get new leads).
        $createHoldingLeads($agent10, 10, 'Agent10');

        // Agent with zero holding leads (fully available for queue).
        // No assigned holding leads for agent0.

        // Pool of unassigned new leads available for round-robin queue.
        $queueLeads = Lead::factory()->count(300)->create([
            'status_id' => $statusNew->id,
            'assigned_to' => null,
            'details' => 'Queue pool lead',
        ]);

        foreach ($queueLeads as $index => $lead) {
            $lead->update([
                'is_dnc' => ($index + 1) % 12 === 0,
            ]);

            LeadPhone::create([
                'lead_id' => $lead->id,
                'phone' => '555-9' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
            ]);

            LeadEmail::create([
                'lead_id' => $lead->id,
                'email' => 'queue.lead' . ($index + 1) . '@example.com',
            ]);

            if (($index + 1) % 40 === 0) {
                LeadCard::create([
                    'lead_id' => $lead->id,
                    'bank_name' => 'Queue Test Bank',
                    'bank_tollfree' => '8005551212',
                    'card_number' => '545454545454' . str_pad((string) (($index + 1) % 100), 2, '0', STR_PAD_LEFT),
                    'name_on_card' => $lead->fullName(),
                    'card_expiry' => '08/29',
                    'card_cvc' => '456',
                    'balance' => 2300.00,
                    'available_amount' => 900.00,
                    'last_payment' => '200',
                    'due_payment' => '125',
                    'apr' => 24.50,
                    'charge_card' => false,
                    'comment' => 'Queue sample card.',
                ]);
            }
        }
    }
}
