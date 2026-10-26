<?php

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\User;

beforeEach(function () {
    $this->proposal = Proposal::factory()->create();
});

it('lets an admin change the status', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patchJson("/api/proposals/{$this->proposal->id}/status", ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($this->proposal->fresh()->status)->toBe(ProposalStatus::Approved);
});

it('rejects an unknown status', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patchJson("/api/proposals/{$this->proposal->id}/status", ['status' => 'published'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('forbids speakers and reviewers to change the status', function (User $user) {
    $this->actingAs($user)
        ->patchJson("/api/proposals/{$this->proposal->id}/status", ['status' => 'approved'])
        ->assertForbidden();

    expect($this->proposal->fresh()->status)->toBe(ProposalStatus::Pending);
})->with([
    'the proposal\'s speaker' => fn () => $this->proposal->user,
    'reviewer' => fn () => User::factory()->reviewer()->create(),
]);
