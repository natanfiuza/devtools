<?php

use App\Models\Base64ImageConversion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Limpa pastas de teste
    File::deleteDirectory(storage_path('app/data/base64_images'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/data/base64_images'));
});

it('renderiza a pagina da tool base64-image com toolProps adequadas', function () {
    $this->get('/pt-br/tools/base64-image')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('slug', 'base64-image')
            ->where('toolProps.isAuthenticated', false)
            ->where('toolProps.isPaidPlan', false)
            ->where('toolProps.maxUploadBytes', 2097152)
            ->where('toolProps.downloadThresholdBytes', 2097152)
            ->where('toolProps.retentionHours', 24)
        );
});

it('permite que usuario anonimo converta uma imagem pequena com sucesso sem gravar no banco', function () {
    $file = UploadedFile::fake()->image('test.png', 20, 20);

    $response = $this->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'filename',
            'mime_type',
            'file_size_bytes',
            'base64_size_bytes',
            'direct_download',
            'data_uri',
            'download_url',
            'preview_url',
            'expires_in_hours',
        ]);

    $data = $response->json();
    expect($data['direct_download'])->toBeFalse()
        ->and($data['data_uri'])->toStartWith('data:image/png;base64,')
        ->and($data['expires_in_hours'])->toBe(24);

    // Nao grava no banco para anonimos
    expect(Base64ImageConversion::count())->toBe(0);

    // Grava no disco sob storage/app/data/base64_images/anonymous/
    $txtFile = storage_path("app/data/base64_images/anonymous/{$data['id']}.txt");
    expect(File::exists($txtFile))->toBeTrue();
});

it('registra no banco a conversao de usuario autenticado no plano free e respeita limite de 2mb', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('avatar.jpg', 50, 50);

    $response = $this->actingAs($user)->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect(Base64ImageConversion::count())->toBe(1);

    $record = Base64ImageConversion::first();
    expect($record->user_id)->toBe($user->id)
        ->and($record->is_paid_plan)->toBeFalse()
        ->and($record->filename)->toBe('avatar.jpg')
        ->and($record->isExpired())->toBeFalse();

    // Arquivos no disco sob a pasta do usuario
    $userDir = storage_path("app/data/base64_images/{$user->id}");
    expect(File::exists("{$userDir}/{$data['id']}.txt"))->toBeTrue();
});

it('bloqueia upload maior que 2mb para usuario no plano free', function () {
    $user = User::factory()->create();

    // 2.5 MB (2560 KB)
    $file = UploadedFile::fake()->create('large.png', 2560, 'image/png');

    $response = $this->actingAs($user)->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('validation_error');
});

it('permite upload de ate 25mb para usuario no plano pago e aciona direct_download quando base64 > 2mb', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro-monthly')->firstOrFail();
    $proPlan->update(['max_image_upload_bytes' => 26214400]);

    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    expect($user->effectivePlan()->isFree())->toBeFalse();

    // Imagem de 2.2 MB (2250 KB)
    $file = UploadedFile::fake()->createWithContent('highres.jpg', str_repeat('A', 2250 * 1024));

    $response = $this->actingAs($user)->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['direct_download'])->toBeTrue()
        ->and($data['data_uri'])->toBeNull()
        ->and($data['expires_in_hours'])->toBe(720); // 30 dias

    $record = Base64ImageConversion::first();
    expect($record->is_paid_plan)->toBeTrue();
});

it('lista historico do usuario autenticado com isolamento', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Base64ImageConversion::factory()->create([
        'user_id' => $user1->id,
        'filename' => 'user1_file.png',
    ]);

    Base64ImageConversion::factory()->create([
        'user_id' => $user2->id,
        'filename' => 'user2_file.png',
    ]);

    $response = $this->actingAs($user1)->getJson(route('api.tools.base64-image.history'));

    $response->assertOk();
    $items = $response->json();

    expect($items)->toHaveCount(1)
        ->and($items[0]['filename'])->toBe('user1_file.png');
});

