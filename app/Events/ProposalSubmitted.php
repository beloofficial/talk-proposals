<?php

namespace App\Events;

use App\Models\Proposal;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalSubmitted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Proposal $proposal) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('reviewers')];
    }

    public function broadcastAs(): string
    {
        return 'proposal.submitted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'proposal_id' => $this->proposal->id,
            'title' => $this->proposal->title,
            'speaker' => $this->proposal->user->name,
        ];
    }
}
