<?php

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('lets a speaker submit a proposal with a PDF of up to 4 MB', function () {
    $speaker = User::factory()->create();

    $this->actingAs($speaker)
        ->postJson('/api/proposals', [
            'title' => 'Scaling Laravel queues',
            'description' => 'Lessons learned in production.',
            'file' => UploadedFile::fake()->create('talk.pdf', 4 * 1024, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Scaling Laravel queues')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.has_file', true)
        ->assertJsonMissingPath('data.file_path');

    Storage::disk('local')->assertExists($speaker->proposals()->sole()->file_path);
});

it('accepts a proposal without a file', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', ['title' => 'No slides', 'description' => 'Just talking.'])
        ->assertCreated()
        ->assertJsonPath('data.has_file', false);
});

it('validates the proposal', function (array $input, array $errors) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing fields' => [[], ['title', 'description']],
    'not a PDF' => [
        fn () => ['title' => 'T', 'description' => 'D', 'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ['file'],
    ],
    'larger than 4 MB' => [
        fn () => ['title' => 'T', 'description' => 'D', 'file' => UploadedFile::fake()->create('talk.pdf', 4 * 1024 + 1, 'application/pdf')],
        ['file'],
    ],
]);

it('forbids non-speakers to submit', function (User $user) {
    $this->actingAs($user)
        ->postJson('/api/proposals', ['title' => 'T', 'description' => 'D'])
        ->assertForbidden();

    expect(Proposal::count())->toBe(0);
})->with([
    'reviewer' => fn () => User::factory()->reviewer()->create(),
    'admin' => fn () => User::factory()->admin()->create(),
]);

it('lists only the speaker\'s own proposals, newest first', function () {
    $speaker = User::factory()->create();
    $older = Proposal::factory()->for($speaker)->create();
    $newer = Proposal::factory()->for($speaker)->create();
    Proposal::factory()->create();

    $this->actingAs($speaker)
        ->getJson('/api/proposals')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('requires authentication', function () {
    $this->getJson('/api/proposals')->assertUnauthorized();
    $this->postJson('/api/proposals')->assertUnauthorized();
});
