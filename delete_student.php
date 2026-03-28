<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    mysqli_query($conn, "DELETE FROM students WHERE id = $id");
}

header("Location: manage_students.php");
exit();
?>