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

$page_title = "Clean Duplicate Students";
$summary = [
    'duplicate_groups' => 0,
    'deleted_rows' => 0,
    'reassigned_votes' => 0,
    'conflicts' => 0,
    'errors' => 0,
];
$details = [];
$action_message = '';

function evote_fetch_duplicate_student_groups(mysqli $conn): array {
    $groups = [];
    $result = mysqli_query($conn, "SELECT reg_number, COUNT(*) AS c FROM students GROUP BY reg_number HAVING COUNT(*) > 1 ORDER BY reg_number ASC");
    if (!$result) {
        return $groups;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $groups[] = (string) $row['reg_number'];
    }

    return $groups;
}

function evote_fetch_student_group_rows(mysqli $conn, string $regNumber): array {
    $rows = [];
    $stmt = mysqli_prepare($conn, "SELECT id, reg_number, full_name, department, has_voted, is_active, created_at FROM students WHERE reg_number = ? ORDER BY id ASC");
    if (!$stmt) {
        return $rows;
    }

    mysqli_stmt_bind_param($stmt, 's', $regNumber);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function evote_fetch_vote_counts_for_student_ids(mysqli $conn, array $studentIds): array {
    $counts = [];
    if (empty($studentIds)) {
        return $counts;
    }

    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $types = str_repeat('i', count($studentIds));
    $sql = "SELECT student_id, COUNT(*) AS c FROM votes WHERE student_id IN ({$placeholders}) GROUP BY student_id";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $counts;
    }

    $bind = [$types];
    foreach ($studentIds as $index => $id) {
        $bind[] = &$studentIds[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(int) $row['student_id']] = (int) $row['c'];
        }
    }
    mysqli_stmt_close($stmt);
    return $counts;
}

function evote_choose_student_survivor(array $rows, array $voteCounts): array {
    $votedRows = [];
    $flaggedRows = [];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if (!empty($voteCounts[$id])) {
            $votedRows[] = $row;
        } elseif (!empty($row['has_voted'])) {
            $flaggedRows[] = $row;
        }
    }

    if (count($votedRows) === 1) {
        return ['status' => 'safe', 'survivor' => $votedRows[0], 'reason' => 'Vote-linked record retained.'];
    }

    if (count($votedRows) > 1) {
        return ['status' => 'conflict', 'reason' => 'More than one duplicate record already has vote data attached. Manual review required.'];
    }

    if (count($flaggedRows) === 1) {
        return ['status' => 'safe', 'survivor' => $flaggedRows[0], 'reason' => 'Single record marked as voted retained.'];
    }

    if (count($flaggedRows) > 1) {
        return ['status' => 'conflict', 'reason' => 'Multiple duplicate rows are marked as voted. Manual review required.'];
    }

    return ['status' => 'safe', 'survivor' => $rows[0], 'reason' => 'Earliest record retained.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cleanup'])) {
    $groups = evote_fetch_duplicate_student_groups($conn);
    $summary['duplicate_groups'] = count($groups);

    mysqli_begin_transaction($conn);

    try {
        foreach ($groups as $regNumber) {
            $rows = evote_fetch_student_group_rows($conn, $regNumber);
            if (count($rows) < 2) {
                continue;
            }

            $studentIds = array_map(static fn($row) => (int) $row['id'], $rows);
            $voteCounts = evote_fetch_vote_counts_for_student_ids($conn, $studentIds);
            $choice = evote_choose_student_survivor($rows, $voteCounts);

            if ($choice['status'] !== 'safe') {
                $summary['conflicts']++;
                $details[] = [
                    'reg_number' => $regNumber,
                    'status' => 'conflict',
                    'message' => $choice['reason'],
                    'survivor' => null,
                    'deleted' => [],
                ];
                continue;
            }

            $survivor = $choice['survivor'];
            $survivorId = (int) $survivor['id'];
            $deletedIds = [];

            foreach ($rows as $row) {
                $studentId = (int) $row['id'];
                if ($studentId === $survivorId) {
                    continue;
                }

                $hasVotes = !empty($voteCounts[$studentId]);
                if ($hasVotes) {
                    $reassign = mysqli_prepare($conn, "UPDATE votes SET student_id = ?, reg_number = ? WHERE student_id = ?");
                    if (!$reassign) {
                        $summary['errors']++;
                        $details[] = [
                            'reg_number' => $regNumber,
                            'status' => 'error',
                            'message' => 'Unable to prepare vote reassignment.',
                            'survivor' => $survivor,
                            'deleted' => $deletedIds,
                        ];
                        continue 2;
                    }

                    $survivorReg = (string) $survivor['reg_number'];
                    mysqli_stmt_bind_param($reassign, 'isi', $survivorId, $survivorReg, $studentId);
                    if (!mysqli_stmt_execute($reassign)) {
                        mysqli_stmt_close($reassign);
                        $summary['errors']++;
                        $details[] = [
                            'reg_number' => $regNumber,
                            'status' => 'error',
                            'message' => 'Unable to reassign vote records.',
                            'survivor' => $survivor,
                            'deleted' => $deletedIds,
                        ];
                        continue 2;
                    }
                    mysqli_stmt_close($reassign);
                    $summary['reassigned_votes']++;
                }

                $delete = mysqli_prepare($conn, "DELETE FROM students WHERE id = ? AND reg_number = ?");
                if (!$delete) {
                    $summary['errors']++;
                    continue;
                }

                mysqli_stmt_bind_param($delete, 'is', $studentId, $regNumber);
                if (mysqli_stmt_execute($delete)) {
                    $deletedIds[] = $studentId;
                    $summary['deleted_rows']++;
                } else {
                    $summary['errors']++;
                }
                mysqli_stmt_close($delete);
            }

            logAuditAction(
                $conn,
                'admin',
                (int) $_SESSION['admin_id'],
                (string) ($_SESSION['admin_username'] ?? 'admin'),
                'DUPLICATE_STUDENT_CLEANUP',
                "Cleaned duplicate student records for {$regNumber}. Survivor: {$survivorId}. Deleted: " . implode(',', $deletedIds),
                'students',
                $survivorId
            );

            $details[] = [
                'reg_number' => $regNumber,
                'status' => 'cleaned',
                'message' => $choice['reason'],
                'survivor' => $survivor,
                'deleted' => $deletedIds,
            ];
        }

        mysqli_commit($conn);
        $action_message = 'Duplicate cleanup finished successfully.';
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $action_message = 'Duplicate cleanup failed: ' . $e->getMessage();
    }
}

