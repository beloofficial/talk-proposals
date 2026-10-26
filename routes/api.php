<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProposalController;
use App\Models\Proposal;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/proposals', [ProposalController::class, 'index']);
    Route::post('/proposals', [ProposalController::class, 'store'])->can('create', Proposal::class);
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])->can('view', 'proposal');
    Route::get('/proposals/{proposal}/file', [ProposalController::class, 'download'])->can('view', 'proposal');
});
