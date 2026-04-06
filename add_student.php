<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Add Student";
include 'includes/admin_header.php';

$message = "";
$message_class = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_number = trim($_POST['reg_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password_raw = $_POST['password'] ?? '';
    $department = trim($_POST['department'] ?? '');

    if ($reg_number === '' || $full_name === '' || $password_raw === '' || $department === '') {
        $message = "All fields are required.";
        $message_class = "message-error";
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE reg_number = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 's', $reg_number);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $message = "A student with that registration number already exists.";
            $message_class = "message-error";
        } else {
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $password_column = evote_student_password_column($conn);
            $record_hash = evote_compute_entity_hash([
                'reg_number' => $reg_number,
                'full_name' => $full_name,
                'password' => $password_hash,
                'department' => $department,
                'email' => '',
                'has_voted' => 0,
                'is_active' => 1
            ]);

            $insert_stmt = mysqli_prepare($conn, "INSERT INTO students (reg_number, full_name, {$password_column}, department, has_voted, is_active, record_hash) VALUES (?, ?, ?, ?, 0, 1, ?)");
            mysqli_stmt_bind_param($insert_stmt, 'sssss', $reg_number, $full_name, $password_hash, $department, $record_hash);

            if (mysqli_stmt_execute($insert_stmt)) {
                $student_id = mysqli_insert_id($conn);
                logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'STUDENT_CREATED', "Created student {$reg_number}.", 'students', $student_id);
                $message = "Student added successfully.";
                $message_class = "message-success";
            } else {
                $message = mysqli_errno($conn) === 1062 ? "A student with that registration number already exists." : "Error: " . mysqli_error($conn);
                $message_class = "message-error";
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
}
?>
<div class="page-header">
    <h1 class="page-title">Add Student</h1>
    <p class="page-subtitle">Register a new student voter for the election</p>
</div>

<div class="content-card">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="max-width: 600px;">
        <div class="form-group">
            <label class="form-label" for="reg_number">Registration Number</label>
            <input type="text" id="reg_number" name="reg_number" class="form-input" placeholder="e.g., STU001" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Enter full name" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Temporary Password</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="Enter temporary password" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="department">Department</label>
            <select id="department" name="department" class="form-select" required>
                <option value="">-- Select Department --</option>
                <?php
                $dept_query = mysqli_query($conn, "SELECT department_name FROM departments ORDER BY department_name ASC");
                while ($dept = mysqli_fetch_assoc($dept_query)):
                ?>
                    <option value="<?php echo htmlspecialchars($dept['department_name']); ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary">➕ Add Student</button>
            <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/admin_footer.php'; exit; ?>
$page_title = "Add Student";
include 'includes/admin_header.php';
?>

<div class="page-header">
    <h1 class="page-title">Add Student</h1>
    <p class="page-subtitle">Register a new student voter for the election</p>
</div>

<div class="content-card">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo strpos($message, 'Error') === false && strpos($message, 'already') === false ? 'message-success' : 'message-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="max-width: 600px;">
        <div class="form-group">
            <label class="form-label" for="reg_number">Registration Number</label>
            <input type="text" id="reg_number" name="reg_number" class="form-input" placeholder="e.g., STU001" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Enter full name" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Temporary Password</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="Enter temporary password" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="department">Department</label>
            <select id="department" name="department" class="form-select" required>
                <option value="">-- Select Department --</option>
                <?php
                $dept_query = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name ASC");
                while ($dept = mysqli_fetch_assoc($dept_query)):
                ?>
                    <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary">➕ Add Student</button>
            <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/admin_footer.php'; ?>

                <label class="form-label" for="department">Department</label>
                <select id="department" name="department" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="primary-btn">Add Student</button>
            </form>
        </div>

        <div class="highlight-card scroll-reveal">
            <h3>Student registration benefits</h3>
            <ul>
                <li>Ensure all eligible students are ready to vote.</li>
                <li>Create and manage the official voter registry.</li>
                <li>Support departments with accurate election counts.</li>
            </ul>
        </div>
    </div>
</main>

<footer>
    <div class="container">
        <p>© 2026 University Election Portal · Add students with a secure voter registration flow.</p>
    </div>
</footer>
</body>
</html>
