<?php

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Aba de doacoes do painel (F6).
|
| A pagina publica de status esconde nome e e-mail do doador; aqui o admin ve
| tudo. O filtro de situacao enxerga a expiracao preguicosa do Pix dinamico, e
| o Pix estatico nao expira - as duas regras precisam valer na lista, e nao so
| na pagina publica.
*/

it('abre em pagamentos por padrao e so carrega doacoes na aba delas', function () {
    $admin = User::factory()->admin()->create();
    Donation::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Payments')
            ->where('tab', 'payments')
            ->where('donations', null)
            ->has('payments.data', 0));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Payments')
            ->where('tab', 'donations')
            ->where('payments', null)
            ->has('donations.data', 1));
});

it('mostra os dados que a pagina publica nao mostra', function () {
    $admin = User::factory()->admin()->create();
    $donation = Donation::factory()->create([
        'name' => 'Maria Doadora',
        'email' => 'maria@exemplo.com',
        'gateway_status' => 'rejected',
        'gateway_status_detail' => 'rejected_by_bank',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donations.data.0.ulid', $donation->ulid)
            ->where('donations.data.0.donor', 'Maria Doadora')
            ->where('donations.data.0.email', 'maria@exemplo.com')
            ->where('donations.data.0.status', DonationStatus::Pending->value)
            ->where('donations.data.0.gateway', 'mercadopago')
            // O codigo cru e do admin; a pagina publica so ve o motivo traduzido.
            ->where('donations.data.0.gatewayStatus', 'rejected')
            ->where('donations.data.0.gatewayStatusDetail', 'rejected_by_bank')
            ->where('donations.data.0.qrCode', $donation->qr_code));
});

it('busca por nome, e-mail ou identificador', function () {
    $admin = User::factory()->admin()->create();
    Donation::factory()->create(['name' => 'Ana']);
    $bruno = Donation::factory()->create([
        'name' => 'Bruno',
        'email' => 'bruno@exemplo.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations', 'q' => 'Bruno']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.donor', 'Bruno'));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations', 'q' => 'bruno@exemplo.com']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.ulid', $bruno->ulid));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations', 'q' => $bruno->ulid]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.ulid', $bruno->ulid));
});

it('trata a expiracao preguicosa no filtro de situacao', function () {
    $admin = User::factory()->admin()->create();

    Donation::factory()->create();
    $vencida = Donation::factory()->create(['expires_at' => now()->subMinute()]);
    $estatica = Donation::factory()->staticPix()->create(['expires_at' => now()->subMinute()]);

    // A lista mostra a vencida como expirada mesmo antes de `payments:expire`
    // rodar: a leitura e preguicosa, igual a da pagina publica.
    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations', 'status' => 'expired']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.ulid', $vencida->ulid)
            ->where('donations.data.0.status', DonationStatus::Expired->value));

    // A pendente vencida nao aparece como pendente, e o Pix estatico, que nao
    // tem prazo, continua pendente para sempre.
    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['tab' => 'donations', 'status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('donations.data', 2));
});

it('recusa a aba para quem nao e admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.payments.index', ['tab' => 'donations']))
        ->assertForbidden();
});
