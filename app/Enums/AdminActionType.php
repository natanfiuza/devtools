<?php

namespace App\Enums;

/**
 * O que foi feito no painel administrativo (tabela `admin_actions`).
 *
 * O valor e a chave de traducao na tela de auditoria (`admin.actions.<valor>`),
 * entao ele nao muda sem mudar o texto nos 5 idiomas.
 */
enum AdminActionType: string
{
    case UserVerified = 'user.verified';
    case UserVerificationResent = 'user.verification_resent';
    case UserPasswordResetSent = 'user.password_reset_sent';
    case UserRoleGranted = 'user.role_granted';
    case UserRoleRevoked = 'user.role_revoked';
    case UserSuspended = 'user.suspended';
    case UserUnsuspended = 'user.unsuspended';
    case UserPlanGranted = 'user.plan_granted';
    case UserQuotaGranted = 'user.quota_granted';
    case UserDeleted = 'user.deleted';
    case UserImpersonated = 'user.impersonated';
    case PlanUpdated = 'plan.updated';
    case OutputTargetUpdated = 'output_target.updated';
    case AiCachePruned = 'ai_cache.pruned';
    case PaymentRegistered = 'payment.registered';
    case PaymentRefunded = 'payment.refunded';
    case SubscriberRemoved = 'subscriber.removed';
    case SubscriberResubscribed = 'subscriber.resubscribed';
    case SubscriptionIntentFulfilled = 'intent.fulfilled';
    case SubscriptionIntentCanceled = 'intent.canceled';
    case UserRestored = 'user.restored';
}
