<?php

arch()->preset()->php();

arch()->preset()->security();

arch('Laravel debugging helpers and env() stay out of the code')
    ->expect(['dd', 'ddd', 'env'])
    ->not->toBeUsed();

arch('repositories are used only by services')
    ->expect('App\Repositories')
    ->toOnlyBeUsedIn('App\Services');

arch('services are used only by controllers')
    ->expect('App\Services')
    ->toOnlyBeUsedIn('App\Http\Controllers');

arch('controllers do not build raw database queries')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('form requests follow one shape')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest')
    ->toHaveSuffix('Request')
    ->toHaveMethod('rules');

arch('policies, resources and enums live where Laravel expects them')
    ->expect('App\Policies')->toHaveSuffix('Policy')
    ->and('App\Http\Resources')->toExtend('Illuminate\Http\Resources\Json\JsonResource')
    ->and('App\Enums')->toBeEnums();
