<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(['web', 'tablet', 'kassa']);
        $tableCode = $channel === 'web' 
            ? 'WEB-' . strtoupper($this->faker->bothify('??##??'))
            : ($channel === 'tablet' ? (string) $this->faker->numberBetween(1, 20) : null);

        $paidAt = $this->faker->dateTimeBetween('2021-03-04', 'now');

        return [
            'channel' => $channel,
            'status' => 'paid',
            'table_code' => $tableCode,
            'subtotal' => 0,
            'total' => 0,
            'paid_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ];
    }
}
