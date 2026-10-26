<?php

namespace App\Repositories;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProposalRepository
{
    /**
     * @param  list<int|string>  $tagIds
     */
    public function paginate(
        ?User $speaker = null,
        ?User $reviewer = null,
        ?string $search = null,
        array $tagIds = [],
        ?bool $reviewed = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $byReviewer = fn (Builder $reviews) => $reviews->whereBelongsTo($reviewer);

        return Proposal::query()
            ->with(['user', 'tags'])
            ->withCount('reviews')
            ->when($speaker, fn (Builder $query) => $query->whereBelongsTo($speaker))
            ->when($search, fn (Builder $query) => $query->whereLike('title', "%{$search}%"))
            ->when($tagIds, fn (Builder $query) => $query->whereHas('tags', fn (Builder $query) => $query->whereKey($tagIds)))
            ->when($reviewer && $reviewed === true, fn (Builder $query) => $query->whereHas('reviews', $byReviewer))
            ->when($reviewer && $reviewed === false, fn (Builder $query) => $query->whereDoesntHave('reviews', $byReviewer))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
