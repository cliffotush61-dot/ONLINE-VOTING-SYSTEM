<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Add Candidate";
include 'includes/admin_header.php';

$message = "";
$message_class = "";
$student = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reg_number = trim($_POST['reg_number'] ?? '');

    if ($action === 'search') {
        if ($reg_number === '') {
            $message = "Registration number is required.";
            $message_class = "message-error";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id, reg_number, full_name, department FROM students WHERE reg_number = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $reg_number);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $student = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if (!$student) {
                $message = "Student not found.";
                $message_class = "message-error";
            }
        }
    }

    if ($action === 'add_candidate') {
        $position = evote_position_storage_value(trim($_POST['position'] ?? ''));
        $gender = trim($_POST['gender'] ?? '');
        $manifesto = trim($_POST['manifesto'] ?? '');

        $stmt = mysqli_prepare($conn, "SELECT id, reg_number, full_name, department FROM students WHERE reg_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $reg_number);
        mysqli_stmt_execute($stmt);
        $student_result = mysqli_stmt_get_result($stmt);
        $student = $student_result ? mysqli_fetch_assoc($student_result) : null;
        mysqli_stmt_close($stmt);

        if (!$student) {
            $message = "Invalid student.";
            $message_class = "message-error";
        } elseif ($position === '' || $gender === '') {
            $message = "Position and gender are required.";
            $message_class = "message-error";
        } else {
            if (($position === "male_delegate" && $gender !== "Male") || ($position === "female_delegate" && $gender !== "Female")) {
                $message = "Candidate gender does not match the selected position.";
                $message_class = "message-error";
            } else {
                $check_stmt = mysqli_prepare($conn, "SELECT id FROM candidates WHERE reg_number = ? LIMIT 1");
                mysqli_stmt_bind_param($check_stmt, 's', $reg_number);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if ($check_result && mysqli_num_rows($check_result) > 0) {
                    $message = "This student is already a candidate.";
                    $message_class = "message-error";
                } else {
                    $photo = "";
                    if (!empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $filename = time() . "_" . preg_replace('/[^a-zA-Z0-9.]/', '_', basename($_FILES['photo']['name']));
                        $target_dir = "assets/IMAGES/";
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        $target = $target_dir . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                            $photo = $target;
                        }
                    }

                    $record_hash = evote_compute_entity_hash([
                        'name' => $student['full_name'],
                        'reg_number' => $student['reg_number'],
                        'department' => $student['department'],
                        'position' => $position,
                        'gender' => $gender,
                        'manifesto' => $manifesto,
                        'photo' => $photo
                    ]);

                    $insert_stmt = mysqli_prepare($conn, "INSERT INTO candidates (reg_number, name, department, position, gender, manifesto, photo, record_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($insert_stmt, 'ssssssss', $student['reg_number'], $student['full_name'], $student['department'], $position, $gender, $manifesto, $photo, $record_hash);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $candidate_id = mysqli_insert_id($conn);
                        logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'CANDIDATE_CREATED', "Created candidate {$student['full_name']} for {$position}.", 'candidates', $candidate_id);
                        $message = "Candidate added successfully.";
                        $message_class = "message-success";
                        $student = null;
                    } else {
                        $message = "Error: " . mysqli_error($conn);
                        $message_class = "message-error";
                    }
                    mysqli_stmt_close($insert_stmt);
                }
                mysqli_stmt_close($check_stmt);
            }
        }
    }
}
?>
<div class="page-header">
    <h1 class="page-title">Add Candidate</h1>
    <p class="page-subtitle">Search for a registered student and promote them to candidate status</p>
</div>

<div class="content-card">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="max-width: 600px; margin-bottom: 30px;">
        <input type="hidden" name="action" value="search">
        <div class="form-group">
            <label class="form-label" for="reg_number">Registration Number</label>
            <div style="display: flex; gap: 12px;">
                <input type="text" id="reg_number" name="reg_number" class="form-input" placeholder="Enter registration number" value="<?php echo htmlspecialchars($_POST['reg_number'] ?? ''); ?>" required style="flex: 1;">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </div>
    </form>

    <?php if ($student): ?>
        <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #667eea;">
            <p style="margin-bottom: 10px;"><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
            <p style="margin-bottom: 10px;"><strong>Registration Number:</strong> <?php echo htmlspecialchars($student['reg_number']); ?></p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
            <input type="hidden" name="action" value="add_candidate">
            <input type="hidden" name="reg_number" value="<?php echo htmlspecialchars($student['reg_number']); ?>">

            <div class="form-group">
                <label class="form-label" for="position">Position</label>
                <select id="position" name="position" class="form-select" required>
                    <option value="">-- Select Position --</option>
            <option value="male_delegate">Male Delegate</option>
            <option value="female_delegate">Female Delegate</option>
            <option value="departmental_delegate">Departmental Delegate</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-select" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="manifesto">Manifesto</label>
                <textarea id="manifesto" name="manifesto" class="form-textarea" placeholder="Enter candidate manifesto (optional)"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">Photo</label>
                <input type="file" id="photo" name="photo" class="form-input" accept="image/*">
                <small style="color: #6b7280; margin-top: 8px; display: block;">Recommended: JPG or PNG, max 5MB</small>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">Add Candidate</button>
                <a href="manage_candidate.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; exit; ?>
