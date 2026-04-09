<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/
if (evote_normalize_auth_session($conn) === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'student' || !isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$studentId   = (int) $_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? ($_SESSION['username'] ?? 'Student');
$department  = $_SESSION['department'] ?? '';
$is_admin_session = (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id']));
$back_href = $is_admin_session ? 'admin_dashboard.php' : 'dashboard.php';
$back_label = $is_admin_session ? 'Admin Dashboard' : 'Home';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function candidateName(array $row): string
{
    return trim((string)($row['full_name'] ?? $row['name'] ?? 'Unnamed Candidate'));
}

function candidatePhoto(array $row): string
{
    $photo = trim((string)($row['photo'] ?? ''));
    if ($photo === '') {
        return '';
    }
    return $photo;
}

function getElectionStatus(mysqli $conn): array
{
    // Adjust if your project already has an elections/settings table.
    $fallback = [
        'status' => 'open',
        'label'  => 'Election is live.',
        'open'   => true
    ];

    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'elections'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        return $fallback;
    }

    $sql = "SELECT status, start_datetime, end_datetime, title
            FROM elections
            ORDER BY id DESC
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        return $fallback;
    }

    $row = mysqli_fetch_assoc($res);
    $now = new DateTime('now');
    $start = !empty($row['start_datetime']) ? new DateTime($row['start_datetime']) : null;
    $end   = !empty($row['end_datetime']) ? new DateTime($row['end_datetime']) : null;

    $status = 'open';
    $label  = 'Election is live.';
    $open   = true;

    if ($start && $now < $start) {
        $status = 'scheduled';
        $label  = 'Election has not opened yet.';
        $open   = false;
    } elseif ($end && $now > $end) {
        $status = 'closed';
        $label  = 'Election has closed.';
        $open   = false;
    } elseif (!empty($row['status']) && strtolower((string)$row['status']) === 'closed') {
        $status = 'closed';
        $label  = 'Election has closed.';
        $open   = false;
    }

    return [
        'status' => $status,
        'label'  => $label,
        'open'   => $open
    ];
}

function studentAlreadyVoted(mysqli $conn, int $studentId): bool
{
    $hasVotedSession = $_SESSION['has_voted'] ?? null;
    if ((string)$hasVotedSession === '1' || $hasVotedSession === 1) {
        return true;
    }

    $checkVotesTable = mysqli_query($conn, "SHOW TABLES LIKE 'votes'");
    if ($checkVotesTable && mysqli_num_rows($checkVotesTable) > 0) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM votes WHERE student_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) > 0) {
            return true;
        }
    }

    $checkStudentTable = mysqli_prepare($conn, "SELECT has_voted FROM students WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStudentTable, 'i', $studentId);
    mysqli_stmt_execute($checkStudentTable);
    $res = mysqli_stmt_get_result($checkStudentTable);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return ((int)($row['has_voted'] ?? 0) === 1);
    }

    return false;
}

function fetchCandidatesByPosition(mysqli $conn, string $department): array
{
    $grouped = [
        'Male Delegate' => [],
        'Female Delegate' => [],
        'Departmental Delegate' => []
    ];

    $hasIsActive = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM candidates LIKE 'is_active'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) {
        $hasIsActive = true;
    }

    $sql = $hasIsActive
        ? "SELECT * FROM candidates WHERE LOWER(TRIM(department)) = LOWER(TRIM(?)) AND is_active = 1 ORDER BY position, id ASC"
        : "SELECT * FROM candidates WHERE LOWER(TRIM(department)) = LOWER(TRIM(?)) ORDER BY position, id ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $department);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if (!$res) {
        return $grouped;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $positionSlug = evote_position_storage_value((string) ($row['position'] ?? ''));
        $positionLabel = evote_position_display_label($positionSlug);

        if (!isset($grouped[$positionLabel])) {
            continue;
        }

        $row['position'] = $positionSlug;
        $grouped[$positionLabel][] = $row;
    }

    return $grouped;
}

