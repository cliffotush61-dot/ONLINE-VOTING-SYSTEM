<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['student_id']) || !isset($_SESSION['preview_vote'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$reg_number = $_SESSION['reg_number'];
$department = $_SESSION['department'];

$male_id = $_SESSION['preview_vote']['male_delegate'];
$female_id = $_SESSION['preview_vote']['female_delegate'];
$dept_id = $_SESSION['preview_vote']['departmental_delegate'];

// Prevent duplicate voting
$check_vote = mysqli_query($conn, "SELECT * FROM votes WHERE student_id = '$student_id'");
if (mysqli_num_rows($check_vote) > 0) {
    die("You have already voted. Voting is irreversible.");
}

$sql = "INSERT INTO votes (
            student_id, reg_number, department,
            male_delegate_candidate_id,
            female_delegate_candidate_id,
            departmental_delegate_candidate_id
        ) VALUES (
            '$student_id', '$reg_number', '$department',
            '$male_id', '$female_id', '$dept_id'
        )";

$result = mysqli_query($conn, $sql);

if ($result) {
    mysqli_query($conn, "UPDATE students SET has_voted = 1 WHERE id = '$student_id'");
    unset($_SESSION['preview_vote']);
    $_SESSION['has_voted'] = 1;
    echo "<h2>Vote submitted successfully.</h2>";
    echo "<p>Your vote has been recorded and cannot be changed.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>