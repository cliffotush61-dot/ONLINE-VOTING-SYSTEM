<?php
$page_title = "Manage Candidates";
include 'includes/admin_header.php';

$result = mysqli_query($conn, "SELECT * FROM candidates ORDER BY department, position, name");
?>

<div class="page-header">
    <h1 class="page-title">Manage Candidates</h1>
    <p class="page-subtitle">View and manage all election candidates</p>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="content-card">
        <div class="message-box message-success">✓ Candidate deleted successfully.</div>
    </div>
<?php endif; ?>

<div class="content-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Reg Number</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Gender</th>
                    <th>Action</th>
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
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($row['photo']); ?>" alt="Candidate Photo" style="max-width: 80px; max-height: 80px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <span style="color: #9ca3af;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars(evote_position_display_label($row['position'])); ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td>
                            <a href="edit_candidate.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;">Edit</a>
                            <a href="delete_candidate.php?id=<?php echo $row['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Are you sure you want to delete this candidate?')">🗑️ Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php if ($count === 0): ?>
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <p style="font-size: 16px;">No candidates registered yet.</p>
                <a href="add_candidate.php" class="btn btn-primary mt-20">➕ Add First Candidate</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