function insertVote(mysqli $conn, int $studentId, int $candidateId, string $position, string $department): bool
{
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'votes'");
    if (!$check || mysqli_num_rows($check) === 0) {
        return false;
    }

    $columns = [];
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM votes");
    if ($colRes) {
        while ($col = mysqli_fetch_assoc($colRes)) {
            $columns[] = $col['Field'];
        }
    }

    $hasCreatedAt = in_array('created_at', $columns, true);
    $hasDepartment = in_array('department', $columns, true);
    $hasPosition = in_array('position', $columns, true);

    $fields = ['student_id', 'candidate_id'];
    $placeholders = '?, ?';
    $types = 'ii';
    $values = [$studentId, $candidateId];

    if ($hasPosition) {
        $fields[] = 'position';
        $placeholders .= ', ?';
        $types .= 's';
        $values[] = $position;
    }

    if ($hasDepartment) {
        $fields[] = 'department';
        $placeholders .= ', ?';
        $types .= 's';
        $values[] = $department;
    }

    if ($hasCreatedAt) {
        $fields[] = 'created_at';
        $placeholders .= ', NOW()';
    }

    $sql = "INSERT INTO votes (" . implode(', ', $fields) . ") VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, $types, ...$values);
    return mysqli_stmt_execute($stmt);
}

/*
|--------------------------------------------------------------------------
| LIVE DATA
|--------------------------------------------------------------------------
*/
$election = getElectionStatus($conn);
$alreadyVoted = studentAlreadyVoted($conn, $studentId);

if ($department === '') {
    $stmt = mysqli_prepare($conn, "SELECT department, full_name FROM students WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $department = (string)($row['department'] ?? '');
        $studentName = (string)($row['full_name'] ?? $studentName);
        $_SESSION['department'] = $department;
        $_SESSION['student_name'] = $studentName;
    }
}

$candidates = fetchCandidatesByPosition($conn, $department);

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| VOTE SUBMISSION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$election['open']) {
        $errors[] = 'Voting is currently unavailable because the election is not open.';
    }

    if ($alreadyVoted) {
        $errors[] = 'You have already submitted your vote. Voting is final.';
    }

    $maleDelegate         = (int)($_POST['male_delegate'] ?? 0);
    $femaleDelegate       = (int)($_POST['female_delegate'] ?? 0);
    $departmentalDelegate = (int)($_POST['departmental_delegate'] ?? 0);

    if ($maleDelegate <= 0) {
        $errors[] = 'Please select one Male Delegate.';
    }

    if ($femaleDelegate <= 0) {
        $errors[] = 'Please select one Female Delegate.';
    }

    if ($departmentalDelegate <= 0) {
        $errors[] = 'Please select one Departmental Delegate.';
    }

    $validMap = [];
    foreach ($candidates as $position => $rows) {
        foreach ($rows as $row) {
            $validMap[(int)$row['id']] = $position;
        }
    }

    foreach ([$maleDelegate, $femaleDelegate, $departmentalDelegate] as $id) {
        if ($id > 0 && !isset($validMap[$id])) {
            $errors[] = 'One or more selected candidates are invalid for your department.';
            break;
        }
    }

    if (empty($errors)) {
        $_SESSION['preview_vote'] = [
            'male_delegate' => $maleDelegate,
            'female_delegate' => $femaleDelegate,
            'departmental_delegate' => $departmentalDelegate,
        ];

        session_write_close();
        header('Location: submit_vote.php');
        exit();
    }
}

