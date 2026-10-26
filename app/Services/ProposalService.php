<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Proposal;
use App\Models\User;
use App\Repositories\ProposalRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProposalService
{
    public function __construct(private ProposalRepository $proposals) {}

    public function listFor(User $user): LengthAwarePaginator
    {
        return $this->proposals->paginate(
            speaker: $user->role === Role::Speaker ? $user : null,
        );
    }

    /**
     * @param  array{title: string, description: string}  $data
     */
    public function create(User $speaker, array $data, ?UploadedFile $file): Proposal
    {
        $path = $file?->store('proposals', 'local');

        try {
            return $speaker->proposals()->create([...$data, 'file_path' => $path]);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }
}
