<?php

use App\Enums\BadgeViewSubject;
use App\Enums\CustomMetricFormatter;
use App\Models\BadgeAsset;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use App\Models\BadgeView;
use App\Models\User;
use App\Support\BadgeForge\Analytics\CountryReader;
use App\Support\BadgeForge\Analytics\PrivacyHash;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Gravacao de visualizacoes (F7, ADR-086).
|
| So imagem com dono grava: pessoal, endpoint e custom. Uma linha por acesso,
| inclusive 304; estatica e provider global ficam de fora. IP/UA viram HMAC e
| falha de analytics nunca derruba a imagem.
*/

const ANALYTICS_PERSONAL_DIR = 'framework/testing/badgeforge-analytics';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => ANALYTICS_PERSONAL_DIR]);

    File::deleteDirectory(storage_path(ANALYTICS_PERSONAL_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(ANALYTICS_PERSONAL_DIR));
});

function analyticsFakeDns(): void
{
    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
}

function analyticsFakeGithub(int $stars = 1234): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => $stars,
            'forks_count' => 56,
            'open_issues_count' => 7,
            'license' => ['spdx_id' => 'MIT'],
        ]),
    ]);
}

function analyticsPersonalAsset(string $username = 'natanfiuza'): BadgeAsset
{
    return BadgeAsset::factory()
        ->for(User::factory()->create(['username' => $username]))
        ->provider()
        ->create();
}

function analyticsEndpoint(): BadgeEndpoint
{
    return BadgeEndpoint::factory()
        ->for(User::factory()->create())
        ->create(['name' => 'deploys']);
}

function analyticsCustom(): BadgeCustomProvider
{
    $provider = BadgeCustomProvider::factory()->create();

    $provider->definitions()->create([
        'slug' => 'stars',
        'label' => 'Stars',
        'pointer' => '/stargazers_count',
        'formatter' => CustomMetricFormatter::Number,
        'sort' => 0,
    ]);

    return $provider->refresh();
}

function analyticsFakeCustomOrigin(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"stargazers_count":42,"forks_count":7}',
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);
}

it('grava a visualizacao da badge pessoal com hashes e referer', function () {
    analyticsFakeGithub();

    $asset = analyticsPersonalAsset();

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (analytics)',
        'Referer' => 'https://Example.COM/readme?tab=1',
    ])->get('/b/natanfiuza/'.$asset->id.'.svg')->assertOk();

    $view = BadgeView::query()->sole();

    expect($view->subject_type)->toBe(BadgeViewSubject::Personal)
        ->and($view->subject_id)->toBe($asset->id)
        ->and($view->user_id)->toBe($asset->user_id)
        ->and($view->definition)->toBeNull()
        ->and($view->cache_hit)->toBeNull()
        ->and($view->format)->toBe('svg')
        ->and($view->style)->not->toBeNull()
        ->and($view->ip_hash)->toBe(PrivacyHash::of('127.0.0.1'))
        ->and($view->user_agent_hash)->toBe(PrivacyHash::of('Mozilla/5.0 (analytics)'))
        ->and($view->user_agent)->toBe('Mozilla/5.0 (analytics)')
        ->and($view->referer_domain)->toBe('example.com')
        ->and($view->country)->toBeNull();
});

it('trunca o user-agent e descarta referer invalido', function () {
    analyticsFakeGithub();
    config(['badgeforge.analytics.user_agent_max_length' => 10]);

    $asset = analyticsPersonalAsset();

    $this->withHeaders([
        'User-Agent' => '12345678901234567890',
        'Referer' => 'not a url',
    ])->get('/b/natanfiuza/'.$asset->id.'.svg')->assertOk();

    $view = BadgeView::query()->sole();

    expect($view->user_agent)->toBe('1234567890')
        ->and($view->user_agent_hash)->toBe(PrivacyHash::of('12345678901234567890'))
        ->and($view->referer_domain)->toBeNull();
});

it('usa o leitor de pais quando o GeoIP esta disponivel', function () {
    analyticsFakeGithub();

    app()->instance(CountryReader::class, new class implements CountryReader
    {
        public function country(?string $ip): ?string
        {
            return 'BR';
        }
    });

    $asset = analyticsPersonalAsset();

    $this->get('/b/natanfiuza/'.$asset->id.'.svg')->assertOk();

    expect(BadgeView::query()->sole()->country)->toBe('BR');
});

it('conta 200 e 304 da badge pessoal', function () {
    analyticsFakeGithub();

    $asset = analyticsPersonalAsset();
    $url = '/b/natanfiuza/'.$asset->id.'.svg';

    $first = $this->get($url)->assertOk();
    $etag = (string) $first->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get($url)
        ->assertStatus(304);

    expect(BadgeView::query()->count())->toBe(2);
});

it('grava endpoint com cache_hit por status', function () {
    analyticsFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"schemaVersion":1,"label":"deploys","message":"green"}',
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $endpoint = analyticsEndpoint();
    $url = '/api/badges/endpoint/'.$endpoint->id.'.svg';

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    $views = BadgeView::query()->orderBy('id')->get();

    expect($views)->toHaveCount(2)
        ->and($views[0]->subject_type)->toBe(BadgeViewSubject::Endpoint)
        ->and($views[0]->subject_id)->toBe($endpoint->id)
        ->and($views[0]->definition)->toBeNull()
        ->and($views[0]->cache_hit)->toBeFalse()
        ->and($views[1]->cache_hit)->toBeTrue();
});

it('grava custom com a definicao e o cache_hit', function () {
    analyticsFakeDns();
    analyticsFakeCustomOrigin();

    $provider = analyticsCustom();
    $url = '/api/badges/custom/'.$provider->id.'/stars/natanfiuza/devtools.svg';

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    $views = BadgeView::query()->orderBy('id')->get();

    expect($views)->toHaveCount(2)
        ->and($views[0]->subject_type)->toBe(BadgeViewSubject::Custom)
        ->and($views[0]->subject_id)->toBe($provider->id)
        ->and($views[0]->definition)->toBe('stars')
        ->and($views[0]->cache_hit)->toBeFalse()
        ->and($views[1]->cache_hit)->toBeTrue();
});

it('nao grava estatica nem provider global', function () {
    analyticsFakeGithub();

    $this->get('/api/badges/static/build/passing.svg')->assertOk();
    $this->get('/api/badges/github/stars/natanfiuza/devtools.svg')->assertOk();

    expect(BadgeView::query()->count())->toBe(0);
});

it('serve a imagem mesmo com a tabela de analytics ausente', function () {
    analyticsFakeGithub();

    $asset = analyticsPersonalAsset();

    Schema::rename('badge_views', 'badge_views_missing');

    try {
        $this->get('/b/natanfiuza/'.$asset->id.'.svg')->assertOk();
    } finally {
        Schema::rename('badge_views_missing', 'badge_views');
    }
});

it('nao grava quando a imagem nao pode ser servida', function () {
    analyticsFakeGithub();

    $asset = analyticsPersonalAsset();

    $this->get('/b/natanfiuza/01JZZZZZZZZZZZZZZZZZZZZZZZ.svg')->assertNotFound();
    $this->get('/b/outra-conta/'.$asset->id.'.svg')->assertNotFound();

    expect(BadgeView::query()->count())->toBe(0);
});
