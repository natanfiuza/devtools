<?php

use App\Models\BadgeCustomLogo;
use App\Models\User;
use App\Support\BadgeForge\CustomLogoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Resolucao publica de `?logo=u_<ULID>` (ADR-078).
|
| O endpoint de imagem continua anonimo e nunca quebra por causa do logo:
| slug invalido, registro apagado, JSON corrompido ou banco fora caem em
| "sem logo" com 200. Embutidos nao consultam a tabela.
*/

function customLogo(): BadgeCustomLogo
{
    return BadgeCustomLogo::factory()->create([
        'user_id' => User::factory(),
        'paths' => [['d' => 'M12 2L2 22h20z']],
    ]);
}

function customLogoRepository(): CustomLogoRepository
{
    return app(CustomLogoRepository::class);
}

it('resolve o slug opaco para o logo do banco', function () {
    $logo = BadgeCustomLogo::factory()->multipath()->create();

    $resolved = customLogoRepository()->find('u_'.$logo->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->slug)->toBe('u_'.$logo->id)
        ->and($resolved?->custom)->toBeTrue()
        ->and($resolved?->viewBox)->toBe('0 0 24 24')
        ->and($resolved?->paths)->toHaveCount(2)
        ->and($resolved?->paths[0]->d)->toBe('M2 2h20v20H2z')
        ->and($resolved?->paths[1]->clipRule)->toBe('evenodd');
});

it('aceita o slug em caixa baixa, com espacos e ULID canônico', function () {
    $logo = BadgeCustomLogo::factory()->create();

    expect(customLogoRepository()->find('  u_'.strtolower($logo->id).'  '))->not->toBeNull();
});

it('devolve null para slug que nao seja u_<ULID>', function (mixed $slug) {
    expect(customLogoRepository()->find($slug))->toBeNull();
})->with([
    'texto' => ['github'],
    'sem prefixo' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
    'ulid curto' => ['u_123'],
    'ulid com letra proibida' => ['u_01ARZ3NDEKTSV4RRFFQ69G5FAI'],
    'array' => [['u_01ARZ3NDEKTSV4RRFFQ69G5FAV']],
    'null' => [null],
]);

it('devolve null para ULID inexistente', function () {
    expect(customLogoRepository()->find('u_01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();
});

it('memoiza a busca, inclusive o nao encontrado', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $repository = customLogoRepository();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $repository->find('u_'.$logo->id);
    $repository->find('u_'.$logo->id);
    $repository->find('u_01ARZ3NDEKTSV4RRFFQ69G5FAV');
    $repository->find('u_01ARZ3NDEKTSV4RRFFQ69G5FAV');

    $queries = array_filter(
        DB::getQueryLog(),
        static fn (array $query): bool => str_contains((string) $query['query'], 'badge_custom_logos'),
    );

    DB::disableQueryLog();

    expect($queries)->toHaveCount(2);
});

it('revalida o JSON na leitura e ignora linha corrompida', function () {
    $logo = BadgeCustomLogo::factory()->create();

    DB::table('badge_custom_logos')->where('id', $logo->id)->update(['paths' => 'not-json']);
    expect(customLogoRepository()->find('u_'.$logo->id))->toBeNull();

    DB::table('badge_custom_logos')->where('id', $logo->id)->update([
        'paths' => json_encode([['d' => '<script>']]),
    ]);
    expect(customLogoRepository()->find('u_'.$logo->id))->toBeNull();

    DB::table('badge_custom_logos')->where('id', $logo->id)->update([
        'paths' => json_encode([['d' => 'M0 0h10v10H0z', 'fillRule' => 'inherit']]),
    ]);
    expect(customLogoRepository()->find('u_'.$logo->id))->toBeNull();

    DB::table('badge_custom_logos')->where('id', $logo->id)->update(['view_box' => '0 0 0 24']);
    expect(customLogoRepository()->find('u_'.$logo->id))->toBeNull();
});

it('nao derruba o endpoint quando a tabela nao existe', function () {
    Schema::drop('badge_custom_logos');

    expect(customLogoRepository()->find('u_01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();

    $this->get('/api/badges/static/build/passing.svg?logo=u_01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
});

it('renderiza o logo personalizado na imagem publica', function () {
    $logo = BadgeCustomLogo::factory()->multipath()->create();

    $svg = (string) $this->get('/api/badges/static/build/passing.svg?logo=u_'.$logo->id)
        ->assertOk()
        ->getContent();

    expect($svg)->toContain('viewBox="0 0 24 24"')
        ->and($svg)->toContain('<path d="M2 2h20v20H2z"')
        ->and($svg)->toContain('fill-rule="evenodd"')
        ->and($svg)->toContain('clip-rule="evenodd"');
});

it('cai em "sem logo" com 200 para logo desconhecido ou malformado', function () {
    $default = (string) $this->get('/api/badges/static/build/passing.svg')->getContent();

    foreach (['u_01ARZ3NDEKTSV4RRFFQ69G5FAV', 'u_nope', 'u_', 'u_<script>'] as $slug) {
        $this->get('/api/badges/static/build/passing.svg?logo='.urlencode($slug))
            ->assertOk()
            ->assertContent($default);
    }
});

it('cai em "sem logo" quando o logo apagado nao existe mais', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $url = '/api/badges/static/build/passing.svg?logo=u_'.$logo->id;

    $withLogo = (string) $this->get($url)->getContent();

    $logo->delete();

    $withoutLogo = (string) $this->get($url)->getContent();

    expect($withLogo)->not->toBe($withoutLogo)
        ->and($withoutLogo)->toBe((string) $this->get('/api/badges/static/build/passing.svg')->getContent());
});

it('muda o ETag quando o logo muda por dentro', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $url = '/api/badges/static/build/passing.svg?logo=u_'.$logo->id;

    $first = (string) $this->get($url)->headers->get('ETag');

    $logo->forceFill(['paths' => [['d' => 'M2 2h20v20H2z']]])->save();

    $second = (string) $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

it('limita o cache do badge com logo personalizado', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $url = '/api/badges/static/build/passing.svg?logo=u_'.$logo->id;

    $this->get($url.'&cacheSeconds=86400')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public');

    $this->get($url.'&cacheSeconds=600')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=600, public');
});

it('nao consulta a tabela para logos embutidos ou ausentes', function (string $query) {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get('/api/badges/static/build/passing.svg'.$query)->assertOk();

    $queries = array_filter(
        DB::getQueryLog(),
        static fn (array $entry): bool => str_contains((string) $entry['query'], 'badge_custom_logos'),
    );

    DB::disableQueryLog();

    expect($queries)->toBe([]);
})->with([
    'sem logo' => [''],
    'logo embutido' => ['?logo=github'],
    'logo desconhecido' => ['?logo=nope'],
]);

it('mantem o teto normal de cache para logos embutidos', function () {
    $this->get('/api/badges/static/build/passing.svg?logo=github&cacheSeconds=86400')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, public');
});
