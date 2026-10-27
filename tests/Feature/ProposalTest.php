<?php

use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

dataset('staff', [
    'reviewer' => fn () => User::factory()->reviewer()->create(),
    'admin' => fn () => User::factory()->admin()->create(),
]);

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

it('rolls back the proposal and removes its file when saving tags fails', function () {
    Tag::created(fn () => throw new RuntimeException('Tag storage is down'));

    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', [
            'title' => 'T',
            'description' => 'D',
            'file' => UploadedFile::fake()->create('talk.pdf', 10, 'application/pdf'),
            'tags' => ['Brand new tag'],
        ])
        ->assertServerError();

    expect(Proposal::count())->toBe(0)->and(Tag::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('proposals');
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
})->with('staff');

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

it('lists all proposals with their speakers for reviewers and admins', function (User $user) {
    Proposal::factory(3)->create();

    $this->actingAs($user)
        ->getJson('/api/proposals')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'title', 'status', 'speaker' => ['id', 'name']]]]);
})->with('staff');

it('shows a proposal to its speaker', function () {
    $proposal = Proposal::factory()->create();

    $this->actingAs($proposal->user)
        ->getJson("/api/proposals/{$proposal->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $proposal->id)
        ->assertJsonPath('data.speaker.name', $proposal->user->name);
});

it('shows any proposal to reviewers and admins', function (User $user) {
    $proposal = Proposal::factory()->create();

    $this->actingAs($user)->getJson("/api/proposals/{$proposal->id}")->assertOk();
})->with('staff');

it('hides a proposal from other speakers', function () {
    $proposal = Proposal::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/proposals/{$proposal->id}")
        ->assertForbidden();
});

it('does not expose other users\' emails', function () {
    $proposal = Proposal::factory()->create();
    Review::factory()->for($proposal)->create();
    $this->actingAs(User::factory()->reviewer()->create());

    $this->getJson('/api/proposals')
        ->assertOk()
        ->assertJsonPath('data.0.speaker.name', $proposal->user->name)
        ->assertJsonMissingPath('data.0.speaker.email');

    $this->getJson("/api/proposals/{$proposal->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.speaker.email')
        ->assertJsonMissingPath('data.reviews.0.reviewer.email');
});

it('returns 404 for a missing proposal', function () {
    $this->actingAs(User::factory()->reviewer()->create())
        ->getJson('/api/proposals/999')
        ->assertNotFound();
});

it('lets the speaker download the PDF as an attachment named after the title', function () {
    $proposal = Proposal::factory()->withFile()->create(['title' => 'Scaling Laravel queues']);

    $this->actingAs($proposal->user)
        ->get("/api/proposals/{$proposal->id}/file")
        ->assertOk()
        ->assertDownload("{$proposal->id}-scaling-laravel-queues.pdf");
});

it('lets reviewers and admins download any PDF', function (User $user) {
    $proposal = Proposal::factory()->withFile()->create();

    $this->actingAs($user)->get("/api/proposals/{$proposal->id}/file")->assertOk();
})->with('staff');

it('forbids other speakers to download the PDF', function () {
    $proposal = Proposal::factory()->withFile()->create();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/proposals/{$proposal->id}/file")
        ->assertForbidden();
});

it('returns 404 when the proposal has no PDF', function () {
    $proposal = Proposal::factory()->create();

    $this->actingAs($proposal->user)
        ->getJson("/api/proposals/{$proposal->id}/file")
        ->assertNotFound();
});

it('returns 404 when the PDF is gone from the disk', function () {
    $proposal = Proposal::factory()->create(['file_path' => 'proposals/missing.pdf']);

    $this->actingAs($proposal->user)
        ->getJson("/api/proposals/{$proposal->id}/file")
        ->assertNotFound();
});

it('requires authentication', function () {
    $this->getJson('/api/proposals')->assertUnauthorized();
    $this->postJson('/api/proposals')->assertUnauthorized();
    $this->getJson('/api/proposals/1')->assertUnauthorized();
    $this->getJson('/api/proposals/1/file')->assertUnauthorized();
});
