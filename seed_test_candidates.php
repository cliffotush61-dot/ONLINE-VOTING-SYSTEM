<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Seed Test Candidates";
include 'includes/admin_header.php';

$message = "";
$message_class = "";

function evote_seed_candidate_from_student(mysqli $conn, array $student, string $position, string $gender, string $manifesto): array {
    $result = [
        'inserted' => false,
        'skipped' => false,
        'reason' => '',
        'candidate_id' => null,
    ];

    $reg_number = (string) ($student['reg_number'] ?? '');
    if ($reg_number === '') {
        $result['skipped'] = true;
        $result['reason'] = 'Missing registration number.';
        return $result;
    }

    $check_stmt = mysqli_prepare($conn, "SELECT id FROM candidates WHERE reg_number = ? LIMIT 1");
    if (!$check_stmt) {
        $result['reason'] = 'Unable to check for existing candidate.';
        return $result;
    }

    mysqli_stmt_bind_param($check_stmt, 's', $reg_number);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $already_exists = $check_result && mysqli_num_rows($check_result) > 0;
    mysqli_stmt_close($check_stmt);

    if ($already_exists) {
        $result['skipped'] = true;
        $result['reason'] = "Student {$reg_number} is already a candidate.";
        return $result;
    }

    $record_hash = evote_compute_entity_hash([
        'name' => $student['full_name'] ?? '',
        'reg_number' => $reg_number,
        'department' => $student['department'] ?? '',
        'position' => $position,
        'gender' => $gender,
        'manifesto' => $manifesto,
        'photo' => ''
    ]);

    $insert_stmt = mysqli_prepare($conn, "INSERT INTO candidates (reg_number, name, department, position, gender, manifesto, photo, record_hash) VALUES (?, ?, ?, ?, ?, ?, '', ?)");
    if (!$insert_stmt) {
        $result['reason'] = 'Unable to prepare candidate insert.';
        return $result;
    }

    $name = (string) ($student['full_name'] ?? '');
    $department = (string) ($student['department'] ?? '');
    mysqli_stmt_bind_param($insert_stmt, 'sssssss', $reg_number, $name, $department, $position, $gender, $manifesto, $record_hash);
    $ok = mysqli_stmt_execute($insert_stmt);
    if ($ok) {
        $result['inserted'] = true;
        $result['candidate_id'] = mysqli_insert_id($conn);
    } else {
        $result['reason'] = mysqli_errno($conn) === 1062 ? "Duplicate candidate for {$reg_number}." : mysqli_error($conn);
    }
    mysqli_stmt_close($insert_stmt);

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_candidates'])) {
    mysqli_begin_transaction($conn);

    $dept_stmt = mysqli_prepare($conn, "SELECT department, COUNT(*) AS total_students FROM students WHERE is_active = 1 GROUP BY department ORDER BY total_students DESC, department ASC LIMIT 1");
    $dept_name = '';
    if ($dept_stmt) {
        mysqli_stmt_execute($dept_stmt);
        $dept_result = mysqli_stmt_get_result($dept_stmt);
        $dept_row = $dept_result ? mysqli_fetch_assoc($dept_result) : null;
        mysqli_stmt_close($dept_stmt);
        $dept_name = (string) ($dept_row['department'] ?? '');
    }

    if ($dept_name === '') {
        mysqli_rollback($conn);
        $message = "No active student department was found to seed candidates from.";
        $message_class = "message-error";
    } else {
        $student_stmt = mysqli_prepare($conn, "SELECT id, reg_number, full_name, department FROM students WHERE department = ? AND is_active = 1 ORDER BY id ASC LIMIT 3");
        $students = [];
        if ($student_stmt) {
            mysqli_stmt_bind_param($student_stmt, 's', $dept_name);
            mysqli_stmt_execute($student_stmt);
            $student_result = mysqli_stmt_get_result($student_stmt);
            while ($student_result && ($row = mysqli_fetch_assoc($student_result))) {
                $students[] = $row;
            }
            mysqli_stmt_close($student_stmt);
        }

        if (count($students) < 3) {
            mysqli_rollback($conn);
            $message = "Need at least three active students in {$dept_name} to seed test candidates.";
            $message_class = "message-error";
        } else {
            $blueprint = [
                ['male_delegate', 'Male', 'Seeded test candidate for the male delegate position.'],
                ['female_delegate', 'Female', 'Seeded test candidate for the female delegate position.'],
                ['departmental_delegate', 'Male', 'Seeded test candidate for the departmental delegate position.'],
            ];

            $inserted = 0;
            $skipped = 0;
            $details = [];

            foreach ($blueprint as $index => $spec) {
                $student = $students[$index];
                $seed = evote_seed_candidate_from_student($conn, $student, $spec[0], $spec[1], $spec[2]);
                if ($seed['inserted']) {
                    $inserted++;
                    $details[] = "Inserted {$student['reg_number']} as " . evote_position_display_label($spec[0]) . ".";
                } elseif ($seed['skipped']) {
                    $skipped++;
                    $details[] = $seed['reason'];
                } else {
                    $details[] = $seed['reason'] ?: "Failed to seed {$student['reg_number']}.";
                }
            }

            mysqli_commit($conn);
            logAuditAction(
                $conn,
                'admin',
                (int) ($_SESSION['admin_id'] ?? 0),
                (string) ($_SESSION['admin_username'] ?? 'admin'),
                'TEST_CANDIDATES_SEEDED',
                "Seeded {$inserted} test candidates for {$dept_name}. Skipped {$skipped}.",
                'candidates',
                null
            );

            $message = "Seed complete for {$dept_name}: {$inserted} inserted, {$skipped} skipped.";
            if (!empty($details)) {
                $message .= ' ' . implode(' ', array_slice($details, 0, 3));
            }
            $message_class = "message-success";
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Seed Test Candidates</h1>
    <p class="page-subtitle">Create three ballot candidates from the active student roster for quick testing</p>
</div>

<div class="content-card" style="max-width: 820px;">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo htmlspecialchars($message_class); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;margin-bottom:22px;">
        <h3 style="margin-bottom:10px;">What this does</h3>
        <p style="color:#475569;line-height:1.7;">
            This tool selects the active department with the most students, then uses the first three students in that department to create test candidates for the Male Delegate, Female Delegate, and Departmental Delegate positions.
        </p>
    </div>

    <form method="POST" onsubmit="return confirm('Seed three test candidates from the current student roster?');">
        <button type="submit" name="seed_candidates" class="btn btn-primary">Seed Test Candidates</button>
        <a href="admin_dashboard.php" class="btn btn-secondary" style="margin-left:10px;">Back to Dashboard</a>
    </form>
</div>

<?php include 'includes/admin_footer.php'; ?>
