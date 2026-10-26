<?php

it('serves the OpenAPI document locally with both the API and the auth endpoints', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonStructure(['paths' => ['/register', '/login', '/logout', '/api/proposals', '/api/tags']]);
});

it('keeps the API docs closed outside the local environment', function () {
    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();
});
