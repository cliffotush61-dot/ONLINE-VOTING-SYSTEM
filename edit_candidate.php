<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Edit Candidate";
include 'includes/admin_header.php';

$candidate_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = "";
$message_class = "";

if ($candidate_id <= 0) {
    $message = "Invalid candidate selected.";
    $message_class = "message-error";
    $candidate = null;
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM candidates WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $candidate_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$candidate) {
        $message = "Candidate not found.";
        $message_class = "message-error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $candidate) {
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = evote_position_storage_value(trim($_POST['position'] ?? ''));
    $gender = trim($_POST['gender'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');
    $photo = $candidate['photo'] ?? '';

    if ($name === '' || $department === '' || $position === '' || $gender === '') {
        $message = "All fields except manifesto and photo are required.";
        $message_class = "message-error";
    } else {
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
            'name' => $name,
            'reg_number' => $candidate['reg_number'],
            'department' => $department,
            'position' => $position,
            'gender' => $gender,
            'manifesto' => $manifesto,
            'photo' => $photo
        ]);

        $stmt = mysqli_prepare($conn, "UPDATE candidates SET name = ?, department = ?, position = ?, gender = ?, manifesto = ?, photo = ?, record_hash = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssssssi', $name, $department, $position, $gender, $manifesto, $photo, $record_hash, $candidate_id);

        if (mysqli_stmt_execute($stmt)) {
            logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'CANDIDATE_UPDATED', "Updated candidate {$candidate['reg_number']}.", 'candidates', $candidate_id);
            header("Location: manage_candidate.php");
            exit();
        }

        $message = "Error: " . mysqli_error($conn);
        $message_class = "message-error";
        mysqli_stmt_close($stmt);
    }
}
?>
<div class="page-header">
    <h1 class="page-title">Edit Candidate</h1>
    <p class="page-subtitle">Update candidate information and competition details</p>
</div>

<div class="content-card" style="max-width: 760px;">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($candidate): ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Registration Number</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($candidate['reg_number']); ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($candidate['name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="department">Department</label>
                <input type="text" id="department" name="department" class="form-input" value="<?php echo htmlspecialchars($candidate['department']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="position">Position</label>
                <select id="position" name="position" class="form-select" required>
                    <option value="male_delegate" <?php echo evote_position_storage_value($candidate['position']) === 'male_delegate' ? 'selected' : ''; ?>>Male Delegate</option>
                    <option value="female_delegate" <?php echo evote_position_storage_value($candidate['position']) === 'female_delegate' ? 'selected' : ''; ?>>Female Delegate</option>
                    <option value="departmental_delegate" <?php echo evote_position_storage_value($candidate['position']) === 'departmental_delegate' ? 'selected' : ''; ?>>Departmental Delegate</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-select" required>
                    <option value="Male" <?php echo $candidate['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $candidate['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="manifesto">Manifesto</label>
                <textarea id="manifesto" name="manifesto" class="form-textarea" rows="6"><?php echo htmlspecialchars($candidate['manifesto'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">Photo</label>
                <input type="file" id="photo" name="photo" class="form-input" accept="image/*">
                <?php if (!empty($candidate['photo'])): ?>
                    <small style="color: #6b7280; display:block; margin-top:8px;">Current photo: <?php echo htmlspecialchars($candidate['photo']); ?></small>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage_candidate.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; ?>
