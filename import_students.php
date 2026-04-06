<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Import Students";
include 'includes/admin_header.php';

$success = "";
$error = "";

if (isset($_POST["import"])) {

    $file = $_FILES['file']['tmp_name'] ?? '';
    if ($file === '' || !is_uploaded_file($file)) {
        $error = "Please choose a valid CSV file.";
    } elseif (($handle = fopen($file, "r")) !== FALSE) {

        $row = 0;
        $imported = 0;
        $duplicate_count = 0;
        $rejected_count = 0;
        $rejected_rows = [];
        $password_column = evote_student_password_column($conn);

        $check_stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE reg_number = ? LIMIT 1");
        $insert_stmt = mysqli_prepare($conn, "INSERT INTO students (reg_number, full_name, {$password_column}, department, has_voted, is_active, record_hash) VALUES (?, ?, ?, ?, 0, 1, ?)");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

            if ($row == 0) { // skip header
                $row++;
                continue;
            }

            $reg = trim($data[0] ?? '');
            $name = trim($data[1] ?? '');
            $password_raw = trim($data[2] ?? '');
            $dept = trim($data[3] ?? '');

            if ($reg === '' || $name === '' || $password_raw === '' || $dept === '') {
                $rejected_count++;
                $rejected_rows[] = "Row {$row}: missing one or more required fields.";
                $row++;
                continue;
            }

            $password = password_hash($password_raw, PASSWORD_DEFAULT);

            mysqli_stmt_bind_param($check_stmt, 's', $reg);
            mysqli_stmt_execute($check_stmt);
            $check = mysqli_stmt_get_result($check_stmt);

            if ($check && mysqli_num_rows($check) == 0) {
                $record_hash = evote_compute_entity_hash([
                    'reg_number' => $reg,
                    'full_name' => $name,
                    'password' => $password,
                    'department' => $dept,
                    'email' => '',
                    'has_voted' => 0,
                    'is_active' => 1
                ]);
                mysqli_stmt_bind_param($insert_stmt, 'sssss', $reg, $name, $password, $dept, $record_hash);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $imported++;
                } elseif (mysqli_errno($conn) === 1062) {
                    $duplicate_count++;
                    $rejected_rows[] = "Row {$row}: duplicate registration number ({$reg}).";
                } else {
                    $rejected_count++;
                    $rejected_rows[] = "Row {$row}: " . mysqli_error($conn);
                }
            } else {
                $duplicate_count++;
                $rejected_rows[] = "Row {$row}: duplicate registration number ({$reg}).";
            }
            $row++;
        }

        fclose($handle);
        mysqli_stmt_close($check_stmt);
        mysqli_stmt_close($insert_stmt);
        $success = "Import completed! {$imported} inserted, {$duplicate_count} duplicates skipped, {$rejected_count} rejected.";
        if (!empty($rejected_rows)) {
            $success .= " Rejected rows: " . implode(' ', array_slice($rejected_rows, 0, 5));
        }
        logAuditAction(
            $conn,
            'admin',
            (int) $_SESSION['admin_id'],
            (string) ($_SESSION['admin_username'] ?? 'admin'),
            'CSV_IMPORT',
            "Imported {$imported} student records from CSV. Duplicate rows skipped: {$duplicate_count}. Rejected rows: {$rejected_count}.",
            'students',
            null
        );
    } else {
        $error = "Unable to read file.";
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Import Students</h1>
    <p class="page-subtitle">Bulk import student records from a CSV file</p>
</div>

<div class="content-card" style="max-width: 600px;">
    <?php if (!empty($success)): ?>
        <div class="message-box message-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="message-box message-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label" for="file">CSV File</label>
            <input type="file" id="file" name="file" class="form-input" accept=".csv" required>
            <small style="color: #6b7280; margin-top: 8px; display: block;">Accepted format: CSV (Comma-Separated Values)</small>
        </div>

        <button type="submit" name="import" class="btn btn-primary">📥 Upload CSV</button>
    </form>

    <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-top: 30px; border-left: 4px solid #667eea;">
        <p style="font-weight: 600; margin-bottom: 10px;">📋 Required CSV Format:</p>
        <p style="font-family: monospace; background: white; padding: 12px; border-radius: 5px; margin-bottom: 10px; overflow-x: auto;">
            reg_number, full_name, password, department
        </p>
        <p style="font-size: 14px; color: #4b5563;">
            <strong>Example:</strong><br>
            STU001, John Doe, temppass123, Computer Science<br>
            STU002, Jane Smith, temppass456, Engineering
        </p>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
