<?php
session_start();
include 'db.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $admin_id = $_SESSION['admin_id'] ?? 0;

    $stmt = mysqli_prepare($conn, "SELECT reg_number, name FROM candidates WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($candidate) {
        logAuditAction($conn, 'admin', $admin_id, (string) ($_SESSION['admin_username'] ?? 'admin'), 'CANDIDATE_DELETED', "Deleted candidate {$candidate['name']} ({$candidate['reg_number']}).", 'candidates', $id);
        $sql = "DELETE FROM candidates WHERE id = ?";
        $delete = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($delete, 'i', $id);
        if (mysqli_stmt_execute($delete)) {
            mysqli_stmt_close($delete);
            header("Location: manage_candidate.php?msg=deleted");
            exit();
        }
        mysqli_stmt_close($delete);
        echo "Error deleting candidate: " . mysqli_error($conn);
    } else {
        echo "Candidate not found.";
    }
} else {
    echo "No candidate selected.";
}
?>
