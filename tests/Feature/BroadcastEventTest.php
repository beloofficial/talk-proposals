<?php

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ProposalSubmitted::class, ProposalReviewed::class, ProposalStatusChanged::class]);

    $this->proposal = Proposal::factory()->create(['title' => 'Scaling queues']);
    $this->reviewer = User::factory()->reviewer()->create(['name' => 'Grace Hopper']);
});

it('tells reviewers about a newly submitted proposal', function () {
    $speaker = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($speaker)
        ->postJson('/api/proposals', ['title' => 'Serverless PHP', 'description' => 'Private details'])
        ->assertCreated();

    Event::assertDispatched(ProposalSubmitted::class, function (ProposalSubmitted $event) {
        expect($event->broadcastOn()[0]->name)->toBe('private-reviewers')
            ->and($event->broadcastAs())->toBe('proposal.submitted')
            ->and($event->broadcastWith())->toBe([
                'proposal_id' => $event->proposal->id,
                'title' => 'Serverless PHP',
                'speaker' => 'Ada Lovelace',
            ]);

        return true;
    });
});

it('tells admins about a new review and about a changed one', function () {
    $url = "/api/proposals/{$this->proposal->id}/review";

    $this->actingAs($this->reviewer)->putJson($url, ['rating' => 3, 'comment' => 'Private comment'])->assertCreated();
    $this->actingAs($this->reviewer)->putJson($url, ['rating' => 5, 'comment' => 'Private comment'])->assertOk();

    Event::assertDispatchedTimes(ProposalReviewed::class, 2);
    Event::assertDispatched(ProposalReviewed::class, function (ProposalReviewed $event) {
        expect($event->broadcastOn()[0]->name)->toBe('private-admins')
            ->and($event->broadcastAs())->toBe('proposal.reviewed');

        return $event->broadcastWith() === [
            'proposal_id' => $this->proposal->id,
            'title' => 'Scaling queues',
            'reviewer' => 'Grace Hopper',
            'is_new' => true,
        ];
    });
    Event::assertDispatched(ProposalReviewed::class, fn (ProposalReviewed $event) => $event->isNew === false);
});

it('stays silent when a review is saved without changes', function () {
    Review::factory()->for($this->proposal)->for($this->reviewer)->create(['rating' => 4, 'comment' => 'Same']);

    $this->actingAs($this->reviewer)
        ->putJson("/api/proposals/{$this->proposal->id}/review", ['rating' => 4, 'comment' => 'Same'])
        ->assertOk();

    Event::assertNotDispatched(ProposalReviewed::class);
});

it('tells the speaker about a status change of their proposal', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patchJson("/api/proposals/{$this->proposal->id}/status", ['status' => 'approved'])
        ->assertOk();

    Event::assertDispatched(ProposalStatusChanged::class, function (ProposalStatusChanged $event) {
        expect($event->broadcastOn()[0]->name)->toBe("private-App.Models.User.{$this->proposal->user_id}")
            ->and($event->broadcastAs())->toBe('proposal.status-changed')
            ->and($event->broadcastWith())->toBe([
                'proposal_id' => $this->proposal->id,
                'title' => 'Scaling queues',
                'status' => 'approved',
            ]);

        return true;
    });
});

it('stays silent when the status does not change', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patchJson("/api/proposals/{$this->proposal->id}/status", ['status' => 'pending'])
        ->assertOk();

    Event::assertNotDispatched(ProposalStatusChanged::class);
});
