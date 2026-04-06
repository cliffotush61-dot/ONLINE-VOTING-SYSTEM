<?php
require_once __DIR__ . '/includes/app_config.php';

$host = "localhost";
$user = "root";
$password = "";
$database = "ONLINE_VOTING_SYSTEM";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

require_once __DIR__ . '/includes/system_helpers.php';

evote_ensure_schema($conn);
?>