it('permite download do arquivo txt e preview da imagem original', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.png', 10, 10);

    $res = $this->actingAs($user)->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);

    $data = $res->json();

    // Download do .txt
    $dlResponse = $this->actingAs($user)->get(route('tools.base64-image.download', ['id' => $data['id']]));
    $dlResponse->assertOk();
    expect($dlResponse->headers->get('content-disposition'))->toContain('photo.png.base64.txt');

    // Preview
    $previewResponse = $this->actingAs($user)->get(route('tools.base64-image.preview', ['id' => $data['id']]));
    $previewResponse->assertOk();

    // Outro usuario nao pode baixar
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)->get(route('tools.base64-image.download', ['id' => $data['id']]))
        ->assertForbidden();
});

it('permite exclusao manual de conversao no historico', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('to_delete.png', 10, 10);

    $res = $this->actingAs($user)->postJson(route('api.tools.base64-image.convert'), [
        'file' => $file,
    ]);
    $data = $res->json();

    $delResponse = $this->actingAs($user)->deleteJson(route('api.tools.base64-image.destroy', ['id' => $data['id']]));
    $delResponse->assertOk();

    expect(Base64ImageConversion::count())->toBe(0);
});

it('executa a poda horaria removendo arquivos fisicos expirados e mantendo registros no banco', function () {
    $userFree = User::factory()->create();
    $userPaid = User::factory()->create();

    // 1. Free com 25 horas (expirado)
    $freeExpired = Base64ImageConversion::factory()->create([
        'user_id' => $userFree->id,
        'is_paid_plan' => false,
        'created_at' => now()->subHours(25),
    ]);
    File::ensureDirectoryExists(dirname($freeExpired->disk_path_converted));
    File::put($freeExpired->disk_path_converted, 'sample data');
    File::put($freeExpired->disk_path_original, 'sample image');

    // 2. Paid com 15 dias (ainda valido)
    $paidValid = Base64ImageConversion::factory()->paid()->create([
        'user_id' => $userPaid->id,
        'created_at' => now()->subDays(15),
    ]);
    File::ensureDirectoryExists(dirname($paidValid->disk_path_converted));
    File::put($paidValid->disk_path_converted, 'sample data');
    File::put($paidValid->disk_path_original, 'sample image');

    // 3. Paid com 31 dias (expirado)
    $paidExpired = Base64ImageConversion::factory()->paid()->create([
        'user_id' => $userPaid->id,
        'created_at' => now()->subDays(31),
    ]);
    File::ensureDirectoryExists(dirname($paidExpired->disk_path_converted));
    File::put($paidExpired->disk_path_converted, 'sample data');
    File::put($paidExpired->disk_path_original, 'sample image');

    // 4. Arquivo anonimo com mais de 25h no disco
    $anonDir = storage_path('app/data/base64_images/anonymous');
    File::ensureDirectoryExists($anonDir);
    $anonExpiredFile = "{$anonDir}/anon_expired.txt";
    File::put($anonExpiredFile, 'anon data');
    touch($anonExpiredFile, now()->subHours(26)->getTimestamp());

    // Roda comando
    $this->artisan('base64-images:prune')
        ->assertSuccessful();

    // Free expirado: arquivos fisicos removidos, registro no banco mantido
    expect(File::exists($freeExpired->disk_path_converted))->toBeFalse()
        ->and($freeExpired->fresh()->isExpired())->toBeTrue();

    // Paid valido: arquivos fisicos preservados
    expect(File::exists($paidValid->disk_path_converted))->toBeTrue()
        ->and($paidValid->fresh()->isExpired())->toBeFalse();

    // Paid expirado: arquivos fisicos removidos, registro no banco mantido
    expect(File::exists($paidExpired->disk_path_converted))->toBeFalse()
        ->and($paidExpired->fresh()->isExpired())->toBeTrue();

    // Anonimo expirado: arquivo removido
    expect(File::exists($anonExpiredFile))->toBeFalse();
});
