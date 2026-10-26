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
        ?string $search = null,
        array $tagIds = [],
        int $perPage = 10,
    ): LengthAwarePaginator {
        return Proposal::query()
            ->with(['user', 'tags'])
            ->when($speaker, fn (Builder $query) => $query->whereBelongsTo($speaker))
            ->when($search, fn (Builder $query) => $query->whereLike('title', "%{$search}%"))
            ->when($tagIds, fn (Builder $query) => $query->whereHas('tags', fn (Builder $query) => $query->whereKey($tagIds)))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
