<?php
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
        'host' => getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : 'localhost',
        'port' => (int) (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : 3306),
        'user' => getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : 'root',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'database' => getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : 'ONLINE_VOTING_SYSTEM',
    ];
}

$db = evote_db_config();
$host = $db['host'];
$user = $db['user'];
$password = $db['password'];
$database = $db['database'];
$port = $db['port'];

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

require_once __DIR__ . '/includes/system_helpers.php';

evote_ensure_schema($conn);
?>
