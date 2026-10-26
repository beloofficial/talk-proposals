<?php

use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;

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

it('validates the filter parameters', function () {
    $this->actingAs($this->reviewer)
        ->getJson('/api/proposals?tags[]=abc')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags.0');
});
