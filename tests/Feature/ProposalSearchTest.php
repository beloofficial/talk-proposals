<?php

use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;

beforeEach(function () {
    $this->reviewer = User::factory()->reviewer()->create();
});

it('filters proposals that have at least one of the given tags', function () {
    [$php, $go, $rust] = Tag::factory(3)->create();
    $phpTalk = Proposal::factory()->hasAttached($php)->create();
    $goTalk = Proposal::factory()->hasAttached($go)->create();
    $bothTalk = Proposal::factory()->hasAttached(collect([$php, $go]))->create();
    Proposal::factory()->hasAttached($rust)->create();
    Proposal::factory()->create();

    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?'.http_build_query(['tags' => [$php->id, $go->id]]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$bothTalk->id, $goTalk->id, $phpTalk->id]);
});

it('sends only the title to the search index', function () {
    $proposal = Proposal::factory()->make(['title' => 'Scaling Laravel', 'description' => 'Private notes']);

    expect($proposal->toSearchableArray())->toBe(['title' => 'Scaling Laravel']);
});

it('indexes a submitted proposal through the queue', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', ['title' => 'Queued talk', 'description' => 'D'])
        ->assertCreated();

    Queue::assertPushed(MakeSearchable::class, fn (MakeSearchable $job) => $job->models->first()->title === 'Queued talk');
});

it('does not index a proposal whose transaction was rolled back', function () {
    Queue::fake();
    Tag::created(fn () => throw new RuntimeException('Tag storage is down'));

    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', ['title' => 'Lost talk', 'description' => 'D', 'tags' => ['Brand new tag']])
        ->assertServerError();

    Queue::assertNotPushed(MakeSearchable::class);
});

it('searches proposals by title, case-insensitively', function () {
    $match = Proposal::factory()->create(['title' => 'Scaling Laravel queues']);
    Proposal::factory()->create(['title' => 'Intro to Go', 'description' => 'Laravel is only in the description.']);

    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?search=LARAVEL')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$match->id]);
});

it('keeps a speaker\'s search and filter within their own proposals', function () {
    $tag = Tag::factory()->create();
    $speaker = User::factory()->create();
    $own = Proposal::factory()->for($speaker)->hasAttached($tag)->create(['title' => 'Laravel tips']);
    Proposal::factory()->hasAttached($tag)->create(['title' => 'Laravel tricks']);

    $this->actingAs($speaker)
        ->getJson('/api/proposals?search=laravel&'.http_build_query(['tags' => [$tag->id]]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$own->id]);
});

it('paginates proposals by ten and keeps the filters in the page links', function () {
    Proposal::factory(12)->create(['title' => 'Laravel talk']);

    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?search=laravel&page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('links.prev', url('/api/proposals?search=laravel&page=1'));
});

it('ignores an empty tags filter', function () {
    Proposal::factory()->create();

    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?tags=')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('validates the filter parameters', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?tags[]=abc')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags.0');
});
