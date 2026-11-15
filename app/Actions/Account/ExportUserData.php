<?php

namespace App\Actions\Account;

use App\Exceptions\Ai\PayloadCodecException;
use App\Models\AiRequest;
use App\Models\BadgeAsset;
use App\Models\BadgeCustomLogo;
use App\Models\BadgeEndpoint;
use App\Models\BadgePreset;
use App\Models\ColorCommentReport;
use App\Models\ColorFavorite;
use App\Models\ColorHistoryEntry;
use App\Models\ColorNotificationPreference;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\ColorPaletteFavorite;
use App\Models\ColorPaletteLike;
use App\Models\ColorPaletteMute;
use App\Models\ColorPreset;
use App\Models\ColorTag;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\SubscriptionIntent;
use App\Models\UsageLedger;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Ai\PayloadCodec;

/**
 * Tudo o que o sistema guarda sobre uma conta, num array pronto para virar
 * JSON (ADR-028).
 *
 * O direito de levar os proprios dados e o par do direito de apagar: quem
 * exclui a conta baixa isto antes. O que sai e o dado da pessoa e o que ele
 * gerou; o que nao sai e segredo de acesso - senha (que nem existe em claro),
 * `remember_token`, token de sessao e o token de gerenciamento da inscricao na
 * newsletter. Exportar credencial seria criar uma copia dela fora do lugar
 * onde ela e protegida.
 *
 * As datas saem em ISO 8601 (e nao no formato da tela): o arquivo e para ser
 * lido por outra maquina, e nao pelo idioma de quem baixou.
 */
