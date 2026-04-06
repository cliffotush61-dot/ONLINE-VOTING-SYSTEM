<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $admin_id = $_SESSION['admin_id'] ?? 0;

    $stmt = mysqli_prepare($conn, "SELECT reg_number FROM students WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($student) {
        logAuditAction($conn, 'admin', $admin_id, (string) ($_SESSION['admin_username'] ?? 'admin'), 'STUDENT_DELETED', "Deleted student {$student['reg_number']}.", 'students', $id);
        $delete = mysqli_prepare($conn, "DELETE FROM students WHERE id = ?");
        mysqli_stmt_bind_param($delete, 'i', $id);
        mysqli_stmt_execute($delete);
        mysqli_stmt_close($delete);
    }
}

header("Location: manage_students.php");
exit();
?>