function renderCandidateCard(array $candidate, string $inputName, bool $disabled = false): void
{
    $id = (int)$candidate['id'];
    $name = candidateName($candidate);
    $manifesto = trim((string)($candidate['manifesto'] ?? 'No manifesto available.'));
    $photo = candidatePhoto($candidate);
    ?>
    <label class="candidate-card">
        <input type="radio" name="<?php echo e($inputName); ?>" value="<?php echo $id; ?>" <?php echo $disabled ? 'disabled' : ''; ?>>
        <div class="candidate-card-inner">
            <div class="candidate-top">
                <div class="avatar-wrap">
                    <?php if ($photo !== ''): ?>
                        <img src="<?php echo e($photo); ?>" alt="<?php echo e($name); ?>" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-fallback"><?php echo e(strtoupper(substr($name, 0, 1))); ?></div>
                    <?php endif; ?>
                </div>
                <div class="candidate-meta">
                    <h3><?php echo e($name); ?></h3>
                    <span class="badge">Candidate</span>
                </div>
                <div class="pick-pill">Select</div>
            </div>

            <div class="manifesto-block">
                <h4>Manifesto</h4>
                <p><?php echo e($manifesto); ?></p>
            </div>
        </div>
    </label>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Now | Department Voting Portal</title>
    <style>
        :root{
            --bg:#081225;
            --bg-soft:#0d1b34;
            --card:#ffffff;
            --text:#0f172a;
            --muted:#64748b;
            --primary:#4f46e5;
            --primary-2:#7c3aed;
            --cyan:#06b6d4;
            --gold:#f59e0b;
            --green:#10b981;
            --red:#ef4444;
            --line:#e2e8f0;
            --shadow:0 20px 45px rgba(2, 6, 23, 0.18);
            --radius:24px;
        }

        *{
            box-sizing:border-box;
            margin:0;
            padding:0;
        }

        body{
            font-family: "Segoe UI", Inter, Arial, sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at top left, rgba(124,58,237,.28), transparent 28%),
                radial-gradient(circle at top right, rgba(6,182,212,.22), transparent 24%),
                linear-gradient(180deg, #07111f 0%, #0b1830 26%, #eef4ff 26%, #f8fbff 100%);
            min-height:100vh;
        }

        .page-shell{
            width:min(1400px, 94%);
            margin:0 auto;
            padding:28px 0 60px;
        }

        .hero{
            position:relative;
            overflow:hidden;
            border-radius:36px;
            padding:34px;
            background:
                linear-gradient(135deg, rgba(79,70,229,.92), rgba(124,58,237,.9) 42%, rgba(6,182,212,.88));
            box-shadow: var(--shadow);
            color:#fff;
        }

        .hero::before,
        .hero::after{
            content:"";
            position:absolute;
            border-radius:999px;
            filter:blur(8px);
            opacity:.25;
        }

        .hero::before{
            width:280px;
            height:280px;
            background:#fff;
            right:-70px;
            top:-90px;
        }

        .hero::after{
            width:220px;
            height:220px;
            background:#fde68a;
            left:-60px;
            bottom:-90px;
        }

        .hero-top{
            position:relative;
            z-index:2;
            display:flex;
            justify-content:space-between;
            gap:20px;
            align-items:flex-start;
            flex-wrap:wrap;
        }

        .hero-copy h1{
            font-size: clamp(2.2rem, 4vw, 4rem);
            line-height:1.04;
            font-weight:900;
            letter-spacing:-1.2px;
            margin-bottom:14px;
            max-width:800px;
        }

        .hero-copy p{
            font-size:1.08rem;
            line-height:1.8;
            color:rgba(255,255,255,.93);
            max-width:760px;
        }

        .hero-side{
            position:relative;
            z-index:2;
            min-width:300px;
            flex:1 1 340px;
            display:flex;
            justify-content:flex-end;
        }

        .glass-panel{
            width:min(420px, 100%);
            background:rgba(255,255,255,.14);
            border:1px solid rgba(255,255,255,.22);
            backdrop-filter: blur(14px);
            border-radius:26px;
            padding:22px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
        }

        .panel-label{
            display:inline-flex;
            align-items:center;
            gap:10px;
            font-weight:800;
            font-size:.95rem;
            margin-bottom:14px;
        }

        .student-name{
            font-size:1.35rem;
            font-weight:900;
            margin-bottom:6px;
        }

        .student-dept{
            color:rgba(255,255,255,.88);
            margin-bottom:18px;
            font-size:1rem;
        }

        .panel-grid{
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:12px;
        }

        .mini-stat{
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.18);
            border-radius:20px;
            padding:14px;
        }

        .mini-stat small{
            display:block;
            opacity:.9;
            margin-bottom:6px;
        }

        .mini-stat strong{
            font-size:1rem;
            font-weight:900;
        }

        .hero-badges{
            position:relative;
            z-index:2;
            margin-top:24px;
            display:flex;
            flex-wrap:wrap;
            gap:12px;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            padding:12px 18px;
            border-radius:999px;
            background:rgba(255,255,255,.14);
            border:1px solid rgba(255,255,255,.2);
            color:#fff;
            font-weight:800;
            letter-spacing:.1px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.05);
        }

        .home-link{
            text-decoration:none;
            color:#fff;
            background:rgba(255,255,255,.16);
            border:1px solid rgba(255,255,255,.22);
            padding:12px 18px;
            border-radius:999px;
            font-weight:900;
            transition:transform .2s ease, background .2s ease;
        }

        .home-link:hover{
            background:rgba(255,255,255,.24);
            transform:translateY(-1px);
        }

        .status-open{ background:rgba(16,185,129,.18); }
        .status-closed{ background:rgba(239,68,68,.18); }
        .status-scheduled{ background:rgba(245,158,11,.18); }

        .content-grid{
            margin-top:28px;
            display:grid;
            grid-template-columns: 1fr 360px;
            gap:24px;
            align-items:start;
        }

        .main-column{
            display:grid;
            gap:24px;
        }

        .section-card{
            background:var(--card);
            border-radius:30px;
            padding:28px;
            box-shadow:var(--shadow);
            border:1px solid rgba(226,232,240,.8);
        }

        .section-head{
            display:flex;
            justify-content:space-between;
            gap:18px;
            flex-wrap:wrap;
            align-items:center;
            margin-bottom:18px;
        }

        .section-title h2{
            font-size:2rem;
            font-weight:900;
            letter-spacing:-.6px;
            margin-bottom:8px;
        }

        .section-title p{
            color:var(--muted);
            line-height:1.7;
        }

        .position-chip{
            padding:10px 16px;
            border-radius:999px;
            background:linear-gradient(135deg, #eef2ff, #ecfeff);
            color:#1e1b4b;
            font-weight:900;
            border:1px solid #dbeafe;
        }

        .candidate-grid{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap:18px;
        }

        .candidate-card{
            display:block;
            cursor:pointer;
        }

        .candidate-card input{
            display:none;
        }

        .candidate-card-inner{
            height:100%;
            border:1px solid #e2e8f0;
            border-radius:26px;
            padding:18px;
            background:
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            transition:transform .24s ease, box-shadow .24s ease, border-color .24s ease;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
        }

        .candidate-card:hover .candidate-card-inner{
            transform:translateY(-5px);
            box-shadow:0 20px 35px rgba(79,70,229,.12);
            border-color:#c7d2fe;
        }

        .candidate-card input:checked + .candidate-card-inner{
            border:2px solid var(--primary);
            box-shadow:0 18px 38px rgba(79,70,229,.2);
            background:
                linear-gradient(180deg, #ffffff 0%, #eef2ff 100%);
        }

        .candidate-top{
            display:flex;
            align-items:center;
            gap:14px;
            margin-bottom:16px;
        }

        .avatar-wrap{
            flex-shrink:0;
        }

        .avatar-img,
        .avatar-fallback{
            width:64px;
            height:64px;
            border-radius:20px;
            object-fit:cover;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:900;
            font-size:1.35rem;
            color:#fff;
            background:linear-gradient(135deg, var(--primary), var(--cyan));
            box-shadow:0 10px 20px rgba(79,70,229,.18);
        }

        .candidate-meta{
            flex:1;
            min-width:0;
        }

        .candidate-meta h3{
            font-size:1.12rem;
            font-weight:900;
            color:#0f172a;
            margin-bottom:8px;
        }

        .badge{
            display:inline-flex;
            align-items:center;
            padding:6px 10px;
            border-radius:999px;
            background:#eff6ff;
            color:#1d4ed8;
            font-size:.8rem;
            font-weight:800;
        }

        .pick-pill{
            flex-shrink:0;
            padding:8px 12px;
            border-radius:999px;
            background:#f1f5f9;
            color:#334155;
            font-size:.85rem;
            font-weight:800;
        }

        .candidate-card input:checked + .candidate-card-inner .pick-pill{
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            color:#fff;
        }

        .manifesto-block{
            border-top:1px solid #e2e8f0;
            padding-top:14px;
        }

        .manifesto-block h4{
            font-size:.88rem;
            text-transform:uppercase;
            letter-spacing:.6px;
            color:#475569;
            margin-bottom:8px;
        }

        .manifesto-block p{
            color:#334155;
            line-height:1.7;
            font-size:.97rem;
        }

        .sidebar{
            position:sticky;
            top:18px;
            display:grid;
            gap:20px;
        }

        .summary-card{
            background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border:1px solid #e2e8f0;
            border-radius:30px;
            padding:24px;
            box-shadow:var(--shadow);
        }

        .summary-card h3{
            font-size:1.4rem;
            font-weight:900;
            margin-bottom:8px;
        }

        .summary-card p{
            color:var(--muted);
            line-height:1.7;
            margin-bottom:18px;
        }

        .summary-box{
            border:1px solid #e2e8f0;
            border-radius:22px;
            padding:16px;
            margin-bottom:14px;
            background:#fff;
        }

        .summary-box strong{
            display:block;
            margin-bottom:6px;
            font-size:.95rem;
            color:#0f172a;
        }

        .summary-value{
            color:#475569;
            font-weight:700;
        }

        .vote-guard{
            padding:14px 16px;
            border-radius:18px;
            font-weight:800;
            margin-bottom:14px;
        }

        .guard-open{
            background:#ecfdf5;
            color:#065f46;
            border:1px solid #a7f3d0;
        }

        .guard-closed{
            background:#fef2f2;
            color:#991b1b;
            border:1px solid #fecaca;
        }

        .guard-warn{
            background:#fffbeb;
            color:#92400e;
            border:1px solid #fde68a;
        }

        .alert{
            border-radius:20px;
            padding:16px 18px;
            margin-bottom:18px;
            font-weight:700;
            line-height:1.6;
        }

        .alert-error{
            background:#fef2f2;
            color:#991b1b;
            border:1px solid #fecaca;
        }

        .alert-success{
            background:#ecfdf5;
            color:#065f46;
            border:1px solid #a7f3d0;
        }

        .actions{
            display:grid;
            gap:12px;
            margin-top:18px;
        }

        .btn{
            border:none;
            border-radius:18px;
            padding:16px 20px;
            font-weight:900;
            font-size:1rem;
            cursor:pointer;
            transition:transform .2s ease, opacity .2s ease, box-shadow .2s ease;
            width:100%;
        }

        .btn:hover{
            transform:translateY(-2px);
            opacity:.97;
        }

        .btn-primary{
            color:#fff;
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow:0 18px 28px rgba(79,70,229,.24);
        }

        .btn-secondary{
            color:#0f172a;
            background:linear-gradient(135deg, #e2e8f0, #f8fafc);
            border:1px solid #cbd5e1;
        }

        .footer-note{
            margin-top:12px;
            font-size:.9rem;
            color:#64748b;
            text-align:center;
            line-height:1.6;
        }

        .empty-state{
            padding:24px;
            border-radius:24px;
            border:1px dashed #cbd5e1;
            background:#f8fafc;
            color:#475569;
            line-height:1.7;
        }

        .modal{
            position:fixed;
            inset:0;
            background:rgba(2,6,23,.55);
            display:none;
            align-items:center;
            justify-content:center;
            padding:18px;
            z-index:9999;
        }

        .modal.active{
            display:flex;
        }

        .modal-card{
            width:min(760px, 100%);
            background:#fff;
            border-radius:30px;
            padding:28px;
            box-shadow:0 28px 60px rgba(2,6,23,.28);
        }

        .modal-head{
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:center;
            margin-bottom:18px;
        }

        .modal-head h3{
            font-size:1.7rem;
            font-weight:900;
        }

        .close-modal{
            border:none;
            width:46px;
            height:46px;
            border-radius:14px;
            background:#eef2ff;
            color:#312e81;
            font-weight:900;
            cursor:pointer;
        }

        .preview-list{
            display:grid;
            gap:14px;
            margin:18px 0 24px;
        }

        .preview-item{
            border:1px solid #e2e8f0;
            border-radius:20px;
            padding:16px;
            background:#f8fbff;
        }

        .preview-item small{
            display:block;
            margin-bottom:8px;
            text-transform:uppercase;
            letter-spacing:.5px;
            color:#64748b;
            font-weight:800;
        }

        .preview-item strong{
            font-size:1.05rem;
            color:#0f172a;
        }

        .locked{
            opacity:.75;
            pointer-events:none;
        }

        @media (max-width: 1180px){
            .content-grid{
                grid-template-columns:1fr;
            }

            .sidebar{
                position:relative;
                top:0;
            }
        }

        @media (max-width: 768px){
            .hero{
                padding:24px;
                border-radius:28px;
            }

            .section-card,
            .summary-card{
                padding:20px;
                border-radius:24px;
            }

            .hero-copy h1{
                font-size:2.5rem;
            }

            .panel-grid{
                grid-template-columns:1fr;
            }

            .section-title h2{
                font-size:1.6rem;
            }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <header class="hero">
        <div class="hero-top">
            <div class="hero-copy">
                <h1>Department Voting Portal</h1>
                <p>
                    Select one candidate for each required position. Review your choices carefully before submission,
                    because your final ballot cannot be edited after confirmation.
                </p>
            </div>

            <div class="hero-side">
                <div class="glass-panel">
                    <div class="panel-label">🎓 Student Voting Profile</div>
                    <div class="student-name"><?php echo e($studentName); ?></div>
                    <div class="student-dept"><?php echo e($department !== '' ? $department : 'Department not found'); ?></div>

                    <div class="panel-grid">
                        <div class="mini-stat">
                            <small>Required Positions</small>
                            <strong>3 Positions</strong>
                        </div>
                        <div class="mini-stat">
                            <small>Submission Rule</small>
                            <strong>Single Final Vote</strong>
                        </div>
                        <div class="mini-stat">
                            <small>Role</small>
                            <strong>Student Voter</strong>
                        </div>
                        <div class="mini-stat">
                            <small>Status</small>
                            <strong><?php echo e($election['label']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-badges">
            <a href="<?php echo e($back_href); ?>" class="home-link"><?php echo e($back_label); ?></a>
            <span class="hero-badge"><?php echo e($department !== '' ? $department : 'No Department'); ?></span>
            <span class="hero-badge">3 Required Positions</span>
            <span class="hero-badge">Single Final Submission</span>
            <span class="hero-badge <?php echo $election['status'] === 'open' ? 'status-open' : ($election['status'] === 'closed' ? 'status-closed' : 'status-scheduled'); ?>">
                <?php echo e($election['label']); ?>
            </span>
            <?php if ($alreadyVoted): ?>
                <span class="hero-badge status-closed">Vote already submitted</span>
            <?php endif; ?>
        </div>
    </header>

    <div class="content-grid">
        <main class="main-column">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo e($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo e($success); ?></div>
            <?php endif; ?>

            <form method="POST" id="voteForm" class="<?php echo ($alreadyVoted || !$election['open']) ? 'locked' : ''; ?>">
                <section class="section-card">
                    <div class="section-head">
                        <div class="section-title">
                            <h2>Male Delegate</h2>
                            <p>Choose one male candidate to represent the department in the male delegate position.</p>
                        </div>
                        <div class="position-chip">1 Required Choice</div>
                    </div>

                    <?php if (!empty($candidates['Male Delegate'])): ?>
                        <div class="candidate-grid">
                            <?php foreach ($candidates['Male Delegate'] as $candidate): ?>
                                <?php renderCandidateCard($candidate, 'male_delegate', $alreadyVoted || !$election['open']); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No male delegate candidates are currently available for your department.</div>
                    <?php endif; ?>
                </section>

                <section class="section-card">
                    <div class="section-head">
                        <div class="section-title">
                            <h2>Female Delegate</h2>
                            <p>Choose one female candidate to represent the department in the female delegate position.</p>
                        </div>
                        <div class="position-chip">1 Required Choice</div>
                    </div>

                    <?php if (!empty($candidates['Female Delegate'])): ?>
                        <div class="candidate-grid">
                            <?php foreach ($candidates['Female Delegate'] as $candidate): ?>
                                <?php renderCandidateCard($candidate, 'female_delegate', $alreadyVoted || !$election['open']); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No female delegate candidates are currently available for your department.</div>
                    <?php endif; ?>
                </section>

                <section class="section-card">
                    <div class="section-head">
                        <div class="section-title">
                            <h2>Departmental Delegate</h2>
                            <p>Choose one candidate for the departmental delegate position.</p>
                        </div>
                        <div class="position-chip">1 Required Choice</div>
                    </div>

                    <?php if (!empty($candidates['Departmental Delegate'])): ?>
                        <div class="candidate-grid">
                            <?php foreach ($candidates['Departmental Delegate'] as $candidate): ?>
                                <?php renderCandidateCard($candidate, 'departmental_delegate', $alreadyVoted || !$election['open']); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No departmental delegate candidates are currently available for your department.</div>
                    <?php endif; ?>
                </section>
            </form>
        </main>

        <aside class="sidebar">
            <div class="summary-card">
                <h3>Ballot Control</h3>
                <p>Confirm all three selections carefully. This portal is designed for a single final submission.</p>

                <?php if ($election['open'] && !$alreadyVoted): ?>
                    <div class="vote-guard guard-open">Voting is open. You may complete your ballot now.</div>
                <?php elseif ($alreadyVoted): ?>
                    <div class="vote-guard guard-closed">You have already voted. This ballot is now locked.</div>
                <?php elseif ($election['status'] === 'scheduled'): ?>
                    <div class="vote-guard guard-warn">The election has not opened yet.</div>
                <?php else: ?>
                    <div class="vote-guard guard-closed">The election is closed. Voting is unavailable.</div>
                <?php endif; ?>

                <div class="summary-box">
                    <strong>Male Delegate</strong>
                    <div class="summary-value" id="summaryMale">No selection yet</div>
                </div>

                <div class="summary-box">
                    <strong>Female Delegate</strong>
                    <div class="summary-value" id="summaryFemale">No selection yet</div>
                </div>

                <div class="summary-box">
                    <strong>Departmental Delegate</strong>
                    <div class="summary-value" id="summaryDept">No selection yet</div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="previewBtn" <?php echo ($alreadyVoted || !$election['open']) ? 'disabled' : ''; ?>>
                        Preview Selection
                    </button>
                    <button type="submit" form="voteForm" class="btn btn-primary" id="submitBtn" style="display:none;" <?php echo ($alreadyVoted || !$election['open']) ? 'disabled' : ''; ?>>
                        Confirm and Submit Vote
                    </button>
                </div>

                <div class="footer-note">
                    Make deliberate choices. The system records only one final ballot per student account.
                </div>
            </div>
        </aside>
    </div>
</div>

<div class="modal" id="previewModal">
    <div class="modal-card">
        <div class="modal-head">
            <h3>Preview Your Ballot</h3>
            <button type="button" class="close-modal" id="closeModal">×</button>
        </div>

        <p style="color:#64748b; line-height:1.7;">
            Review your selected candidates below. Once submitted, your vote becomes final.
        </p>

        <div class="preview-list">
            <div class="preview-item">
                <small>Male Delegate</small>
                <strong id="previewMale">No selection</strong>
            </div>
            <div class="preview-item">
                <small>Female Delegate</small>
                <strong id="previewFemale">No selection</strong>
            </div>
            <div class="preview-item">
                <small>Departmental Delegate</small>
                <strong id="previewDept">No selection</strong>
            </div>
        </div>

        <div class="actions" style="grid-template-columns:1fr 1fr;">
            <button type="button" class="btn btn-secondary" id="backToEdit">Go Back</button>
            <button type="button" class="btn btn-primary" id="finalSubmitBtn">Submit Final Vote</button>
        </div>
    </div>
</div>

<script>
    function getSelectedCandidateName(groupName) {
        const checked = document.querySelector(`input[name="${groupName}"]:checked`);
        if (!checked) return 'No selection yet';

        const card = checked.closest('.candidate-card');
        const nameEl = card ? card.querySelector('.candidate-meta h3') : null;
        return nameEl ? nameEl.textContent.trim() : 'No selection yet';
    }

    function refreshSummary() {
        const male = getSelectedCandidateName('male_delegate');
        const female = getSelectedCandidateName('female_delegate');
        const dept = getSelectedCandidateName('departmental_delegate');

        document.getElementById('summaryMale').textContent = male;
        document.getElementById('summaryFemale').textContent = female;
        document.getElementById('summaryDept').textContent = dept;

        document.getElementById('previewMale').textContent = male;
        document.getElementById('previewFemale').textContent = female;
        document.getElementById('previewDept').textContent = dept;
    }

    document.querySelectorAll('input[type="radio"]').forEach(input => {
        input.addEventListener('change', refreshSummary);
    });

    refreshSummary();

    const previewBtn = document.getElementById('previewBtn');
    const submitBtn = document.getElementById('submitBtn');
    const modal = document.getElementById('previewModal');
    const closeModal = document.getElementById('closeModal');
    const backToEdit = document.getElementById('backToEdit');
    const finalSubmitBtn = document.getElementById('finalSubmitBtn');
    const voteForm = document.getElementById('voteForm');

    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            const male = document.querySelector('input[name="male_delegate"]:checked');
            const female = document.querySelector('input[name="female_delegate"]:checked');
            const dept = document.querySelector('input[name="departmental_delegate"]:checked');

            if (!male || !female || !dept) {
                alert('Please make one selection for all three positions before previewing your ballot.');
                return;
            }

            refreshSummary();
            modal.classList.add('active');
        });
    }

    if (closeModal) {
        closeModal.addEventListener('click', function () {
            modal.classList.remove('active');
        });
    }

    if (backToEdit) {
        backToEdit.addEventListener('click', function () {
            modal.classList.remove('active');
        });
    }

    if (finalSubmitBtn) {
        finalSubmitBtn.addEventListener('click', function () {
            voteForm.submit();
        });
    }

    window.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
</script>
</body>
</html>
