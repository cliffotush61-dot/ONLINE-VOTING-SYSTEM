<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

if (evote_normalize_auth_session($conn) === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (evote_session_role() !== 'student') {
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

$_SESSION['reg_number'] = $student_row['reg_number'];
$_SESSION['department'] = $student_row['department'];
$_SESSION['student_id'] = $student_id;

$election_state = evote_election_state($conn);
if ((int) ($student_row['has_voted'] ?? 0) === 1) {
    logAuditAction($conn, 'student', $student_id, (string) $student_row['reg_number'], 'DUPLICATE_VOTE_ATTEMPT', 'Student attempted to preview a ballot after already voting.', 'votes', $student_id);
    header("Location: dashboard.php");
    exit();
}

if (!$election_state['exists'] || $election_state['status'] !== 'open') {
    logAuditAction($conn, 'student', $student_id, (string) ($_SESSION['reg_number'] ?? ''), 'VOTE_BLOCKED', $election_state['message'] ?: 'Election is not open.', 'elections', $election_state['id']);
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: vote.php");
    exit();
}

$male_id = (int)$_POST['male_delegate'];
$female_id = (int)$_POST['female_delegate'];
$dept_id = (int)$_POST['departmental_delegate'];

function evote_fetch_preview_candidate(mysqli $conn, int $candidate_id, string $department, string $position): ?array {
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

$_SESSION['preview_vote'] = [
    'male_delegate' => $male_id,
    'female_delegate' => $female_id,
    'departmental_delegate' => $dept_id
];

$department = (string) ($_SESSION['department'] ?? '');
$male_candidate = evote_fetch_preview_candidate($conn, $male_id, $department, 'male_delegate');
$female_candidate = evote_fetch_preview_candidate($conn, $female_id, $department, 'female_delegate');
$dept_candidate = evote_fetch_preview_candidate($conn, $dept_id, $department, 'departmental_delegate');

if (!$male_candidate || !$female_candidate || !$dept_candidate) {
    unset($_SESSION['preview_vote']);
    logAuditAction($conn, 'student', $student_id, (string) ($_SESSION['reg_number'] ?? ''), 'VOTE_BLOCKED', 'Invalid candidate selection detected during preview.', 'candidates', null);
    header("Location: vote.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Vote | University Election Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/script.js"></script>
</head>
<body>
    <header class="topbar">
        <div class="brand">University Election Portal</div>
        <div class="topbar-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="vote.php">Back to Vote</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <main class="section container scroll-reveal">
        <div class="preview-box scroll-reveal">
            <h2>Review Your Ballot</h2>
            <p class="section-intro">Confirm your selected candidates before final submission. Once submitted, the vote is final.</p>

            <div class="candidate-summary">
                <div>
                    <h3>Male Delegate</h3>
                    <p><?php echo htmlspecialchars($male_candidate['name'] ?? $male_candidate['full_name'] ?? 'Candidate'); ?></p>
                </div>
                <div>
                    <h3>Female Delegate</h3>
                    <p><?php echo htmlspecialchars($female_candidate['name'] ?? $female_candidate['full_name'] ?? 'Candidate'); ?></p>
                </div>
                <div>
                    <h3>Departmental Delegate</h3>
                    <p><?php echo htmlspecialchars($dept_candidate['name'] ?? $dept_candidate['full_name'] ?? 'Candidate'); ?></p>
                </div>
            </div>

            <div class="button-group">
                <form method="POST" action="submit_vote.php">
                    <button type="submit" class="primary-btn">Confirm and Submit Vote</button>
                </form>
                <a class="button-secondary" href="vote.php">Go Back and Edit</a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>© 2026 University Election Portal · Confirm your ballot with confidence.</p>
        </div>
    </footer>
</body>
</html>
