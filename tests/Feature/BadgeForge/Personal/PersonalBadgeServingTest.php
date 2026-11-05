<?php

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Imagem publica das badges pessoais (F4, ADR-081).
|
| Stateless: sem sessao, sem CSRF, sem `?refresh=1`. A URL leva username + ULID
| e o erro sai no envelope `ApiError`: recurso inexistente e 404 `not_found`,
| falha de banco e 503 `badge_unavailable` (F8, ADR-089). Provider fora vira
| stale/fallback 200 - nunca a pagina Inertia de erro e nunca um 500.
*/

const PERSONAL_SERVING_DIR = 'framework/testing/badgeforge-personal-serving';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => PERSONAL_SERVING_DIR]);

    File::deleteDirectory(storage_path(PERSONAL_SERVING_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PERSONAL_SERVING_DIR));
});

/**
 * @return array<string, mixed>
 */
function personalServingGithubPayload(int $stars = 1234): array
{
    return [
        'stargazers_count' => $stars,
        'forks_count' => 56,
        'open_issues_count' => 7,
        'license' => ['spdx_id' => 'MIT'],
    ];
}

function personalServingAccount(string $username = 'natanfiuza'): User
{
    return User::factory()->create(['username' => $username]);
}

function personalServingUrl(BadgeAsset $asset, string $username = 'natanfiuza', string $ext = 'svg'): string
{
    return route('badges.personal', [
        'username' => $username,
        'badgeAsset' => $asset->id,
        'ext' => $ext,
    ]);
}

it('nao passa pelos middlewares da sessao, apenas pelo cap de imagens', function () {
    $route = Route::getRoutes()->getByName('badges.personal');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toBe(['throttle:badgeforge-images']);
});

it('serve a metrica do provider e grava o arquivo', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $response = $this->get(personalServingUrl($asset))->assertOk();

    expect((string) $response->getContent())->toContain('>stars<')->toContain('>1.2k<')
        ->and($response->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=300')
        ->and($response->headers->getCookies())->toBe([]);

    $asset->refresh();

    expect($asset->svg_path)->toBe('personal/natanfiuza/'.$asset->id.'.svg')
        ->and($asset->expires_at?->isFuture())->toBeTrue()
        ->and(app(BadgeAssetStorage::class)->read($asset->svg_path))->toContain('>1.2k<');
});

it('nao reaproveita a chamada enquanto o arquivo esta fresco', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $this->get(personalServingUrl($asset))->assertOk();
    $this->get(personalServingUrl($asset))->assertOk();

    Http::assertSentCount(1);
});

it('serve badge estatica sem rede', function () {
    Http::preventStrayRequests();

    $asset = BadgeAsset::factory()->for(personalServingAccount())->static()->create();

    $response = $this->get(personalServingUrl($asset))->assertOk();

    expect((string) $response->getContent())->toContain('>build<')->toContain('>passing<');

    Http::assertNothingSent();
});

it('envia o PAT do dono para item privado sem expor na URL', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->private()->create();

    $this->get(personalServingUrl($asset))->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer ghp_factory_token')
        && ! str_contains($request->url(), 'ghp_factory_token'));
});

