<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalController extends Controller
{
    public function __construct(private ProposalService $proposals) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProposalResource::collection($this->proposals->listFor($request->user()));
    }

    public function store(StoreProposalRequest $request): ProposalResource
    {
        $proposal = $this->proposals->create(
            $request->user(),
            $request->safe()->only(['title', 'description']),
            $request->file('file'),
        );

        return new ProposalResource($proposal);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        return new ProposalResource($proposal);
    }

    public function download(Proposal $proposal): StreamedResponse
    {
        abort_if($proposal->file_path === null || Storage::disk('local')->fileMissing($proposal->file_path), 404);

        return Storage::disk('local')->download(
            $proposal->file_path,
            Str::slug("{$proposal->id} {$proposal->title}").'.pdf',
        );
    }
}