final class ExportUserData
{
    public function __construct(private readonly PayloadCodec $codec) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'locale' => $user->locale,
                'appearance' => $user->appearance,
                'history_enabled' => (bool) $user->history_enabled,
                'api_requests_count' => (int) $user->api_requests_count,
                'api_sql_queries_count' => (int) $user->api_sql_queries_count,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'social_accounts' => $user->socialAccounts()
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'provider' => $account->provider,
                    'linked_at' => $account->created_at?->toIso8601String(),
                ])
                ->all(),
            'newsletter' => $this->newsletter($user),
            'subscriptions' => $user->subscriptions()
                ->with('plan')
                ->orderBy('id')
                ->get()
                ->map(fn (Subscription $subscription): array => [
                    'plan' => $subscription->plan->name,
                    'gateway' => $subscription->gateway,
                    'status' => $subscription->status->value,
                    'start' => $subscription->current_period_start?->toIso8601String(),
                    'end' => $subscription->current_period_end?->toIso8601String(),
                ])
                ->all(),
            'subscription_intents' => $user->subscriptionIntents()
                ->with('plan')
                ->orderBy('id')
                ->get()
                ->map(fn (SubscriptionIntent $intent): array => [
                    'plan' => $intent->plan->name,
                    'interval' => $intent->plan->interval,
                    'username' => $intent->username,
                    'phone' => $intent->phone,
                    'document' => $intent->document,
                    'payment_method' => $intent->payment_method->value,
                    'notes' => $intent->notes,
                    'status' => $intent->status->value,
                    'created_at' => $intent->created_at?->toIso8601String(),
                ])
                ->all(),
            'payments' => $user->payments()
                ->with('plan')
                ->orderBy('id')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'plan' => $payment->plan?->name,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'method' => $payment->method->value,
                    'status' => $payment->status->value,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'note' => $payment->note,
                ])
                ->all(),
            // O historico de consultas: e o que a pessoa gerou (ADR-028). O
            // schema e a descricao so aparecem enquanto o payload canonico
            // ainda esta no banco - depois da liquidacao ele e limpo e o que
            // resta e o resultado.
            'ai_requests' => $user->aiRequests()
                ->with('outputTarget')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (AiRequest $request): array => $this->aiRequest($request))
                ->all(),
            // BadgeForge: o logo e o SVG normalizado que a pessoa enviou (o
            // arquivo cru nunca foi gravado) e o preset e o formulario que ela
            // salvou. Ver ADR-078 e ADR-079.
            'badgeforge' => [
                'custom_logos' => $user->badgeCustomLogos()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (BadgeCustomLogo $logo): array => [
                        'name' => $logo->name,
                        'slug' => $logo->slug(),
                        'view_box' => $logo->view_box,
                        'paths' => $logo->paths,
                        'size_bytes' => $logo->size_bytes,
                        'created_at' => $logo->created_at?->toIso8601String(),
                    ])
                    ->all(),
                'presets' => $user->badgePresets()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (BadgePreset $preset): array => [
                        'name' => $preset->name,
                        'form' => $preset->payload,
                        'created_at' => $preset->created_at?->toIso8601String(),
                    ])
                    ->all(),
                // Badges pessoais (F4, ADR-081): metadados e URLs publicas.
                // O PAT cifrado e o fingerprint nunca saem - exportar
                // credencial seria criar uma copia fora do lugar protegido.
                'badges' => $user->badgeAssets()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (BadgeAsset $asset): array => [
                        'name' => $asset->name,
                        'type' => $asset->type,
                        'private' => (bool) $asset->private,
                        'provider' => $asset->provider_slug,
                        'definition' => $asset->definition_slug,
                        'params' => $asset->params,
                        'form' => $asset->form,
                        'credential_set' => $asset->credentialSet(),
                        'credential_username' => $asset->credential_username,
                        'url' => $asset->publicSvgUrl(),
                        'png_url' => $asset->publicPngUrl(),
                        'last_generated_at' => $asset->last_generated_at?->toIso8601String(),
                        'expires_at' => $asset->expires_at?->toIso8601String(),
                        'created_at' => $asset->created_at?->toIso8601String(),
                    ])
                    ->all(),
                // Endpoints JSON (F5, ADR-083): o dono recebe a URL de origem
                // que ele mesmo cadastrou - e o dado dele - e as URLs publicas.
                // Nenhum corpo de origem e guardado para exportar.
                'endpoints' => $user->badgeEndpoints()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (BadgeEndpoint $endpoint): array => [
                        'name' => $endpoint->name,
                        'url' => $endpoint->url,
                        'svg_url' => $endpoint->publicSvgUrl(),
                        'png_url' => $endpoint->publicPngUrl(),
                        'created_at' => $endpoint->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            // Colors (F1 a F7): paletas, biblioteca pessoal, relações sociais
            // e o que a conta escreveu. O bloco existe para que o direito de
            // levar os proprios dados (ADR-028) cubra o grupo inteiro.
            'colors' => $this->colors($user),
            // O livro-razao do consumo: e da conta, e nao estado do sistema.
            'usage' => $user->usageLedger()
                ->orderBy('id')
                ->get()
                ->map(fn (UsageLedger $entry): array => [
                    'type' => $entry->type->value,
                    'tokens' => $entry->tokens,
                    'uses' => $entry->uses,
                    'at' => $entry->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Dados do grupo Colors que pertencem a conta (F8, ADR-144).
     *
     * Ficam de fora o `moderation_reason` (nota interna da moderacao) e os
     * seguidores (a linha e a acao de outra conta). Curtidas e follows so
     * aparecem quando estao ativos; comentario excluido entra, porque o texto
     * continua sendo do autor.
     *
     * @return array<string, mixed>
     */
    private function colors(User $user): array
    {
        $preferences = ColorNotificationPreference::for($user);

        return [
            'palettes' => $user->colorPalettes()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPalette $palette): array => [
                    'id' => $palette->id,
                    'name' => $palette->name,
                    'description' => $palette->description,
                    'slug' => $palette->slug,
                    'kind' => $palette->kind->value,
                    'visibility' => $palette->visibility->value,
                    'archived_at' => $palette->archived_at?->toIso8601String(),
                    'colors' => $palette->colors,
                    'roles' => $palette->roles,
                    'source' => $palette->source,
                    'colors_count' => $palette->colors_count,
                    'duplications_count' => $palette->duplications_count,
                    'algorithm_version' => $palette->algorithm_version,
                    'created_at' => $palette->created_at?->toIso8601String(),
                    'updated_at' => $palette->updated_at?->toIso8601String(),
                ])
                ->all(),
            'tags' => $user->colorTags()
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (ColorTag $tag): array => [
                    'name' => $tag->name,
                    'key' => $tag->key,
                    'position' => $tag->position,
                ])
                ->all(),
            'history' => $user->colorHistoryEntries()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorHistoryEntry $entry): array => [
                    'color' => $entry->color,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->all(),
            'favorites' => $user->colorFavorites()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorFavorite $favorite): array => [
                    'color' => $favorite->color,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])
                ->all(),
            'presets' => $user->colorPresets()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPreset $preset): array => [
                    'name' => $preset->name,
                    'base' => $preset->base,
                    'created_at' => $preset->created_at?->toIso8601String(),
                ])
                ->all(),
            'palette_favorites' => $user->favoritePalettes()
                ->with('palette')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPaletteFavorite $favorite): array => [
                    'palette_id' => $favorite->color_palette_id,
                    'palette_slug' => $favorite->palette->slug,
                    'palette_name' => $favorite->palette->name,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])
                ->all(),
            'likes' => $user->paletteLikes()
                ->whereNotNull('liked_at')
                ->with('palette')
                ->orderBy('liked_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPaletteLike $like): array => [
                    'palette_id' => $like->color_palette_id,
                    'palette_slug' => $like->palette->slug,
                    'palette_name' => $like->palette->name,
                    'liked_at' => $like->liked_at?->toIso8601String(),
                ])
                ->all(),
            // O username vem do join: a conta seguida pode estar em soft
            // delete e o vinculo continua existindo, entao a relacao
            // `followed` resolveria nula mesmo com a linha presente.
            'following' => $user->following()
                ->whereNotNull('user_follows.followed_at')
                ->join('users', 'users.id', '=', 'user_follows.followed_id')
                ->orderBy('user_follows.followed_at')
                ->orderBy('user_follows.id')
                ->select(['user_follows.followed_at', 'users.username'])
                ->get()
                ->map(fn (UserFollow $follow): array => [
                    'username' => $follow->getAttribute('username'),
                    'followed_at' => $follow->followed_at?->toIso8601String(),
                ])
                ->all(),
            'comments' => $user->colorPaletteComments()
                ->withTrashed()
                ->with('palette')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPaletteComment $comment): array => [
                    'id' => $comment->id,
                    'palette_id' => $comment->color_palette_id,
                    'palette_slug' => $comment->palette->slug,
                    'body' => $comment->body,
                    'approved_body' => $comment->approved_body,
                    'status' => $comment->status->value,
                    'locale' => $comment->locale,
                    'revision' => $comment->revision,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'edited_at' => $comment->edited_at?->toIso8601String(),
                    'deleted_at' => $comment->deleted_at?->toIso8601String(),
                ])
                ->all(),
            'reports_filed' => $user->colorCommentReports()
                ->with('comment.palette')
                ->orderBy('reported_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorCommentReport $report): array => [
                    'comment_id' => $report->comment_id,
                    'palette_slug' => $report->comment->palette->slug,
                    'reason' => $report->reason->value,
                    'status' => $report->status->value,
                    'reported_at' => $report->reported_at->toIso8601String(),
                    'resolved_at' => $report->resolved_at?->toIso8601String(),
                ])
                ->all(),
            'notification_preferences' => [
                'email_on_like' => (bool) $preferences->email_on_like,
                'email_on_follow' => (bool) $preferences->email_on_follow,
                'email_on_comment' => (bool) $preferences->email_on_comment,
                'email_on_reply' => (bool) $preferences->email_on_reply,
            ],
            'muted_palettes' => $user->colorPaletteMutes()
                ->with('palette')
                ->orderBy('id')
                ->get()
                ->map(fn (ColorPaletteMute $mute): array => [
                    'palette_slug' => $mute->palette->slug,
                    'palette_name' => $mute->palette->name,
                ])
                ->all(),
        ];
    }

    /**
     * Uma consulta do historico, sem credencial e sem o prompt montado.
     *
     * @return array<string, mixed>
     */
    private function aiRequest(AiRequest $request): array
    {
        $payload = null;

        if ($request->canonical_payload !== null) {
            try {
                $payload = $this->codec->decode($request->canonical_payload);
            } catch (PayloadCodecException) {
                // Payload corrompido nao pode derrubar o resto da exportacao.
                $payload = null;
            }
        }

        return [
            'id' => $request->id,
            'tool' => $request->outputTarget->label,
            'tool_slug' => $request->outputTarget->slug,
            'schema_source' => $request->schema_source->value,
            'dialect' => $request->dialect->value,
            'status' => $request->status->value,
            'schema' => $payload?->schema,
            'description' => $payload?->description,
            'result' => $request->result(),
            'tokens_in' => $request->tokens_in,
            'tokens_out' => $request->tokens_out,
            'cache_hit' => (bool) $request->cache_hit,
            'error_code' => $request->error_code?->value,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    /**
     * A inscricao na newsletter e identificada pelo e-mail: sem ele nao ha o
     * que procurar, e sem a inscricao o campo sai nulo.
     *
     * @return array<string, mixed>|null
     */
    private function newsletter(User $user): ?array
    {
        if ($user->email === '') {
            return null;
        }

        $subscriber = NewsletterSubscriber::query()
            ->where('email', $user->email)
            ->first();

        if ($subscriber === null) {
            return null;
        }

        return [
            'email' => $subscriber->email,
            'locale' => $subscriber->locale,
            'source' => $subscriber->source,
            'confirmed_at' => $subscriber->confirmed_at?->toIso8601String(),
            'unsubscribed_at' => $subscriber->unsubscribed_at?->toIso8601String(),
        ];
    }
}
