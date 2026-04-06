<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
require_once __DIR__ . '/db.php';

if (evote_normalize_auth_session($conn) !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Edit Student";
$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = "";
$message_class = "";
$student = null;

if ($student_id <= 0) {
    $message = "Invalid student selected.";
    $message_class = "message-error";
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }

    if (!$student) {
        $message = "Student not found.";
        $message_class = "message-error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $full_name = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = trim($_POST['password'] ?? '');

    if ($full_name === '' || $department === '') {
        $message = "Full name and department are required.";
        $message_class = "message-error";
    } else {
        $password_value = (string) ($student['password'] ?? '');
        if ($new_password !== '') {
            $password_value = password_hash($new_password, PASSWORD_DEFAULT);
        }
        $password_column = evote_student_password_column($conn);

        $record_hash = evote_compute_entity_hash([
            'reg_number' => $student['reg_number'] ?? '',
            'full_name' => $full_name,
            'password' => $password_value,
            'department' => $department,
            'email' => $student['email'] ?? '',
            'has_voted' => (int) ($student['has_voted'] ?? 0),
            'is_active' => $is_active
        ]);

        $stmt = mysqli_prepare($conn, "UPDATE students SET full_name = ?, department = ?, {$password_column} = ?, is_active = ?, record_hash = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssisi', $full_name, $department, $password_value, $is_active, $record_hash, $student_id);

            if (mysqli_stmt_execute($stmt)) {
                logAuditAction($conn, 'admin', (int) ($_SESSION['admin_id'] ?? 0), (string) ($_SESSION['admin_username'] ?? 'admin'), 'STUDENT_UPDATED', "Updated student {$student['reg_number']}.", 'students', $student_id);
                mysqli_stmt_close($stmt);
                header("Location: manage_students.php");
                exit();
            }

            $message = "Error: " . mysqli_error($conn);
            $message_class = "message-error";
            mysqli_stmt_close($stmt);
        } else {
            $message = "Unable to update student at this time.";
            $message_class = "message-error";
        }
    }
}

include 'includes/admin_header.php';
?>
<div class="page-header">
    <h1 class="page-title">Edit Student</h1>
    <p class="page-subtitle">Update student details and security status</p>
</div>

<div class="content-card" style="max-width: 700px;">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($student): ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Registration Number</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($student['reg_number']); ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="department">Department</label>
                <select id="department" name="department" class="form-select" required>
                    <option value="">-- Select Department --</option>
                    <?php
                    $dept_query = mysqli_query($conn, "SELECT department_name FROM departments ORDER BY department_name ASC");
                    while ($dept = mysqli_fetch_assoc($dept_query)):
                        $selected = ($student['department'] ?? '') === $dept['department_name'] ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($dept['department_name']); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Leave blank to keep current password">
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1" <?php echo !empty($student['is_active']) ? 'checked' : ''; ?>>
                    Active account
                </label>
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; ?>
