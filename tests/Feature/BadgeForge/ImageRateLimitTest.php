<?php

use App\Models\BadgeAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Cap por IP das imagens anonimas (F8, ADR-089).
|
| `badgeforge-images` e um teto generoso e configuravel que estatica, provider
| e badge pessoal dividem. Endpoint e custom seguem com o limite por dono; a
| recusa sai no envelope `rate_limited`, nunca em HTML.
*/

beforeEach(function () {
    config(['badgeforge.serving.rate_limit.per_minute' => 2]);
});

function imageRateLimitPersonalUrl(): string
{
    $user = User::factory()->create(['username' => 'natanfiuza']);
    $asset = BadgeAsset::factory()->for($user)->static()->create();

    return '/b/natanfiuza/'.$asset->id.'.svg';
}

it('aplica o cap por IP nas imagens estaticas', function () {
    $this->get('/api/badges/static/build/passing.svg')->assertOk();
    $this->get('/api/badges/static/build/passing.svg')->assertOk();

    $this->get('/api/badges/static/build/passing.svg')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited');
});

it('compartilha o cap entre estatica, provider e badge pessoal', function () {
    $this->get('/api/badges/static/build/passing.svg')->assertOk();
    $this->get(imageRateLimitPersonalUrl())->assertOk();

    $this->get('/api/badges/github/stars/natanfiuza/devtools.svg')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});
