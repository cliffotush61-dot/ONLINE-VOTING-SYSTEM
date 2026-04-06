<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Audit Logs';
include 'includes/admin_header.php';

$filters = [
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? ''),
    'user_role' => trim($_GET['user_role'] ?? ''),
    'action_type' => trim($_GET['action_type'] ?? ''),
    'keyword' => trim($_GET['keyword'] ?? ''),
];

$active_filters = array_filter($filters, fn($value) => $value !== '');
$audit_logs = evote_fetch_all_logs($conn, $active_filters);
$log_count = count($audit_logs);
?>

<div class="page-header">
    <h1 class="page-title">Audit Logs</h1>
    <p class="page-subtitle">Read-only log history with filters for role, action, date, and keyword</p>
</div>

<div class="content-card">
    <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
        <div class="form-group">
            <label class="form-label" for="date_from">Date From</label>
            <input type="date" id="date_from" name="date_from" class="form-input" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="date_to">Date To</label>
            <input type="date" id="date_to" name="date_to" class="form-input" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="user_role">User Role</label>
            <select id="user_role" name="user_role" class="form-select">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $filters['user_role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="student" <?php echo $filters['user_role'] === 'student' ? 'selected' : ''; ?>>Student</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="action_type">Action Type</label>
            <input type="text" id="action_type" name="action_type" class="form-input" value="<?php echo htmlspecialchars($filters['action_type']); ?>" placeholder="e.g. LOGIN_SUCCESS">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="keyword">Keyword</label>
            <input type="text" id="keyword" name="keyword" class="form-input" value="<?php echo htmlspecialchars($filters['keyword']); ?>" placeholder="Search username, description, or table">
        </div>
        <div style="display:flex;gap:12px;align-items:flex-end;grid-column:1/-1;">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="view_audit.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card"><h3>Displayed Logs</h3><div class="stat-value"><?php echo $log_count; ?></div></div>
    <div class="stat-card"><h3>Read Only</h3><div class="stat-value">Yes</div></div>
    <div class="stat-card"><h3>Latest First</h3><div class="stat-value">Yes</div></div>
    <div class="stat-card"><h3>Protected Tables</h3><div class="stat-value">5</div></div>
</div>

<div class="content-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Affected</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($audit_logs)): ?>
                    <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><strong>#<?php echo (int) $log['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($log['user_role']); ?></td>
                            <td><?php echo htmlspecialchars($log['username_or_reg']); ?></td>
                            <td><span style="background:#667eea;color:white;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;display:inline-block;"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($log['affected_table'] ?? '') . ' ' . ($log['affected_record_id'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:#6b7280;">No audit logs found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
