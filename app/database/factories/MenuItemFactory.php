<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem;

    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 999),
            'suffix' => $this->faker->optional(0.2)->randomLetter(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 5, 45),
            'is_active' => true,
        ];
    }
}
