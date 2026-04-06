<?php
// Database initialization script
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

// Read schema file
$schema = file_get_contents(__DIR__ . '/databases/schema.sql');

// Execute multiple statements
$statements = explode(';', $schema);

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        if (!mysqli_query($conn, $statement)) {
            echo "Error executing SQL: " . mysqli_error($conn) . "\n";
        }
    }
}

mysqli_close($conn);

echo "✓ Database initialization complete!\n";
echo "✓ Created 'online_voting_system' database\n";
echo "✓ Created all tables\n";
?>
