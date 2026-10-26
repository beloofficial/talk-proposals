<?php

use App\Enums\Role;
use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

it('seeds the demo accounts and sample proposals', function () {
    expect(User::firstWhere('email', 'speaker@example.com')->role)->toBe(Role::Speaker)
        ->and(User::firstWhere('email', 'reviewer@example.com')->role)->toBe(Role::Reviewer)
        ->and(User::firstWhere('email', 'admin@example.com')->role)->toBe(Role::Admin)
        ->and(Proposal::count())->toBe(15)
        ->and(Tag::count())->toBe(6)
        ->and(Tag::has('proposals')->exists())->toBeTrue();
});

it('lets only reviewers author the seeded reviews', function () {
    expect(User::whereHas('reviews')->where('role', '!=', Role::Reviewer)->exists())->toBeFalse();
});

it('lets every demo account log in with the documented password', function (string $email) {
    $this->postJson('/login', ['email' => $email, 'password' => 'password'])->assertOk();
})->with(['speaker@example.com', 'reviewer@example.com', 'admin@example.com']);
