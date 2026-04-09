<?php
require_once __DIR__ . '/config.php';

try {
    $conn = evote_db_connect();
    require_once __DIR__ . '/includes/system_helpers.php';
    evote_ensure_schema($conn);
} catch (Throwable $e) {
    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    http_response_code(503);

    if (PHP_SAPI !== 'cli') {
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Database Unavailable</title>';
        echo '<style>';
        echo 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f7fb;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}';
        echo '.card{max-width:720px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 18px 40px rgba(15,23,42,.08);padding:32px;}';
        echo 'h1{font-size:28px;margin-bottom:12px;color:#0f172a;}';
        echo 'p{line-height:1.6;color:#475569;margin-bottom:12px;}';
        echo '.hint{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-top:18px;font-size:14px;color:#334155;}';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="card">';
        echo '<h1>Database unavailable</h1>';
        echo '<p>The app could not connect to the local MariaDB server, so database-backed pages cannot load right now.</p>';
        echo '<p><strong>Details:</strong> ' . $message . '</p>';
        echo '<div class="hint">The homepage can still open, but login, registration, voting, and results require a working database account.</div>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }

    exit;
}
?>
