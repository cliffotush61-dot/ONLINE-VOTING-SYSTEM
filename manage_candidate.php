<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$result = mysqli_query($conn, "SELECT * FROM candidates ORDER BY department, position, full_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Candidates</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">Evoting Admin</div>
    <div class="topbar-links">
        <a href="admin_dashboard.php" class="btn-home">Dashboard</a>
        <a href="add_candidate.php" class="btn-home">Add Candidate</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <h1 class="page-title">Manage Candidates</h1>
    <p class="page-subtitle">View and manage all registered candidates.</p>

    <?php
    if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
        echo "<div class='message-box message-success'>Candidate deleted successfully.</div>";
    }
    ?>

    <div class="table-wrap">
        <table>
            <tr>
                <th>Reg Number</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Gender</th>
                <th>Action</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                <td>
                    <?php if (!empty($row['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($row['photo']); ?>" class="thumb" alt="Candidate Photo">
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['department']); ?></td>
                <td><?php echo htmlspecialchars($row['position']); ?></td>
                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                <td>
                    <a class="delete-link" href="delete_candidate.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete candidate?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>