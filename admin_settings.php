<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Election Settings";
include 'includes/admin_header.php';

$state = evote_election_state($conn);
$message = "";
$message_class = "";

function evote_upsert_election(mysqli $conn, array $data, ?int $election_id = null): bool {
    $title = $data['title'];
    $start_datetime = $data['start_datetime'];
    $end_datetime = $data['end_datetime'];
    $status = $data['status'];
    $manual_override = $data['manual_override'];
    $live_results_enabled = (int) $data['live_results_enabled'];
    $record_hash = evote_compute_entity_hash([
        'title' => $title,
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime,
        'status' => $status,
        'manual_override' => $manual_override,
        'live_results_enabled' => $live_results_enabled,
        'is_active' => 1
    ]);

    if ($election_id !== null) {
        $stmt = mysqli_prepare($conn, "UPDATE elections SET title = ?, start_datetime = ?, end_datetime = ?, status = ?, manual_override = ?, live_results_enabled = ?, is_active = 1, record_hash = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssssisi', $title, $start_datetime, $end_datetime, $status, $manual_override, $live_results_enabled, $record_hash, $election_id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO elections (title, start_datetime, end_datetime, status, manual_override, live_results_enabled, is_active, record_hash) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'sssssis', $title, $start_datetime, $end_datetime, $status, $manual_override, $live_results_enabled, $record_hash);
    }

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

$current_election = null;
if ($state['exists'] && $state['id']) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM elections WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $state['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_election = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_election') {
        $title = trim($_POST['title'] ?? '');
        $start_datetime = trim($_POST['start_datetime'] ?? '');
        $end_datetime = trim($_POST['end_datetime'] ?? '');
        $live_results_enabled = isset($_POST['live_results_enabled']) ? 1 : 0;

        if ($title === '' || $start_datetime === '' || $end_datetime === '') {
            $message = "Title, start date/time, and end date/time are required.";
            $message_class = "message-error";
        } else {
            $status = 'scheduled';
            $manual_override = 'none';
            $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            $start = new DateTime($start_datetime);
            $end = new DateTime($end_datetime);
            if ($now >= $start && $now <= $end) {
                $status = 'open';
            } elseif ($now > $end) {
                $status = 'closed';
            }

            $ok = evote_upsert_election($conn, [
                'title' => $title,
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'status' => $status,
                'manual_override' => $manual_override,
                'live_results_enabled' => $live_results_enabled
            ], $current_election['id'] ?? null);

            if ($ok) {
                logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), $current_election ? 'ELECTION_UPDATED' : 'ELECTION_CREATED', "Saved election configuration for {$title}.", 'elections', $current_election['id'] ?? null);
                $message = "Election settings saved successfully.";
                $message_class = "message-success";
            } else {
                $message = "Error saving election: " . mysqli_error($conn);
                $message_class = "message-error";
            }
        }
    }

    if ($action === 'force_open' || $action === 'force_close') {
        if ($action === 'force_open' && (!$state['exists'] || !$current_election)) {
            $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            $end = (clone $now)->modify('+1 day');
            $ok = evote_upsert_election($conn, [
                'title' => 'Student Election',
                'start_datetime' => $now->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'status' => 'open',
                'manual_override' => 'force_open',
                'live_results_enabled' => 0
            ], null);

            if ($ok) {
                logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'ELECTION_CREATED_AND_OPENED', 'Administrator created a default election and opened it for testing.', 'elections', null);
                $message = "Election created and opened. It will close automatically in 24 hours.";
                $message_class = "message-success";
            } else {
                $message = "Unable to create the election.";
                $message_class = "message-error";
            }
        } elseif ($state['exists'] && $current_election) {
            if ($action === 'force_open') {
                $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
                $end = !empty($current_election['end_datetime']) ? new DateTime($current_election['end_datetime']) : null;

                if ($end && $now > $end) {
                    $message = "The election deadline has already passed. Please create or extend the election instead of forcing it open.";
                    $message_class = "message-error";
                } else {
                    $status = 'open';
                    $manual_override = 'force_open';
                    $ok = evote_upsert_election($conn, [
                        'title' => $current_election['title'],
                        'start_datetime' => $current_election['start_datetime'],
                        'end_datetime' => $current_election['end_datetime'],
                        'status' => $status,
                        'manual_override' => $manual_override,
                        'live_results_enabled' => (int) $current_election['live_results_enabled']
                    ], (int) $current_election['id']);

                    if ($ok) {
                        logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'ELECTION_FORCE_OPEN', "Administrator manually set election to {$status}.", 'elections', (int) $current_election['id']);
                        $message = "Election forced open.";
                        $message_class = "message-success";
                    } else {
                        $message = "Unable to update election status.";
                        $message_class = "message-error";
                    }
                }
            } else {
                $status = 'closed';
                $manual_override = 'force_close';
                $ok = evote_upsert_election($conn, [
                    'title' => $current_election['title'],
                    'start_datetime' => $current_election['start_datetime'],
                    'end_datetime' => $current_election['end_datetime'],
                    'status' => $status,
                    'manual_override' => $manual_override,
                    'live_results_enabled' => (int) $current_election['live_results_enabled']
                ], (int) $current_election['id']);

                if ($ok) {
                    logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'ELECTION_FORCE_CLOSE', "Administrator manually set election to {$status}.", 'elections', (int) $current_election['id']);
                    $message = "Election forced closed.";
                    $message_class = "message-success";
                } else {
                    $message = "Unable to update election status.";
                    $message_class = "message-error";
                }
            }
        } else {
            $message = "Create an election before forcing its status.";
            $message_class = "message-error";
        }
    }

    if ($action === 'reset_election') {
        if (trim($_POST['confirm_reset'] ?? '') !== 'RESET') {
            $message = "Type RESET to confirm the reset action.";
            $message_class = "message-error";
        } else {
            mysqli_begin_transaction($conn);
            $votes_ok = false;
            $students_ok = false;
            $elections_ok = true;

            $delete_votes = mysqli_prepare($conn, "DELETE FROM votes");
            if ($delete_votes) {
                $votes_ok = mysqli_stmt_execute($delete_votes);
                mysqli_stmt_close($delete_votes);
            }

            $students_ok = true;
            $student_stmt = mysqli_prepare($conn, "SELECT id, reg_number, full_name, password, department, email, has_voted, is_active FROM students ORDER BY id ASC");
            if ($student_stmt) {
                mysqli_stmt_execute($student_stmt);
                $student_result = mysqli_stmt_get_result($student_stmt);
                $update_student = mysqli_prepare($conn, "UPDATE students SET has_voted = 0, record_hash = ?, updated_at = NOW() WHERE id = ?");

                while ($student = $student_result ? mysqli_fetch_assoc($student_result) : null) {
                    $student['has_voted'] = 0;
                    $student_hash = evote_compute_entity_hash(evote_hash_fields_from_row('students', $student));
                    if ($update_student) {
                        $student_id_value = (int) $student['id'];
                        mysqli_stmt_bind_param($update_student, 'si', $student_hash, $student_id_value);
                        if (!mysqli_stmt_execute($update_student)) {
                            $students_ok = false;
                            break;
                        }
                    }
                }

                if ($update_student) {
                    mysqli_stmt_close($update_student);
                }
                mysqli_stmt_close($student_stmt);
            } else {
                $students_ok = false;
            }

            if ($current_election) {
                $reset_fields = [
                    'title' => $current_election['title'],
                    'start_datetime' => $current_election['start_datetime'],
                    'end_datetime' => $current_election['end_datetime'],
                    'status' => 'scheduled',
                    'manual_override' => 'none',
                    'live_results_enabled' => (int) $current_election['live_results_enabled'],
                    'is_active' => 1
                ];
                $reset_hash = evote_compute_entity_hash($reset_fields);
                $reset_stmt = mysqli_prepare($conn, "UPDATE elections SET status = 'scheduled', manual_override = 'none', record_hash = ?, updated_at = NOW() WHERE id = ?");
                if ($reset_stmt) {
                    mysqli_stmt_bind_param($reset_stmt, 'si', $reset_hash, $current_election['id']);
                    $elections_ok = mysqli_stmt_execute($reset_stmt);
                    mysqli_stmt_close($reset_stmt);
                } else {
                    $elections_ok = false;
                }
            }

            if ($votes_ok && $students_ok && $elections_ok) {
                mysqli_commit($conn);
                logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'ELECTION_RESET', 'Election data reset. Votes cleared and student vote flags reset.', 'elections', $current_election['id'] ?? null);
                $message = "Election data reset successfully.";
                $message_class = "message-success";
            } else {
                mysqli_rollback($conn);
                $message = "Unable to reset election data.";
                $message_class = "message-error";
            }
        }
    }
}

