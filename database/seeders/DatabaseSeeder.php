<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoSpeaker = User::factory()->create(['name' => 'Demo Speaker', 'email' => 'speaker@example.com']);
        $demoReviewer = User::factory()->reviewer()->create(['name' => 'Demo Reviewer', 'email' => 'reviewer@example.com']);
        User::factory()->admin()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com']);

        $speakers = User::factory(4)->create()->push($demoSpeaker);
        $reviewers = User::factory(2)->reviewer()->create()->push($demoReviewer);

        $tags = collect(['Technology', 'Health', 'Business', 'Design', 'Science', 'Education'])
            ->map(fn (string $name) => Tag::create(['name' => $name]));

        $proposals = $speakers->flatMap(fn (User $speaker) => Proposal::factory(3)
            ->for($speaker)
            ->state(fn () => ['status' => fake()->randomElement(ProposalStatus::cases())])
            ->create());

        foreach ($proposals as $proposal) {
            $proposal->tags()->attach($tags->random(fake()->numberBetween(0, 3)));

            foreach ($reviewers->random(fake()->numberBetween(0, $reviewers->count())) as $reviewer) {
                Review::factory()->for($proposal)->for($reviewer)->create();
            }
        }
    }
}
