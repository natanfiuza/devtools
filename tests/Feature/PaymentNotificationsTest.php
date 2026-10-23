<?php

use App\Actions\Admin\RefundPayment;
use App\Actions\Admin\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Notifications\PaymentRefunded;
use App\Support\Format;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/*
| A conta que pagou recebe a confirmacao; a que teve o pagamento estornado
| recebe o aviso (ADR-044). O e-mail sai no idioma da conta, vai para o
| endereco da conta (nunca para o admin) e e best-effort: o registro fiscal ja
| esta gravado antes do disparo.
*/

function emailDePagamento(): Email
{
    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages->last()->getOriginalMessage();
}

function corpoDoEmailDePagamento(): string
{
    $email = emailDePagamento();

    return (string) $email->getHtmlBody().(string) $email->getTextBody();
}

function pagamentoComAssinatura(
    User $user,
    Plan $plan,
    ?Subscription $subscription = null,
    int $amountCents = 2990,
): Payment {
    $subscription ??= Subscription::factory()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
    ]);

    return Payment::factory()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
        'subscription_id' => $subscription->getKey(),
        'amount_cents' => $amountCents,
    ]);
}

it('confirma o pagamento para a conta que pagou', function () {
    Notification::fake();

    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix);

    Notification::assertSentTo($user, PaymentConfirmed::class);
    Notification::assertNotSentTo($admin, PaymentConfirmed::class);
});

it('envia a confirmacao no idioma da conta', function () {
    Notification::fake();

    $user = User::factory()->create(['locale' => 'fr']);

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix);

    Notification::assertSentTo(
        $user,
        PaymentConfirmed::class,
        fn ($notification, $channels, $notifiable, $locale): bool => $locale === 'fr',
    );
});

it('renderiza a confirmacao no idioma da conta', function () {
    $user = User::factory()->create(['name' => 'Camille', 'locale' => 'fr']);

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Transfer);

    $email = emailDePagamento();
    $corpo = corpoDoEmailDePagamento();

    expect($email->getSubject())->toBe(trans('mail.payment.confirmed.subject', [], 'fr'))
        ->and($corpo)->toContain(trans('mail.payment.confirmed.greeting', ['name' => 'Camille'], 'fr'))
        ->and($corpo)->toContain(trans('account.billing.methods.transfer', [], 'fr'))
        ->and($corpo)->toContain(route('dashboard.billing'))
        ->and($corpo)->toContain('color: #0f7f8a;">_</span>');
});

it('mostra plano e fim do periodo quando o pagamento concede o plano', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix, $plan);

    $subscription = $user->refresh()->activeSubscription();

    expect($subscription)->not->toBeNull();

    $corpo = corpoDoEmailDePagamento();

    app()->setLocale('pt_BR');

    expect($corpo)->toContain(trans('mail.payment.confirmed.plan', ['plan' => $plan->name], 'pt_BR'))
        ->and($corpo)->toContain(trans('mail.payment.confirmed.period_end', [
            'date' => Format::date($subscription->current_period_end),
        ], 'pt_BR'));
});

it('nao menciona plano nem periodo quando o pagamento nao tem plano', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);

    app(RegisterPayment::class)($user, 1990, PaymentMethod::Cash);

    $corpo = corpoDoEmailDePagamento();

    $rotuloPlano = Str::before(trans('mail.payment.confirmed.plan', [], 'pt_BR'), ':plan');
    $rotuloPeriodo = Str::before(trans('mail.payment.confirmed.period_end', [], 'pt_BR'), ':date');

    expect($corpo)->not->toContain($rotuloPlano)
        ->and($corpo)->not->toContain($rotuloPeriodo);
});

it('nao avisa conta anonimizada', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->forceFill(['anonymized_at' => now()])->save();

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix);

    expect(Payment::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('nao avisa conta excluida', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->delete();

    app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix);

    expect(Payment::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('avisa o estorno e diz que a assinatura foi encerrada', function () {
    Notification::fake();

    $user = User::factory()->create();
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $subscription = Subscription::factory()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
    ]);
    $payment = pagamentoComAssinatura($user, $plan, $subscription);

    app(RefundPayment::class)($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled);

    Notification::assertSentTo(
        $user,
        PaymentRefunded::class,
        fn ($notification): bool => $notification->canceledSubscription === true,
    );
});

