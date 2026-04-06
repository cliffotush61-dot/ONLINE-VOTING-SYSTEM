<?php
declare(strict_types=1);

if (!defined('EVOTE_CONFIG_LOADED')) {
    define('EVOTE_CONFIG_LOADED', true);

    require_once __DIR__ . '/includes/app_config.php';

    function evote_db_config(): array
    {
        $dsn = getenv('DATABASE_URL');
        if ($dsn !== false && trim($dsn) !== '') {
            $parts = parse_url($dsn);
            if ($parts !== false) {
                return [
                    'host' => $parts['host'] ?? 'localhost',
                    'port' => isset($parts['port']) ? (int) $parts['port'] : 3306,
                    'user' => $parts['user'] ?? 'root',
                    'password' => $parts['pass'] ?? '',
                    'database' => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
                ];
            }
        }

        return [
            'host' => getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : 3306),
            'user' => getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : 'root',
            'password' => getenv('DB_PASS') !== false && getenv('DB_PASS') !== ''
                ? getenv('DB_PASS')
                : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''),
            'database' => getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : 'ONLINE_VOTING_SYSTEM',
        ];
    }

    function evote_db_connect(): mysqli
    {
        $db = evote_db_config();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = mysqli_connect(
            $db['host'],
            $db['user'],
            $db['password'],
            $db['database'],
            $db['port']
        );

        if (!$conn) {
            throw new RuntimeException('Database connection failed: ' . mysqli_connect_error());
        }

        mysqli_set_charset($conn, 'utf8mb4');
        return $conn;
    }
}
