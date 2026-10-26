<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