$page_title = "Add Candidate";
include 'includes/admin_header.php';


if (isset($_POST['search'])) {
    $reg = trim($_POST['reg_number']);
    $result = mysqli_query($conn, "SELECT * FROM students WHERE reg_number='$reg'");

    if (mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
    } else {
        $message = "Student not found.";
        $message_class = "message-error";
    }
}

if (isset($_POST['add_candidate'])) {

    $reg = trim($_POST['reg_number']);
    $position = $_POST['position'];
    $gender = $_POST['gender'];
    $manifesto = $_POST['manifesto'];

    $student_query = mysqli_query($conn, "SELECT * FROM students WHERE reg_number='$reg'");
    $student = mysqli_fetch_assoc($student_query);

    if (!$student) {
        $message = "Invalid student.";
        $message_class = "message-error";
    } else {

        $name = $student['full_name'];
        $department = $student['department'];

        $check = mysqli_query($conn, "SELECT * FROM candidates WHERE reg_number='$reg'");

        if (mysqli_num_rows($check) > 0) {
            $message = "This student is already a candidate.";
            $message_class = "message-error";
        } else {

            if ($position == "Male Delegate" && $gender != "Male") {
                $message = "A Male Delegate candidate must be Male.";
                $message_class = "message-error";
            } elseif ($position == "Female Delegate" && $gender != "Female") {
                $message = "A Female Delegate candidate must be Female.";
                $message_class = "message-error";
            } else {

                $photo = "";

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                    $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES["photo"]["name"]);
                    $target = "assets/images/" . $filename;

                    if (!is_dir("assets/images")) {
                        mkdir("assets/images", 0777, true);
                    }

                    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target)) {
                        $photo = $target;
                    }
                }

                $sql = "INSERT INTO candidates (reg_number, name, department, position, gender, photo, manifesto)
                        VALUES ('$reg', '$name', '$department', '$position', '$gender', '$photo', '$manifesto')";

                if (mysqli_query($conn, $sql)) {
                    $message = "Candidate added successfully.";
                    $message_class = "message-success";
                    $student = null;
                } else {
                    $message = "Error: " . mysqli_error($conn);
                    $message_class = "message-error";
                }
            }
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Add Candidate</h1>
    <p class="page-subtitle">Search for a registered student and promote them to candidate status</p>
</div>

<div class="content-card">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo (strpos($message, 'Error') === false && strpos($message, 'already') === false && strpos($message, 'not found') === false && strpos($message, 'must be') === false) ? 'message-success' : 'message-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="max-width: 600px; margin-bottom: 30px;">
        <div class="form-group">
            <label class="form-label" for="reg_number">Registration Number</label>
            <div style="display: flex; gap: 12px;">
                <input type="text" id="reg_number" name="reg_number" class="form-input" placeholder="Enter registration number" required style="flex: 1;">
                <button type="submit" name="search" class="btn btn-primary">🔍 Search</button>
            </div>
        </div>
    </form>

    <?php if ($student): ?>
        <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #667eea;">
            <p style="margin-bottom: 10px;"><strong>👤 Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
            <p style="margin-bottom: 10px;"><strong>🆔 Registration Number:</strong> <?php echo htmlspecialchars($student['reg_number']); ?></p>
            <p><strong>🏢 Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
            <input type="hidden" name="reg_number" value="<?php echo htmlspecialchars($student['reg_number']); ?>">

            <div class="form-group">
                <label class="form-label" for="position">Position</label>
                <select id="position" name="position" class="form-select" required>
                    <option value="">-- Select Position --</option>
                    <option value="Male Delegate">👨 Male Delegate</option>
                    <option value="Female Delegate">👩 Female Delegate</option>
                    <option value="Departmental Delegate">👥 Departmental Delegate</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-select" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male">👨 Male</option>
                    <option value="Female">👩 Female</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="manifesto">Manifesto</label>
                <textarea id="manifesto" name="manifesto" class="form-textarea" placeholder="Enter candidate manifesto (optional)"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">Photo</label>
                <input type="file" id="photo" name="photo" class="form-input" accept="image/*">
                <small style="color: #6b7280; margin-top: 8px; display: block;">Recommended: JPG or PNG, max 5MB</small>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" name="add_candidate" class="btn btn-primary">➕ Add Candidate</button>
                <a href="manage_candidate.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; ?>
