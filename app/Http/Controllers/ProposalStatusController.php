<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Requests\UpdateProposalStatusRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;

class ProposalStatusController extends Controller
{
    public function __invoke(
        UpdateProposalStatusRequest $request,
        Proposal $proposal,
        ProposalService $proposals,
    ): ProposalResource {
        return new ProposalResource(
            $proposals->changeStatus($proposal, $request->enum('status', ProposalStatus::class)),
        );
    }
}
