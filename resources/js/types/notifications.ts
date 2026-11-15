/**
 * Notificacoes da conta (F7, ADR-142).
 *
 * O payload nao carrega o corpo do comentario, e-mail ou IP: so o suficiente
 * para descrever o fato e levar ao recurso certo.
 */

export type AccountNotificationKind =
    | 'palette_liked'
    | 'user_followed'
    | 'palette_comment_approved'
    | 'comment_reply_approved'
    | 'unknown';

export type AccountNotificationData = {
    palette_id: string | null;
    palette_name: string | null;
    palette_slug: string | null;
    comment_id: string | null;
    actor_name: string | null;
    actor_username: string | null;
    anonymous: boolean;
    follower_name: string | null;
    follower_username: string | null;
};

export type AccountNotification = {
    id: string;
    kind: AccountNotificationKind;
    read: boolean;
    created_at: string;
    data: AccountNotificationData;
};

export type AccountNotificationPaginator = {
    data: AccountNotification[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type NotificationPreferences = {
    email_on_like: boolean;
    email_on_follow: boolean;
    email_on_comment: boolean;
    email_on_reply: boolean;
};

export type NotificationsPageProps = {
    notifications: AccountNotificationPaginator;
    preferences: NotificationPreferences;
};

/** Resposta de `POST/DELETE /api/tools/colors/palettes/{id}/mute`. */
export type PaletteMuteState = {
    muted: boolean;
};
