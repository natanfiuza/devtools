<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\UpdateColorNotificationPreferencesRequest;
use App\Models\ColorNotificationPreference;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sino e preferencias de notificacao da conta (F7, ADR-142).
 *
 * A pagina e unica: a lista paginada e os quatro toggles de e-mail. O canal
 * `database` e sempre ligado - o que se desliga e o e-mail de cada tipo. O
 * payload entregue ao frontend so leva ids, nome/slug da paleta, nome e
 * username do ator: nunca o corpo do comentario, e-mail ou IP.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $preferences = ColorNotificationPreference::for($user);

        $paginator = $user->notifications()
            ->latest()
            ->paginate(max(1, (int) config('colors.notifications.per_page', 20)))
            ->through(fn (DatabaseNotification $notification): array => $this->row($notification));

        return Inertia::render('dashboard/Notifications', [
            'notifications' => $paginator,
            'preferences' => [
                'email_on_like' => (bool) $preferences->email_on_like,
                'email_on_follow' => (bool) $preferences->email_on_follow,
                'email_on_comment' => (bool) $preferences->email_on_comment,
                'email_on_reply' => (bool) $preferences->email_on_reply,
            ],
        ]);
    }

    public function updatePreferences(UpdateColorNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        ColorNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            $request->validated(),
        );

        return back();
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();

        // Notificacao de outra conta responde 404, como qualquer recurso que
        // nao pertence a quem pediu.
        $user->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'kind' => $this->text($data['kind'] ?? null) ?? 'unknown',
            'read' => $notification->read_at !== null,
            'created_at' => Format::dateTime($notification->created_at) ?? '',
            'data' => [
                'palette_id' => $this->text($data['palette_id'] ?? null),
                'palette_name' => $this->text($data['palette_name'] ?? null),
                'palette_slug' => $this->text($data['palette_slug'] ?? null),
                'comment_id' => $this->text($data['comment_id'] ?? null),
                'actor_name' => $this->text($data['actor_name'] ?? null),
                'actor_username' => $this->text($data['actor_username'] ?? null),
                'anonymous' => ($data['anonymous'] ?? false) === true,
                'follower_name' => $this->text($data['follower_name'] ?? null),
                'follower_username' => $this->text($data['follower_username'] ?? null),
            ],
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
