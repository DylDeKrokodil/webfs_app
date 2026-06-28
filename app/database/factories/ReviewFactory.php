<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'overall_score' => $this->faker->numberBetween(3, 5),
            'food_score' => $this->faker->numberBetween(1, 5),
            'service_score' => $this->faker->numberBetween(1, 5),
            'speed_score' => $this->faker->numberBetween(1, 5),
            'favorite_dish' => $this->faker->optional()->word(),
            'comment' => $this->faker->optional(0.7)->sentence(),
            'contact_permission' => $this->faker->boolean(10),
            'metadata' => [],
        ];
    }
}
