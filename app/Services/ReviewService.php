<?php

namespace App\Services;

use App\Events\ProposalReviewed;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;

class ReviewService
{
    /**
     * @param  array{rating: int, comment?: string|null}  $data
     */
    public function upsert(Proposal $proposal, User $reviewer, array $data): Review
    {
        $review = $proposal->reviews()->updateOrCreate(['user_id' => $reviewer->id], $data);

        if ($review->wasRecentlyCreated || $review->wasChanged()) {
            ProposalReviewed::dispatch($review);
        }

        return $review;
    }
}
