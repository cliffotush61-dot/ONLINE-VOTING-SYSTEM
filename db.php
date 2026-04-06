<?php
require_once __DIR__ . '/config.php';

$conn = evote_db_connect();

require_once __DIR__ . '/includes/system_helpers.php';

evote_ensure_schema($conn);
?>
