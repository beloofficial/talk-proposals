<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Proposal;
use App\Services\ReviewService;

class ReviewController extends Controller
{
    public function __invoke(UpsertReviewRequest $request, Proposal $proposal, ReviewService $reviews): ReviewResource
    {
        return new ReviewResource($reviews->upsert($proposal, $request->user(), $request->validated()));
    }
}
