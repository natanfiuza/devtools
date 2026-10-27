<?php

use App\Models\SharedText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    File::deleteDirectory(storage_path('app/data/sharetext'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/data/sharetext'));
});

it('renderiza a pagina da tool share-text com toolProps adequadas', function () {
    $this->get('/pt-br/tools/share-text')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('slug', 'share-text')
            ->where('toolProps.isAuthenticated', false)
            ->where('toolProps.isPaidPlan', false)
            ->where('toolProps.maxUploadBytes', 1048576)
            ->where('toolProps.retentionHours', 72)
            ->where('toolProps.initialDocument', null)
        );
});

it('salva texto anonimo via POST /documents com limite de 1MB e expiracao de 72h', function () {
    $response = $this->post('/documents', [
        'data' => 'Console.log("Hello from tests");',
        'extension' => 'js',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['key']);

    $key = $response->json('key');
    expect($key)->toBeString()->toHaveLength(26);

    $record = SharedText::find($key);
    expect($record)->not->toBeNull()
        ->and($record->user_id)->toBeNull()
        ->and($record->extension)->toBe('js')
        ->and($record->size_bytes)->toBe(strlen('Console.log("Hello from tests");'))
        ->and($record->expires_at)->not->toBeNull();

    $expectedPath = storage_path("app/data/sharetext/anonymous/{$key}.txt");
    expect(File::exists($expectedPath))->toBeTrue()
        ->and(File::get($expectedPath))->toBe('Console.log("Hello from tests");');
});

it('aceita payload de texto cru no corpo de POST /documents (compatibilidade Hastebin CLI)', function () {
    $content = 'SELECT * FROM users WHERE active = 1;';

    $response = $this->call('POST', '/documents', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_ACCEPT' => 'application/json',
    ], $content);

    $response->assertCreated();
    $key = $response->json('key');

    $record = SharedText::find($key);
    expect($record)->not->toBeNull()
        ->and($record->getContent())->toBe($content);
});

it('salva texto autenticado permanentemente e vinculado a conta', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/documents', [
        'data' => 'echo "Permanent storage test";',
        'extension' => 'php',
    ]);

    $response->assertCreated();
    $key = $response->json('key');

    $record = SharedText::find($key);
    expect($record)->not->toBeNull()
        ->and($record->user_id)->toBe($user->id)
        ->and($record->expires_at)->toBeNull();

    $userPath = storage_path("app/data/sharetext/{$user->id}/{$key}.txt");
    expect(File::exists($userPath))->toBeTrue()
        ->and(File::get($userPath))->toBe('echo "Permanent storage test";');
});

it('rejeita texto vazio em POST /documents', function () {
    $response = $this->postJson('/documents', [
        'data' => '   ',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data']);
});

it('bloqueia texto que excede o limite maximo de tamanho', function () {
    // Para anonimos o limite e 1 MB
    $largeString = str_repeat('A', 1024 * 1024 + 10);

    $response = $this->postJson('/documents', [
        'data' => $largeString,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data']);
});

it('retorna documento json via GET /documents/{id}', function () {
    $text = SharedText::factory()->anonymous()->create();
    File::ensureDirectoryExists(dirname($text->file_path));
    File::put($text->file_path, 'Sample content');

    $response = $this->getJson("/documents/{$text->id}");

    $response->assertOk()
        ->assertJson([
            'key' => $text->id,
            'data' => 'Sample content',
        ]);
});

it('retorna texto cru seguro via GET /raw/{id}', function () {
    $text = SharedText::factory()->anonymous()->create();
    File::ensureDirectoryExists(dirname($text->file_path));
    File::put($text->file_path, 'RAW_TEXT_OUTPUT');

    $response = $this->get("/raw/{$text->id}");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->getContent())->toBe('RAW_TEXT_OUTPUT');
});

it('carrega o documento na pagina web quando ID e informado', function () {
    $text = SharedText::factory()->anonymous()->create([
        'extension' => 'sql',
    ]);
    File::ensureDirectoryExists(dirname($text->file_path));
    File::put($text->file_path, 'SELECT 1;');

    $this->get("/pt-br/tools/share-text/{$text->id}.sql")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('slug', 'share-text')
            ->where('toolProps.initialDocument.key', $text->id)
            ->where('toolProps.initialDocument.data', 'SELECT 1;')
            ->where('toolProps.initialDocument.extension', 'sql')
        );
});

it('retorna 404 na pagina web para documento expirado', function () {
    $text = SharedText::factory()->expired()->create();

    $this->get("/pt-br/tools/share-text/{$text->id}")
        ->assertNotFound();
});

it('redireciona a rota curta /share/{id} para a ferramenta no idioma preferido', function () {
    $text = SharedText::factory()->anonymous()->create();
    File::ensureDirectoryExists(dirname($text->file_path));
    File::put($text->file_path, 'Short redirect test');

    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')
        ->get("/share/{$text->id}")
        ->assertRedirect("/pt-br/tools/share-text/{$text->id}");
});

it('permite que usuario autenticado veja e exclua seus textos do historico', function () {
    $user = User::factory()->create();

    $text = SharedText::factory()->create([
        'user_id' => $user->id,
    ]);
    File::ensureDirectoryExists(dirname($text->file_path));
    File::put($text->file_path, 'To be deleted');

    $historyResponse = $this->actingAs($user)->getJson('/api/tools/share-text/history');
    $historyResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $text->id);

    $deleteResponse = $this->actingAs($user)->deleteJson("/api/tools/share-text/{$text->id}");
    $deleteResponse->assertOk();

    expect(SharedText::find($text->id))->toBeNull()
        ->and(File::exists($text->file_path))->toBeFalse();
});

it('poda textos anonimos expirados pelo comando share-text:prune', function () {
    $expired = SharedText::factory()->expired()->create([
        'user_id' => null,
        'file_path' => storage_path('app/data/sharetext/anonymous/EXPIRED.txt'),
    ]);
    File::ensureDirectoryExists(dirname($expired->file_path));
    File::put($expired->file_path, 'Old data');

    $active = SharedText::factory()->anonymous()->create([
        'file_path' => storage_path('app/data/sharetext/anonymous/ACTIVE.txt'),
    ]);
    File::ensureDirectoryExists(dirname($active->file_path));
    File::put($active->file_path, 'Fresh data');

    $this->artisan('share-text:prune')
        ->assertSuccessful();

    expect(SharedText::find($expired->id))->toBeNull()
        ->and(File::exists($expired->file_path))->toBeFalse()
        ->and(SharedText::find($active->id))->not->toBeNull()
        ->and(File::exists($active->file_path))->toBeTrue();
});
