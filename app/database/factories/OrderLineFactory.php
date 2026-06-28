<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => 0,
            'line_total' => 0,
        ];
    }
}
