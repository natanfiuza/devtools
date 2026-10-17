<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\SubscriptionIntentController;
use App\Http\Controllers\Admin\UserActionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Painel administrativo (ADR-025).
|
| Sem prefixo de idioma, como /api e /admin sempre foram no SPEC: o painel nao
| tem SEO nem link publico, entao o idioma vem negociado do cookie, da conta e
| do cabecalho (ResolveLocale). As telas, essas sim, sao traduzidas nos cinco
| idiomas.
|
| A porta e o Gate `access-admin`; o que se pode fazer sobre cada conta e a
| UserPolicy.
*/

// Sair da impersonation fica FORA do grupo do painel: durante a impersonation a
// sessao e a do alvo, que nao e admin, e por isso nao passaria pelo Gate - o
// que e proposital (o painel fica inacessivel enquanto se esta como outro).
Route::delete('impersonation', [ImpersonationController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('impersonation.destroy');

Route::middleware(['auth', 'verified', 'active', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Contas.
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

        // As nove acoes sobre uma conta, uma rota cada: o que muda de uma para
        // a outra e a autorizacao, e ela fica explicita no controlador.
        Route::post('users/{user}/verify-email', [UserActionController::class, 'verifyEmail'])
            ->name('users.verify-email');
        Route::post('users/{user}/resend-verification', [UserActionController::class, 'resendVerification'])
            ->name('users.resend-verification');
        Route::post('users/{user}/send-password-reset', [UserActionController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset');
        Route::post('users/{user}/role', [UserActionController::class, 'updateRole'])
            ->name('users.role');
        Route::post('users/{user}/suspend', [UserActionController::class, 'suspend'])
            ->name('users.suspend');
        Route::post('users/{user}/unsuspend', [UserActionController::class, 'unsuspend'])
            ->name('users.unsuspend');
        Route::post('users/{user}/plan', [UserActionController::class, 'grantPlan'])
            ->name('users.plan');
        Route::post('users/{user}/quota', [UserActionController::class, 'grantQuota'])
            ->name('users.quota');
        Route::delete('users/{user}', [UserActionController::class, 'destroy'])
            ->name('users.destroy');

        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])
            ->name('users.impersonate');

        // Assinantes da newsletter.
        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        // Exportacao vem antes do {subscriber} para nao ser lida como id.
        Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
        Route::post('subscribers/{subscriber}/resubscribe', [SubscriberController::class, 'resubscribe'])
            ->name('subscribers.resubscribe');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])
            ->name('subscribers.destroy');

        // Catalogo e cobranca.
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');

        Route::get('intents', [SubscriptionIntentController::class, 'index'])->name('intents.index');
        Route::post('intents/{intent}/fulfill', [SubscriptionIntentController::class, 'fulfill'])
            ->name('intents.fulfill');
        Route::post('intents/{intent}/cancel', [SubscriptionIntentController::class, 'cancel'])
            ->name('intents.cancel');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->name('payments.refund');

        Route::get('audit', AuditController::class)->name('audit.index');
    });
