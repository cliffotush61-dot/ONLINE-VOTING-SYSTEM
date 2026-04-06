<?php
include 'db.php';

echo "<style>
  body { font-family: sans-serif; padding: 40px; background: #f5f5f5; }
  .status { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
  .ok { color: #10b981; }
  .error { color: #ef4444; }
  pre { background: #f9fafb; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

echo "<div class='status'>";
echo "<h1>System Status Check</h1>";

// Check database connection
try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM students");
    echo "<p class='ok'>✓ Database connection successful</p>";
    
    // Count records in each table
    $tables = ['students', 'admins', 'candidates', 'departments', 'votes', 'audit_log'];
    echo "<h2>Database Tables:</h2>";
    echo "<pre>";
    foreach ($tables as $table) {
        $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM $table"))['count'];
        echo "• $table: $count records\n";
    }
    echo "</pre>";
    
    echo "<h2>Next Steps:</h2>";
    echo "<p><a href='admin_login.php' style='color: #667eea; font-weight: bold;'>→ Go to Admin Login</a></p>";
    echo "<p><a href='login.php' style='color: #667eea; font-weight: bold;'>→ Go to Student Login</a></p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
}

mysqli_close($conn);
echo "</div>";
?>
