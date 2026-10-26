<?php

namespace App\Repositories;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProposalRepository
{
    public function paginate(?User $speaker = null, int $perPage = 10): LengthAwarePaginator
    {
        return Proposal::query()
            ->when($speaker, fn (Builder $query) => $query->whereBelongsTo($speaker))
            ->latest('id')
            ->paginate($perPage);
    }
}
