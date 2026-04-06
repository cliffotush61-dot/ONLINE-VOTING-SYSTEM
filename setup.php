<?php
// Check existing databases and create if needed
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

$result = mysqli_query($conn, "SHOW DATABASES LIKE 'online_voting_system'");
$dbExists = mysqli_num_rows($result) > 0;

if (!$dbExists) {
    if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS online_voting_system")) {
        die('Error creating database: ' . mysqli_error($conn));
    }
    echo "Database 'online_voting_system' created<br>";
}

if (!mysqli_select_db($conn, 'online_voting_system')) {
    die('Error selecting database: ' . mysqli_error($conn));
}

$schemaFile = __DIR__ . '/databases/schema.sql';
if (!file_exists($schemaFile)) {
    die('Schema file not found at: ' . $schemaFile);
}

$schema = file_get_contents($schemaFile);
$statements = array_filter(array_map('trim', explode(';', $schema)));

foreach ($statements as $stmt) {
    if (strlen($stmt) > 0 && substr($stmt, 0, 2) !== '--') {
        if (!mysqli_query($conn, $stmt)) {
            if (strpos(mysqli_error($conn), 'already exists') === false) {
                echo 'Warning: ' . mysqli_error($conn) . '<br>';
            }
        }
    }
}

echo "Database schema initialized<br>";

$result = mysqli_query($conn, 'SHOW TABLES');
$existingTables = [];
while ($row = mysqli_fetch_row($result)) {
    $existingTables[] = $row[0];
}

echo 'Tables created: ' . implode(', ', $existingTables) . '<br>';

$hashed = mysqli_real_escape_string($conn, password_hash('admin123', PASSWORD_BCRYPT));
$adminSql = "INSERT INTO admins (username, password_hash, email)
             VALUES ('admin', '$hashed', 'admin@school.edu')
             ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                email = VALUES(email)";

if (mysqli_query($conn, $adminSql)) {
    echo "Default admin user ready (username: admin, password: admin123)<br>";
} else {
    echo 'Warning: unable to normalize default admin user: ' . mysqli_error($conn) . '<br>';
}

$deptCheck = mysqli_query($conn, 'SELECT COUNT(*) as count FROM departments');
$deptRow = mysqli_fetch_assoc($deptCheck);
echo $deptRow['count'] . " departments available<br>";

echo '<hr>';
echo '<h2>System is ready!</h2>';
echo "You can now proceed to:<br>";
echo "<a href='admin_login.php'>Admin Login</a><br>";
echo "<a href='login.php'>Student Login</a><br>";
echo "<a href='index.php'>Home Page</a><br>";

mysqli_close($conn);
?>