$state = evote_election_state($conn);
if ($state['exists'] && !$current_election) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM elections WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $state['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_election = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
}
?>

<div class="page-header">
    <h1 class="page-title">Election Settings</h1>
    <p class="page-subtitle">Create, schedule, open, close, and reset election cycles</p>
</div>

<?php if (!empty($message)): ?>
    <div class="content-card">
        <div class="message-box <?php echo htmlspecialchars($message_class); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <h3>Status</h3>
        <div class="stat-value"><?php echo htmlspecialchars($state['status']); ?></div>
    </div>
    <div class="stat-card">
        <h3>Live Results</h3>
        <div class="stat-value"><?php echo $state['live_results_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
    </div>
    <div class="stat-card">
        <h3>Opens At</h3>
        <div class="stat-value" style="font-size:18px;"><?php echo htmlspecialchars($state['opens_at'] ?? 'N/A'); ?></div>
    </div>
    <div class="stat-card">
        <h3>Closes At</h3>
        <div class="stat-value" style="font-size:18px;"><?php echo htmlspecialchars($state['closes_at'] ?? 'N/A'); ?></div>
    </div>
</div>

<div class="content-card" style="max-width:900px;">
    <form method="POST">
        <input type="hidden" name="action" value="save_election">
        <div class="form-group">
            <label class="form-label" for="title">Election Title</label>
            <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($current_election['title'] ?? 'Student Election'); ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="start_datetime">Start Date/Time</label>
            <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-input" value="<?php echo htmlspecialchars(isset($current_election['start_datetime']) ? str_replace(' ', 'T', substr($current_election['start_datetime'], 0, 16)) : ''); ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="end_datetime">End Date/Time</label>
            <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-input" value="<?php echo htmlspecialchars(isset($current_election['end_datetime']) ? str_replace(' ', 'T', substr($current_election['end_datetime'], 0, 16)) : ''); ?>" required>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
                <input type="checkbox" name="live_results_enabled" value="1" <?php echo !empty($current_election['live_results_enabled']) ? 'checked' : ''; ?>>
                Enable live public results
            </label>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Save Election</button>
        </div>
    </form>
</div>

<div class="content-card" style="max-width:900px;margin-top:20px;">
    <h3 style="margin-bottom:12px;">Manual Controls</h3>
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="action" value="force_open">
        <button type="submit" class="btn btn-primary">Force Open</button>
    </form>
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:12px;">
        <input type="hidden" name="action" value="force_close">
        <button type="submit" class="btn btn-danger">Force Close</button>
    </form>
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:20px;">
        <input type="hidden" name="action" value="reset_election">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="confirm_reset">Type RESET to confirm</label>
            <input type="text" id="confirm_reset" name="confirm_reset" class="form-input" placeholder="RESET">
        </div>
        <button type="submit" class="btn btn-warning" style="background:#f59e0b;">Reset Election Data</button>
    </form>
</div>

<?php include 'includes/admin_footer.php'; ?>
