<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
require_once __DIR__ . '/db.php';

if (evote_normalize_auth_session($conn) !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Reset Student Passwords";
$message = '';
$message_class = '';
$password_plain = '909090';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
    $password_columns = evote_student_password_columns($conn);

    if (empty($password_columns)) {
        $message = 'No student password column was found.';
        $message_class = 'message-error';
        } else {
            $students_result = mysqli_query($conn, "SELECT * FROM students ORDER BY id ASC");
            if (!$students_result) {
                $message = 'Unable to read student records: ' . mysqli_error($conn);
                $message_class = 'message-error';
            } else {
                $setParts = [];
                foreach ($password_columns as $column) {
                    $setParts[] = "`{$column}` = ?";
                }
                $setParts[] = "`record_hash` = ?";
                $setParts[] = "`updated_at` = NOW()";
                $updateSql = "UPDATE students SET " . implode(', ', $setParts) . " WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $updateSql);

                if (!$update_stmt) {
                    $message = 'Unable to prepare the password reset statement.';
                    $message_class = 'message-error';
                } else {
                    $student_count = 0;

                    mysqli_begin_transaction($conn);
                    try {
                        while ($student = mysqli_fetch_assoc($students_result)) {
                            $student_count++;
                            $updated_row = $student;
                            if (isset($updated_row['password'])) {
                                $updated_row['password'] = $password_hash;
                            }
                            if (isset($updated_row['password_hash'])) {
                                $updated_row['password_hash'] = $password_hash;
                            }

                            $record_hash = evote_compute_entity_hash(evote_hash_fields_from_row('students', $updated_row));
                            $id = (int) $student['id'];

                            if (count($password_columns) === 1) {
                                $colValue = $password_hash;
                                mysqli_stmt_bind_param($update_stmt, 'ssi', $colValue, $record_hash, $id);
                            } else {
                                $passwordValue1 = $password_hash;
                                $passwordValue2 = $password_hash;
                                mysqli_stmt_bind_param($update_stmt, 'sssi', $passwordValue1, $passwordValue2, $record_hash, $id);
                            }

                            if (!mysqli_stmt_execute($update_stmt)) {
                                throw new RuntimeException(mysqli_error($conn));
                            }
                        }

                        mysqli_commit($conn);
                        logAuditAction(
                            $conn,
                            'admin',
                            (int) $_SESSION['admin_id'],
                            (string) ($_SESSION['admin_username'] ?? 'admin'),
                            'BULK_STUDENT_PASSWORD_RESET',
                            'All student passwords were reset to the temporary value 909090.',
                            'students',
                            null
                        );
                        $message = "Student passwords were reset successfully for {$student_count} account(s).";
                        $message_class = 'message-success';
                    } catch (Throwable $e) {
                        mysqli_rollback($conn);
                        $message = 'Password reset failed: ' . $e->getMessage();
                        $message_class = 'message-error';
                    }

                    mysqli_stmt_close($update_stmt);
                }
            }
        }
    }

include 'includes/admin_header.php';
?>
<div class="page-header">
    <h1 class="page-title">Reset Student Passwords</h1>
    <p class="page-subtitle">One-time admin utility to set every student password to 909090 with proper hashing.</p>
</div>

<div class="content-card" style="max-width: 760px;">
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="message-box message-warning">
        This action resets all student passwords. It is intended as a cleanup step for legacy or test accounts.
    </div>

    <form method="POST" onsubmit="return confirm('Reset all student passwords to 909090?');">
        <p style="margin-bottom:16px;color:#475569;">
            The system will hash the password before saving it, then update the student record hash so integrity checks remain valid.
        </p>

        <button type="submit" name="confirm_reset" class="btn btn-danger">Reset All Student Passwords</button>
        <a href="manage_students.php" class="btn btn-secondary">Back to Students</a>
    </form>
</div>

<?php include 'includes/admin_footer.php'; ?>
