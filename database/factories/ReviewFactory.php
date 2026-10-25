<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'user_id' => User::factory()->reviewer(),
            'rating' => fake()->numberBetween(1, config('reviews.max_rating')),
            'comment' => fake()->paragraph(),
        ];
    }
}
