<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalReviewed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public bool $isNew;

    public function __construct(public Review $review)
    {
        $this->isNew = $review->wasRecentlyCreated;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admins')];
    }

    public function broadcastAs(): string
    {
        return 'proposal.reviewed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'proposal_id' => $this->review->proposal_id,
            'title' => $this->review->proposal->title,
            'reviewer' => $this->review->user->name,
            'is_new' => $this->isNew,
        ];
    }
}
