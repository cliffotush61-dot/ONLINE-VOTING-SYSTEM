<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: vote.php");
    exit();
}

$male_id = (int)$_POST['male_delegate'];
$female_id = (int)$_POST['female_delegate'];
$dept_id = (int)$_POST['departmental_delegate'];

$_SESSION['preview_vote'] = [
    'male_delegate' => $male_id,
    'female_delegate' => $female_id,
    'departmental_delegate' => $dept_id
];

function getCandidate($conn, $id) {
    $result = mysqli_query($conn, "SELECT * FROM candidates WHERE id = $id");
    return mysqli_fetch_assoc($result);
}

$male_candidate = getCandidate($conn, $male_id);
$female_candidate = getCandidate($conn, $female_id);
$dept_candidate = getCandidate($conn, $dept_id);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Preview Vote</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Evoting</div>
    </div>

    <div class="container">
        <div class="preview-box">
            <h2>Preview Your Vote</h2>
            <p>Please confirm your selected candidates before final submission. Once submitted, the vote cannot be changed.</p>

            <h3>Male Delegate</h3>
            <p><?php echo htmlspecialchars($male_candidate['full_name']); ?></p>

            <h3>Female Delegate</h3>
            <p><?php echo htmlspecialchars($female_candidate['full_name']); ?></p>

            <h3>Departmental Delegate</h3>
            <p><?php echo htmlspecialchars($dept_candidate['full_name']); ?></p>

            <br>

            <form method="POST" action="submit_vote.php">
                <button type="submit" class="primary-btn">Confirm and Submit Vote</button>
            </form>

            <br>
            <a href="vote.php">Go Back and Edit</a>
        </div>
    </div>
</body>
</html>