it('regenera quando o arquivo vence', function () {
    Http::preventStrayRequests();

    $stars = 1234;

    // `Http::fake` acumula stubs: a resposta muda pelo closure, nao por um
    // segundo fake (o primeiro padrao continuaria vencendo).
    Http::fake([
        'api.github.com/*' => function () use (&$stars) {
            return Http::response(personalServingGithubPayload($stars));
        },
    ]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    expect((string) $this->get(personalServingUrl($asset))->assertOk()->getContent())->toContain('>1.2k<');

    $this->travel(301)->seconds();
    $stars = 10_000;

    expect((string) $this->get(personalServingUrl($asset))->assertOk()->getContent())->toContain('>10k<');

    Http::assertSentCount(2);
});

it('serve o arquivo anterior quando o provider fica fora e o cache nao ajuda', function () {
    Http::preventStrayRequests();

    $offline = false;

    Http::fake([
        'api.github.com/*' => function () use (&$offline) {
            return $offline
                ? Http::response([], 503)
                : Http::response(personalServingGithubPayload());
        },
    ]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $this->get(personalServingUrl($asset))->assertOk();

    // Passa do stale-if-error do cache de provider (24 h) e o provider falha:
    // o pessoal serve o ultimo arquivo bom e avisa que esta velho.
    $this->travel(90_000)->seconds();
    $offline = true;

    $response = $this->get(personalServingUrl($asset))->assertOk();

    expect((string) $response->getContent())->toContain('>1.2k<')
        ->and($response->headers->get('Warning'))->toBe('110 - "Response is Stale"');
});

it('responde fallback 200 quando nunca houve arquivo', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response([], 500)]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $response = $this->get(personalServingUrl($asset))->assertOk();

    expect((string) $response->getContent())->toContain('>stars<')->toContain('>unavailable<')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=60');
});

it('ignora ?refresh=1 na URL publica', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $this->get(personalServingUrl($asset))->assertOk();
    $this->get(personalServingUrl($asset).'?refresh=1')->assertOk();

    Http::assertSentCount(1);
});

it('responde 304 com ETag', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();
    $url = personalServingUrl($asset);

    $etag = (string) $this->get($url)->assertOk()->headers->get('ETag');

    expect($etag)->not->toBe('');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get($url)
        ->assertStatus(304);
});

it('serve PNG e download quando o Imagick esta disponivel', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick ausente neste ambiente.');
    }

    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $png = $this->get(personalServingUrl($asset, ext: 'png'))->assertOk();

    expect($png->headers->get('Content-Type'))->toBe('image/png')
        ->and(substr((string) $png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $download = $this->get(personalServingUrl($asset).'?download=1');

    expect((string) $download->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('aplica o teto de cache das badges pessoais', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    config(['badgeforge.personal_badges.max_cache_seconds' => 120]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->create();

    $response = $this->get(personalServingUrl($asset))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=120')
        ->and($asset->refresh()->expires_at?->getTimestamp())
        ->toBeLessThanOrEqual(now()->addSeconds(120)->getTimestamp());
});

it('degrada para anonimo quando a credencial nao pode ser lida', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalServingGithubPayload())]);

    $asset = BadgeAsset::factory()->for(personalServingAccount())->provider()->private()->create();

    // APP_KEY trocada (ou linha adulterada): o ciphertext nao decifra.
    DB::table('badge_assets')->where('id', $asset->id)->update(['credential' => 'nao-e-ciphertext']);

    $this->get(personalServingUrl($asset))->assertOk();

    Http::assertSent(fn ($request): bool => ! $request->hasHeader('Authorization'));
});

it('responde 404 de recurso no envelope para dono errado, inexistente ou ULID invalido', function () {
    $alice = personalServingAccount('alice');
    $asset = BadgeAsset::factory()->for($alice)->static()->create();

    $this->get(personalServingUrl($asset, 'bob'))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('error.code', 'not_found');

    $asset->delete();

    $this->get(personalServingUrl($asset, 'alice'))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/b/alice/not-a-ulid.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('responde 503 de infraestrutura quando o banco esta fora', function () {
    $alice = personalServingAccount('alice');
    $asset = BadgeAsset::factory()->for($alice)->static()->create();

    Schema::rename('badge_assets', 'badge_assets_gone');

    try {
        $response = $this->get(personalServingUrl($asset, 'alice'))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('error.code', 'badge_unavailable');

        // O Symfony acrescenta `private` ao normalizar; o `no-store` e o que
        // impede CDN e `<img>` de guardarem o erro.
        expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    } finally {
        Schema::rename('badge_assets_gone', 'badge_assets');
    }
});
