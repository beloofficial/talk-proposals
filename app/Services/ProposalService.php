<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\ProposalRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProposalService
{
    public function __construct(private ProposalRepository $proposals) {}

    /**
     * @param  list<int|string>  $tagIds
     */
    public function listFor(User $user, ?string $search = null, array $tagIds = []): LengthAwarePaginator
    {
        return $this->proposals->paginate(
            speaker: $user->role === Role::Speaker ? $user : null,
            search: $search,
            tagIds: $tagIds,
        );
    }

    /**
     * @param  array{title: string, description: string}  $data
     * @param  list<string>  $tags
     */
    public function create(User $speaker, array $data, array $tags, ?UploadedFile $file): Proposal
    {
        $path = $file?->store('proposals', 'local');

        try {
            return DB::transaction(function () use ($speaker, $data, $tags, $path) {
                $proposal = $speaker->proposals()->create([...$data, 'file_path' => $path]);

                $tagIds = array_map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id, $tags);
                $proposal->tags()->attach($tagIds);

                return $proposal;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    public function changeStatus(Proposal $proposal, ProposalStatus $status): Proposal
    {
        $proposal->status = $status;
        $proposal->save();

        return $proposal;
    }
}
