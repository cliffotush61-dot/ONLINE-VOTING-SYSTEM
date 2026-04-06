<?php
$page_title = "Manage Students";
include 'includes/admin_header.php';

$result = mysqli_query($conn, "SELECT * FROM students ORDER BY department, full_name");
?>

<div class="page-header">
    <h1 class="page-title">Manage Students</h1>
    <p class="page-subtitle">View all registered student voters and their voting status</p>
</div>

<div class="content-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Registration Number</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Has Voted</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 0;
                while ($row = mysqli_fetch_assoc($result)): 
                    $count++;
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['reg_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $row['has_voted'] ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #7f1d1d;'; ?>">
                                <?php echo $row['has_voted'] ? '✓ Yes' : '✕ No'; ?>
                            </span>
                        </td>
                        <td>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $row['is_active'] ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #7f1d1d;'; ?>">
                                <?php echo $row['is_active'] ? '✓ Yes' : '✕ No'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_student.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;">Edit</a>
                            <a href="delete_student.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php if ($count === 0): ?>
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <p style="font-size: 16px;">No students registered yet.</p>
                <a href="add_student.php" class="btn btn-primary mt-20">➕ Add First Student</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
