<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProposalRequest;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalController extends Controller
{
    public function __construct(private ProposalService $proposals) {}

    public function index(IndexProposalRequest $request): AnonymousResourceCollection
    {
        return ProposalResource::collection($this->proposals->listFor(
            $request->user(),
            $request->validated('search'),
            $request->validated('tags') ?? [],
            $request->validated('review'),
        ));
    }

    public function store(StoreProposalRequest $request): JsonResponse
    {
        $proposal = $this->proposals->create(
            $request->user(),
            $request->safe()->only(['title', 'description']),
            $request->validated('tags') ?? [],
            $request->file('file'),
        );

        return (new ProposalResource($proposal))->response()->setStatusCode(201);
    }

    public function show(Request $request, Proposal $proposal): ProposalResource
    {
        if ($request->user()->can('viewReviews', $proposal)) {
            $proposal->load(['reviews' => fn (HasMany $query) => $query->with('user')->latest('id')]);
        }

        return (new ProposalResource($proposal))->additional([
            'meta' => ['max_rating' => config('reviews.max_rating')],
        ]);
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
