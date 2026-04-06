<?php
if (!defined('EVOTE_APP_CONFIG_LOADED')) {
    define('EVOTE_APP_CONFIG_LOADED', true);

    $appTimezone = getenv('APP_TIMEZONE');
    date_default_timezone_set($appTimezone !== false && $appTimezone !== '' ? $appTimezone : 'Africa/Nairobi');

    if (!defined('EVOTE_HASH_SECRET')) {
        $envSecret = getenv('EVOTE_HASH_SECRET');
        define('EVOTE_HASH_SECRET', $envSecret !== false && $envSecret !== '' ? $envSecret : 'CHANGE-ME-IN-PRODUCTION-2026');
    }

    if (!function_exists('evote_boot_session')) {
        function evote_boot_session(): void {
            if (session_status() !== PHP_SESSION_NONE) {
                return;
            }

            $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                session_set_cookie_params(0, '/; samesite=Lax', '', $isSecure, true);
            }

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');

            session_start();
        }
    }
}
