<?php

beforeEach(function () {
    $this->withoutVite();
});

it('serves the SPA shell for any page path', function (string $path) {
    $this->get($path)->assertOk()->assertViewIs('app');
})->with(['/', '/login', '/proposals/42']);

it('answers unknown API paths with a JSON 404 instead of the SPA', function () {
    $this->get('/api/unknown')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
