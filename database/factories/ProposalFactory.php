<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->catchPhrase(),
            'description' => fake()->paragraphs(3, true),
        ];
    }

    /**
     * Indicate that the proposal has a PDF attached.
     */
    public function withFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => UploadedFile::fake()->create('talk.pdf', 10, 'application/pdf')->store('proposals', 'local'),
        ]);
    }
}
