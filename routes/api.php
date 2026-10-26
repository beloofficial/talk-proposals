<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalStatusController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TagController;
use App\Models\Proposal;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/proposals', [ProposalController::class, 'index']);
    Route::post('/proposals', [ProposalController::class, 'store'])->can('create', Proposal::class);
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])->can('view', 'proposal');
    Route::get('/proposals/{proposal}/file', [ProposalController::class, 'download'])->can('view', 'proposal');
    Route::put('/proposals/{proposal}/review', ReviewController::class)->can('review', 'proposal');
    Route::patch('/proposals/{proposal}/status', ProposalStatusController::class)->can('updateStatus', 'proposal');

    Route::get('/tags', [TagController::class, 'index']);
});
