<?php

use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->proposal = Proposal::factory()->create();
    $this->reviewer = User::factory()->reviewer()->create();
});

it('lets a reviewer rate and comment on a proposal', function () {
    $this->actingAs($this->reviewer)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => 4, 'comment' => 'Solid talk.'])
        ->assertCreated()
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.comment', 'Solid talk.')
        ->assertJsonPath('data.reviewer.id', $this->reviewer->id);
});

it('updates the reviewer\'s existing review instead of adding a second one', function () {
    Review::factory()->for($this->proposal)->for($this->reviewer)->create(['rating' => 2]);

    $this->actingAs($this->reviewer)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => 5, 'comment' => null])
        ->assertOk()
        ->assertJsonPath('data.rating', 5);

    expect($this->proposal->reviews()->sole()->rating)->toBe(5);
});

it('validates the rating against the configured scale', function (mixed $rating) {
    $this->actingAs($this->reviewer)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => $rating])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rating');
})->with([0, 6, 'great', null]);

it('accepts ratings up to a larger configured maximum', function () {
    config(['reviews.max_rating' => 10]);

    $this->actingAs($this->reviewer)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => 9])
        ->assertCreated();
});

it('forbids speakers and admins to review', function (User $user) {
    $this->actingAs($user)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => 3])
        ->assertForbidden();

    expect(Review::count())->toBe(0);
})->with([
    'the proposal\'s speaker' => fn () => $this->proposal->user,
    'admin' => fn () => User::factory()->admin()->create(),
]);

it('shows reviews to everyone who can see the proposal', function (User $user) {
    Review::factory()->for($this->proposal)->for($this->reviewer)->create(['rating' => 3]);

    $this->actingAs($user)
        ->getJson("/api/proposals/{$this->proposal->id}")
        ->assertOk()
        ->assertJsonPath('data.reviews.0.rating', 3)
        ->assertJsonPath('data.reviews.0.reviewer.name', $this->reviewer->name)
        ->assertJsonPath('meta.max_rating', 5);
})->with([
    'the proposal\'s speaker' => fn () => $this->proposal->user,
    'reviewer' => fn () => User::factory()->reviewer()->create(),
    'admin' => fn () => User::factory()->admin()->create(),
]);
