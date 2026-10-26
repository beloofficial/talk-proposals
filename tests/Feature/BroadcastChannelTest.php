<?php

use App\Models\User;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');
});

function authorizeChannel(?User $user, string $channel)
{
    $request = $user ? test()->actingAs($user) : test();

    return $request->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-{$channel}",
    ]);
}

it('lets each role subscribe only to its own channel', function (string $channel, Closure $user, int $status) {
    authorizeChannel($user(), $channel)->assertStatus($status);
})->with([
    'reviewer → reviewers' => ['reviewers', fn () => User::factory()->reviewer()->create(), 200],
    'admin → reviewers' => ['reviewers', fn () => User::factory()->admin()->create(), 403],
    'speaker → reviewers' => ['reviewers', fn () => User::factory()->create(), 403],
    'admin → admins' => ['admins', fn () => User::factory()->admin()->create(), 200],
    'reviewer → admins' => ['admins', fn () => User::factory()->reviewer()->create(), 403],
    'speaker → admins' => ['admins', fn () => User::factory()->create(), 403],
]);

it('signs the subscription for an allowed channel', function () {
    authorizeChannel(User::factory()->admin()->create(), 'admins')
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('lets a user subscribe only to their own personal channel', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    authorizeChannel($user, "App.Models.User.{$user->id}")->assertOk();
    authorizeChannel($user, "App.Models.User.{$someoneElse->id}")->assertForbidden();
});

it('rejects guests', function () {
    authorizeChannel(null, 'admins')->assertForbidden();
});
