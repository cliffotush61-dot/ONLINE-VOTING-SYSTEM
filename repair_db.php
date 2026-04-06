<?php
// Database repair script - normalizes the admins table and default admin account
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

if (!mysqli_select_db($conn, 'online_voting_system')) {
    die('Error selecting database: ' . mysqli_error($conn));
}

$tables_result = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
if (mysqli_num_rows($tables_result) > 0) {
    $columns = mysqli_query($conn, 'SHOW COLUMNS FROM admins');
    $col_names = [];
    while ($col = mysqli_fetch_assoc($columns)) {
        $col_names[] = $col['Field'];
    }

    echo '<h1>Database Repair Report</h1>';
    echo '<p><strong>Admins table found with columns:</strong> ' . implode(', ', $col_names) . '</p>';

    if (in_array('password', $col_names) && !in_array('password_hash', $col_names)) {
        echo '<p>Found legacy password column - converting to password_hash...</p>';
        $rename = mysqli_query($conn, 'ALTER TABLE admins CHANGE COLUMN password password_hash VARCHAR(255) NOT NULL');
        if ($rename) {
            echo '<p>Successfully renamed password to password_hash</p>';
        } else {
            echo '<p>Error renaming column: ' . mysqli_error($conn) . '</p>';
        }
    } elseif (in_array('password_hash', $col_names)) {
        echo '<p>Password hash column already exists</p>';
    } else {
        echo '<p>Neither password column exists - adding password_hash...</p>';
        $add_col = mysqli_query($conn, 'ALTER TABLE admins ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT ""');
        if ($add_col) {
            echo '<p>Successfully added password_hash column</p>';
        } else {
            echo '<p>Error adding column: ' . mysqli_error($conn) . '</p>';
        }
    }

    echo '<p><strong>Checking admin user...</strong></p>';
    $admin_check = mysqli_query($conn, "SELECT * FROM admins WHERE username = 'admin'");
    $hashed = mysqli_real_escape_string($conn, password_hash('admin123', PASSWORD_BCRYPT));

    if (mysqli_num_rows($admin_check) > 0) {
        echo '<p>Admin user exists</p>';
        $update = mysqli_query($conn, "UPDATE admins SET password_hash = '$hashed' WHERE username = 'admin'");
        if ($update) {
            echo '<p>Default admin password updated to admin123</p>';
        } else {
            echo '<p>Error updating admin password: ' . mysqli_error($conn) . '</p>';
        }
    } else {
        echo '<p>Admin user not found - creating...</p>';
        $insert = mysqli_query($conn, "INSERT INTO admins (username, password_hash) VALUES ('admin', '$hashed')");
        if ($insert) {
            echo '<p>Admin user created successfully</p>';
        } else {
            echo '<p>Error creating admin: ' . mysqli_error($conn) . '</p>';
        }
    }
} else {
    echo '<p>Admins table not found!</p>';
}

echo '<hr>';
echo '<p><strong>All tables:</strong></p>';
$all_tables = mysqli_query($conn, 'SHOW TABLES');
while ($table = mysqli_fetch_row($all_tables)) {
    echo '<p>- ' . $table[0] . '</p>';
}

echo '<hr>';
echo '<p>Repair script complete. You can now delete this file (repair_db.php).</p>';
?>