$duplicateGroups = evote_fetch_duplicate_student_groups($conn);
include 'includes/admin_header.php';
?>
<div class="page-header">
    <h1 class="page-title">Clean Duplicate Students</h1>
    <p class="page-subtitle">Review and remove duplicate student accounts before enforcing unique identity rules.</p>
</div>

<div class="content-card">
    <?php if (!empty($action_message)): ?>
        <div class="message-box <?php echo stripos($action_message, 'failed') === false ? 'message-success' : 'message-error'; ?>">
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>

    <div class="message-box message-warning">
        This tool removes only safe duplicates. If more than one row for the same registration number has vote data, the group is left for manual review.
    </div>

    <p style="margin-bottom:16px;">Duplicate registration groups currently found: <strong><?php echo count($duplicateGroups); ?></strong></p>

    <?php if (!empty($duplicateGroups)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Registration Number</th>
                        <th>Rows Found</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicateGroups as $regNumber): ?>
                        <?php $rows = evote_fetch_student_group_rows($conn, $regNumber); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($regNumber); ?></strong></td>
                            <td><?php echo count($rows); ?></td>
                            <td>Will be checked during cleanup</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" style="margin-top:20px;" onsubmit="return confirm('This will remove safe duplicate student rows. Continue?');">
            <button type="submit" name="confirm_cleanup" class="btn btn-danger">Run Duplicate Cleanup</button>
            <a href="manage_students.php" class="btn btn-secondary">Back to Students</a>
        </form>
    <?php else: ?>
        <p>No duplicate registration numbers were found.</p>
        <a href="manage_students.php" class="btn btn-primary">Back to Students</a>
    <?php endif; ?>
</div>

<?php if (!empty($details)): ?>
    <div class="content-card">
        <h2 class="page-title" style="font-size:24px;margin-bottom:16px;">Cleanup Result</h2>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="stat-card"><h3>Groups Processed</h3><div class="stat-value"><?php echo (int) $summary['duplicate_groups']; ?></div></div>
            <div class="stat-card"><h3>Rows Deleted</h3><div class="stat-value"><?php echo (int) $summary['deleted_rows']; ?></div></div>
            <div class="stat-card"><h3>Votes Reassigned</h3><div class="stat-value"><?php echo (int) $summary['reassigned_votes']; ?></div></div>
            <div class="stat-card"><h3>Conflicts</h3><div class="stat-value"><?php echo (int) $summary['conflicts']; ?></div></div>
        </div>

        <div class="table-responsive" style="margin-top:20px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reg Number</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Deleted IDs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $detail): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($detail['reg_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($detail['status']); ?></td>
                            <td><?php echo htmlspecialchars($detail['message']); ?></td>
                            <td><?php echo htmlspecialchars(!empty($detail['deleted']) ? implode(', ', $detail['deleted']) : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/admin_footer.php'; ?>
