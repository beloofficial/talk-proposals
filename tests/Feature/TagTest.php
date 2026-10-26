<?php

use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;

it('lists tags alphabetically', function () {
    Tag::factory()->create(['name' => 'Health']);
    Tag::factory()->create(['name' => 'Business']);

    $this->actingAs(User::factory()->reviewer()->create())
        ->getJson('/api/tags')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Business', 'Health']);
});

it('creates new tags and reuses existing ones case-insensitively', function () {
    $existing = Tag::factory()->create(['name' => 'Technology']);

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', [
            'title' => 'Serverless PHP',
            'description' => 'Running Laravel on Lambda.',
            'tags' => ['technology', 'Serverless'],
        ])
        ->assertCreated();

    expect($response->json('data.tags.*.name'))->toEqualCanonicalizing(['Technology', 'Serverless'])
        ->and(Tag::count())->toBe(2)
        ->and(Proposal::sole()->tags->pluck('id'))->toContain($existing->id);
});

it('accepts at most ten tags per proposal', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', [
            'title' => 'T',
            'description' => 'D',
            'tags' => array_map(fn (int $number) => "Tag {$number}", range(1, 11)),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
});

it('rejects duplicate and overlong tags', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/proposals', [
            'title' => 'T',
            'description' => 'D',
            'tags' => ['PHP', 'php', str_repeat('x', 51)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0', 'tags.1', 'tags.2']);

    expect(Tag::count())->toBe(0);
});
