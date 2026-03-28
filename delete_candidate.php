<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $sql = "DELETE FROM candidates WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        header("Location: manage_candidate.php?msg=deleted");
        exit();
    } else {
        echo "Error deleting candidate: " . mysqli_error($conn);
    }
} else {
    echo "No candidate selected.";
}
?>