it('nao diz que encerrou uma assinatura que ja estava encerrada', function () {
    Notification::fake();

    $user = User::factory()->create();
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $subscription = Subscription::factory()->canceled()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
    ]);
    $payment = pagamentoComAssinatura($user, $plan, $subscription);

    app(RefundPayment::class)($payment);

    Notification::assertSentTo(
        $user,
        PaymentRefunded::class,
        fn ($notification): bool => $notification->canceledSubscription === false,
    );
});

it('nao avisa de novo quando o pagamento ja estava estornado', function () {
    Notification::fake();

    $payment = Payment::factory()->refunded()->create();

    app(RefundPayment::class)($payment);

    Notification::assertNothingSent();
});

it('nao avisa o estorno de conta anonimizada', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->forceFill(['anonymized_at' => now()])->save();

    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $payment = pagamentoComAssinatura($user, $plan);

    app(RefundPayment::class)($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
    Notification::assertNothingSent();
});

it('nao avisa o estorno de conta excluida', function () {
    Notification::fake();

    $user = User::factory()->create();

    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $payment = pagamentoComAssinatura($user, $plan);

    $user->delete();

    app(RefundPayment::class)($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
    Notification::assertNothingSent();
});

it('renderiza o estorno com valor, datas e a linha de encerramento', function () {
    $user = User::factory()->create(['name' => 'Camille', 'locale' => 'pt_BR']);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $subscription = Subscription::factory()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
    ]);
    $payment = pagamentoComAssinatura($user, $plan, $subscription, 123456);

    app(RefundPayment::class)($payment);

    $email = emailDePagamento();
    $corpo = corpoDoEmailDePagamento();

    app()->setLocale('pt_BR');

    expect($email->getSubject())->toBe(trans('mail.payment.refunded.subject', [], 'pt_BR'))
        ->and($corpo)->toContain(trans('mail.payment.refunded.greeting', ['name' => 'Camille'], 'pt_BR'))
        ->and($corpo)->toContain(trans('mail.payment.refunded.amount', [
            'amount' => Format::money(123456, $payment->currency),
        ], 'pt_BR'))
        ->and($corpo)->toContain(trans('account.billing.methods.pix', [], 'pt_BR'))
        ->and($corpo)->toContain(Str::before(trans('mail.payment.refunded.paid_at', [], 'pt_BR'), ':date'))
        ->and($corpo)->toContain(Str::before(trans('mail.payment.refunded.refunded_at', [], 'pt_BR'), ':date'))
        ->and($corpo)->toContain(trans('mail.payment.refunded.plan', ['plan' => $plan->name], 'pt_BR'))
        ->and($corpo)->toContain(trans('mail.payment.refunded.subscription_canceled', [], 'pt_BR'))
        ->and($corpo)->toContain(route('dashboard.billing'))
        ->and($corpo)->toContain('color: #0f7f8a;">_</span>');
});

it('nao menciona encerramento quando a assinatura ja estava encerrada', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    $subscription = Subscription::factory()->canceled()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
    ]);
    $payment = pagamentoComAssinatura($user, $plan, $subscription);

    app(RefundPayment::class)($payment);

    $corpo = corpoDoEmailDePagamento();

    expect($corpo)->not->toContain(trans('mail.payment.refunded.subscription_canceled', [], 'pt_BR'));
});

it('nao derruba o registro quando o aviso falha', function () {
    Exceptions::fake();

    $this->app->bind(Dispatcher::class, fn (): Dispatcher => new class implements Dispatcher
    {
        public function send($notifiables, $notification)
        {
            throw new RuntimeException('falha ao enfileirar');
        }

        public function sendNow($notifiables, $notification, ?array $channels = null)
        {
            throw new RuntimeException('falha ao enfileirar');
        }
    });

    $user = User::factory()->create();

    $payment = app(RegisterPayment::class)($user, 2990, PaymentMethod::Pix);

    expect($payment->exists)->toBeTrue()
        ->and(Payment::query()->count())->toBe(1);

    Exceptions::assertReported(RuntimeException::class);
});
