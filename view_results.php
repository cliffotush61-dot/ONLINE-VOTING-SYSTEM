<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$sql = "
SELECT c.reg_number, c.full_name, c.department, c.position,
(
    (SELECT COUNT(*) FROM votes WHERE male_delegate_candidate_id = c.id) +
    (SELECT COUNT(*) FROM votes WHERE female_delegate_candidate_id = c.id) +
    (SELECT COUNT(*) FROM votes WHERE departmental_delegate_candidate_id = c.id)
) AS total_votes
FROM candidates c
ORDER BY c.department, c.position, total_votes DESC, c.full_name
";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Election Results</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">Evoting Admin</div>
    <div class="topbar-links">
        <a href="admin_dashboard.php" class="btn-home">Dashboard</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <h1 class="page-title">Election Results</h1>
    <p class="page-subtitle">Vote totals by candidate, department, and position.</p>

    <div class="table-wrap">
        <table>
            <tr>
                <th>Reg Number</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Total Votes</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['department']); ?></td>
                <td><?php echo htmlspecialchars($row['position']); ?></td>
                <td><strong><?php echo (int)$row['total_votes']; ?></strong></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>