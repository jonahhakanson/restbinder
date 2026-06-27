<?php
namespace RestBinder\Demo\Grid;

final class GridSession
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['rb_demo_grid_session_key'])) {
            $_SESSION['rb_demo_grid_session_key'] = bin2hex(random_bytes(16));
        }
    }

    public static function sessionKey(): string
    {
        self::start();

        return (string) $_SESSION['rb_demo_grid_session_key'];
    }

    public static function voterKey(): string
    {
        $userId = self::currentUserId();
        if ($userId !== null) {
            return sprintf('user:%d', $userId);
        }

        return sprintf('session:%s', self::sessionKey());
    }

    public static function currentUserId(): ?int
    {
        self::start();

        foreach (['user_id', 'auth_user_id', 'current_user_id'] as $key) {
            if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
                return (int) $_SESSION[$key];
            }
        }

        return null;
    }
}
