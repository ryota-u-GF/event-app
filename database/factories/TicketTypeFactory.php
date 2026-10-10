<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['一般', '学生', 'VIP', 'ペア']),
            'price' => fake()->numberBetween(0, 10) * 1000,
            'capacity' => fake()->numberBetween(5, 50),
        ];
    }
}
