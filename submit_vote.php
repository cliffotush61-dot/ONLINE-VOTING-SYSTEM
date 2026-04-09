<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

$session_role = evote_normalize_auth_session($conn);

if ($session_role === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if ($session_role !== 'student' || !isset($_SESSION['preview_vote'])) {
    header("Location: login.php");
    exit();
}

$student_id = (int) ($_SESSION['student_id'] ?? ($_SESSION['user_id'] ?? 0));
$student_stmt = mysqli_prepare($conn, "SELECT id, reg_number, department, has_voted FROM students WHERE id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($student_stmt, 'i', $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student_row = $student_result ? mysqli_fetch_assoc($student_result) : null;
mysqli_stmt_close($student_stmt);

if (!$student_row) {
    header("Location: login.php");
    exit();
}

$reg_number = (string) $student_row['reg_number'];
$department = (string) $student_row['department'];
$_SESSION['student_id'] = $student_id;
$_SESSION['reg_number'] = $reg_number;
$_SESSION['department'] = $department;

if ((int) ($student_row['has_voted'] ?? 0) === 1) {
    logAuditAction($conn, 'student', $student_id, $reg_number, 'DUPLICATE_VOTE_ATTEMPT', 'Student attempted to submit a ballot after already voting.', 'votes', $student_id);
    unset($_SESSION['preview_vote']);
    header("Location: dashboard.php");
    exit();
}

$election_state = evote_election_state($conn);

if (!$election_state['exists'] || $election_state['status'] !== 'open') {
    logAuditAction($conn, 'student', (int) $student_id, (string) $reg_number, 'VOTE_BLOCKED', $election_state['message'] ?: 'Election is not open.', 'elections', $election_state['id']);
    header("Location: dashboard.php");
    exit();
}

$male_id = $_SESSION['preview_vote']['male_delegate'];
$female_id = $_SESSION['preview_vote']['female_delegate'];
$dept_id = $_SESSION['preview_vote']['departmental_delegate'];

function evote_validate_selected_candidate(mysqli $conn, int $candidate_id, string $department, string $position): ?array {
    $matches = evote_position_match_candidates($position);
    $stmt = mysqli_prepare($conn, "SELECT id, name, department, position, manifesto, photo FROM candidates WHERE id = ? AND department = ? AND (position = ? OR position = ?) LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stored = $matches[0];
    $legacy = $matches[1] ?? $matches[0];
    mysqli_stmt_bind_param($stmt, 'isss', $candidate_id, $department, $stored, $legacy);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $candidate ?: null;
}

$male_candidate = evote_validate_selected_candidate($conn, (int) $male_id, (string) $department, 'male_delegate');
$female_candidate = evote_validate_selected_candidate($conn, (int) $female_id, (string) $department, 'female_delegate');
$dept_candidate = evote_validate_selected_candidate($conn, (int) $dept_id, (string) $department, 'departmental_delegate');

if (!$male_candidate || !$female_candidate || !$dept_candidate) {
    unset($_SESSION['preview_vote']);
    logAuditAction($conn, 'student', (int) $student_id, (string) $reg_number, 'VOTE_BLOCKED', 'Selected candidates failed validation at submission.', 'candidates', null);
    header("Location: vote.php");
    exit();
}

// Prevent duplicate voting
$check_vote_stmt = mysqli_prepare($conn, "SELECT id FROM votes WHERE student_id = ? LIMIT 1");
mysqli_stmt_bind_param($check_vote_stmt, 'i', $student_id);
mysqli_stmt_execute($check_vote_stmt);
$check_vote = mysqli_stmt_get_result($check_vote_stmt);
if ($check_vote && mysqli_num_rows($check_vote) > 0) {
    logAuditAction($conn, 'student', (int) $student_id, (string) $reg_number, 'DUPLICATE_VOTE_ATTEMPT', 'Student attempted to submit a second vote.', 'votes', $student_id);
    die("You have already voted. Voting is irreversible.");
}
mysqli_stmt_close($check_vote_stmt);

// Generate tamper detection data
$previous_hash = evote_latest_hash($conn, 'votes', 'record_hash');
$salt = bin2hex(random_bytes(16));
$timestamp = date('Y-m-d H:i:s');
$vote_data = [
    'student_id' => $student_id,
    'reg_number' => $reg_number,
    'department' => $department,
    'male_delegate_candidate_id' => $male_id,
    'female_delegate_candidate_id' => $female_id,
    'departmental_delegate_candidate_id' => $dept_id,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'created_at' => $timestamp
];
$vote_hash = evote_hash_payload($vote_data, $previous_hash);
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$sql = "INSERT INTO votes (
            student_id, reg_number, department,
            male_delegate_candidate_id,
            female_delegate_candidate_id,
            departmental_delegate_candidate_id,
            previous_hash, record_hash, salt, ip_address, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    'issiiisssss',
    $student_id,
    $reg_number,
    $department,
    $male_id,
    $female_id,
    $dept_id,
    $previous_hash,
    $vote_hash,
    $salt,
    $ip_address,
    $timestamp
);
mysqli_begin_transaction($conn);

$result = mysqli_stmt_execute($stmt);
$vote_id = $result ? mysqli_insert_id($conn) : 0;
mysqli_stmt_close($stmt);

if ($result) {
    $student_select = mysqli_prepare($conn, "SELECT id, reg_number, full_name, password, department, email, has_voted, is_active FROM students WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($student_select, 'i', $student_id);
    mysqli_stmt_execute($student_select);
    $student_result = mysqli_stmt_get_result($student_select);
    $student_row = $student_result ? mysqli_fetch_assoc($student_result) : null;
    mysqli_stmt_close($student_select);

    if ($student_row) {
        $student_row['has_voted'] = 1;
        $student_hash = evote_compute_entity_hash(evote_hash_fields_from_row('students', $student_row));

        $update_stmt = mysqli_prepare($conn, "UPDATE students SET has_voted = 1, record_hash = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $student_hash, $student_id);
        $update_ok = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        if ($update_ok) {
            mysqli_commit($conn);
            logAuditAction($conn, 'student', (int) $student_id, (string) $reg_number, 'VOTE_SUBMITTED', 'Student submitted a ballot.', 'votes', $vote_id);
            unset($_SESSION['preview_vote']);
            $_SESSION['has_voted'] = 1;
            $_SESSION['vote_submitted'] = true;
            header("Location: vote_success.php");
            exit();
        }
    }
}

mysqli_rollback($conn);
echo "Error: " . mysqli_error($conn);
?>
