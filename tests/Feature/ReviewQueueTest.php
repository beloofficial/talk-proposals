<?php

use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->reviewer = User::factory()->reviewer()->create();
    $this->reviewed = Proposal::factory()->create(['title' => 'Laravel queues']);
    $this->waiting = Proposal::factory()->create(['title' => 'Laravel testing']);
    $this->other = Proposal::factory()->create(['title' => 'Go generics']);

    Review::factory()->for($this->reviewed)->for($this->reviewer)->create();
    Review::factory(2)->for($this->other)->create();
});

it('lists proposals the reviewer still has to rate', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?review=pending')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->other->id, $this->waiting->id]);
});

it('lists proposals the reviewer has already rated', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?review=done')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->reviewed->id]);
});

it('combines the review tab with the search', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?review=pending&search=laravel')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->waiting->id]);
});

it('shows review counts to reviewers', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals')
        ->assertJsonPath('data.*.reviews_count', [2, 0, 1]);
});

it('shows review counts to admins but no review queue', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/proposals?review=pending')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.*.reviews_count', [2, 0, 1]);
});

it('hides review counts from speakers', function () {
    $this->actingAs($this->reviewed->user)
        ->getJson('/api/proposals')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.reviews_count');
});

it('validates the review tab', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?review=maybe')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('review');
});
