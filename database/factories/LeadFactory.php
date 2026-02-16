<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'status_id' => 1,
            'assigned_to' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'address' => fake()->optional()->streetAddress() . ', ' . fake()->city(),
            'date_of_birth' => fake()->optional()->date('Y-m-d'),
            'mothers_maiden_name' => fake()->optional()->lastName(),
            'ssn' => fake()->optional()->numerify('###-##-####'),
            'approx_debt' => fake()->optional()->randomFloat(2, 1500, 45000),
            'details' => fake()->optional()->sentence(),
            'is_dnc' => fake()->boolean(15),
        ];
    }
}
