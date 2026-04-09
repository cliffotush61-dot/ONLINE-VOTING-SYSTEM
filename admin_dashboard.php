<?php
require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

if (($_SESSION['role'] ?? '') !== 'admin' || !isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'Admin');
$post_login_notice = (string) ($_SESSION['post_admin_login_notice'] ?? '');
if ($post_login_notice !== '') {
    unset($_SESSION['post_admin_login_notice']);
}

// Get statistics
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students"))['count'];
$total_candidates = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM candidates"))['count'];
$total_votes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM votes"))['count'];
$students_voted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE has_voted = 1"))['count'];
$audit_log_count = evote_table_exists($conn, 'audit_logs') ? (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM audit_logs"))['count'] ?? 0) : 0;
$election_state = evote_election_state($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Secure Voting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            font-size: 24px;
        }

        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .admin-header a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 0;
        }

        .sidebar h3 {
            background: #f5f7fa;
            padding: 15px 20px;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            border-bottom: 1px solid #f0f0f0;
        }

        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background: #f5f7fa;
            border-left-color: #667eea;
            color: #667eea;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            background: linear-gradient(135deg, #667eea15, #764ba215);
        }

        .feature-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .feature-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }

        .feature-card p {
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }

            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
            }

            .sidebar-menu li {
                flex: 1;
                border: none;
                border-right: 1px solid #f0f0f0;
            }

            .main-content {
                padding: 20px;
            }

            .stats-grid, .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <h1>📊 Admin Dashboard</h1>
    <div class="user-info">
        <span>Welcome, <strong><?php echo htmlspecialchars($admin_username); ?></strong></span>
        <a href="index.php">Home</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>📋 Management</h3>
        <ul class="sidebar-menu">
            <li><a href="#students">👥 Students</a></li>
            <li><a href="#candidates">🎯 Candidates</a></li>
            <li><a href="#voting">🗳️ Voting</a></li>
            <li><a href="#security">🔒 Security</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h2 class="page-title">Dashboard Overview</h2>

        <?php if ($post_login_notice !== ''): ?>
            <div class="content-card" style="margin-bottom:20px;">
                <div class="message-box message-success"><?php echo htmlspecialchars($post_login_notice); ?></div>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Students</h3>
                <div class="stat-value"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Candidates</h3>
                <div class="stat-value"><?php echo $total_candidates; ?></div>
            </div>
            <div class="stat-card">
                <h3>Votes Cast</h3>
                <div class="stat-value"><?php echo $total_votes; ?></div>
            </div>
            <div class="stat-card">
                <h3>Students Voted</h3>
                <div class="stat-value"><?php echo $students_voted; ?></div>
            </div>
        </div>

        <!-- Student Management -->
        <h3 id="students" style="margin-top: 40px; margin-bottom: 20px;">👥 Student Management</h3>
        <div class="features-grid">
            <a href="add_student.php" class="feature-card">
                <div class="icon">➕</div>
                <h3>Add Student</h3>
                <p>Register a new student into the system</p>
            </a>
            <a href="manage_students.php" class="feature-card">
                <div class="icon">📝</div>
                <h3>Manage Students</h3>
                <p>View and edit existing students</p>
            </a>
            <a href="import_students.php" class="feature-card">
                <div class="icon">📥</div>
                <h3>Import Students</h3>
                <p>Bulk import students from CSV file</p>
            </a>
            <a href="delete_student.php" class="feature-card">
                <div class="icon">🗑️</div>
                <h3>Delete Student</h3>
                <p>Remove student accounts</p>
            </a>
        </div>

        <!-- Candidate Management -->
        <h3 id="candidates" style="margin-top: 40px; margin-bottom: 20px;">🎯 Candidate Management</h3>
        <div class="features-grid">
            <a href="add_candidate.php" class="feature-card">
                <div class="icon">➕</div>
                <h3>Add Candidate</h3>
                <p>Register a new election candidate</p>
            </a>
            <a href="manage_candidate.php" class="feature-card">
                <div class="icon">📝</div>
                <h3>Manage Candidates</h3>
                <p>View and edit candidate information</p>
            </a>
            <a href="delete_candidate.php" class="feature-card">
                <div class="icon">🗑️</div>
                <h3>Delete Candidate</h3>
                <p>Remove candidates from the system</p>
            </a>
            <a href="seed_test_candidates.php" class="feature-card">
                <div class="icon">🧪</div>
                <h3>Seed Test Candidates</h3>
                <p>Create three test candidates from the current student roster.</p>
            </a>
        </div>

        <!-- Voting Management -->
        <h3 id="voting" style="margin-top: 40px; margin-bottom: 20px;">🗳️ Voting & Results</h3>
        <div class="features-grid">
            <a href="view_results.php" class="feature-card">
                <div class="icon">📊</div>
                <h3>View Results</h3>
                <p>See live voting results by position and department</p>
            </a>
            <form method="POST" action="admin_settings.php" class="feature-card" style="border:none;cursor:pointer;">
                <input type="hidden" name="action" value="force_open">
                <div class="icon">▶</div>
                <h3>Open Voting Session</h3>
                <p>Force the election open now. It will still close automatically at the deadline.</p>
                <button type="submit" class="btn btn-success" style="margin-top:12px;">Open Election</button>
            </form>
            <a href="election_settings.php" class="feature-card">
                <div class="icon">⚙️</div>
                <h3>Election Settings</h3>
                <p>Create, open, close, or reset the current election</p>
            </a>
        </div>

        <!-- Security & Audit -->
        <h3 id="security" style="margin-top: 40px; margin-bottom: 20px;">🔒 Security & Audit</h3>
        <div class="features-grid">
            <a href="tamper_check.php" class="feature-card">
                <div class="icon">🔍</div>
                <h3>Tamper Check</h3>
                <p>Verify system integrity and detect tampering</p>
            </a>
            <a href="view_audit.php" class="feature-card">
                <div class="icon">📋</div>
                <h3>Audit Log</h3>
                <p>Review all system actions and changes</p>
            </a>
            <a href="cleanup_students.php" class="feature-card">
                <div class="icon">🧹</div>
                <h3>Clean Duplicates</h3>
                <p>Review and remove duplicate student accounts safely</p>
            </a>
            <a href="reset_student_passwords.php" class="feature-card">
                <div class="icon">🔐</div>
                <h3>Reset Passwords</h3>
                <p>Reapply the temporary student password 909090</p>
            </a>
        </div>

        <div class="content-card" id="live-monitoring" style="margin-top: 26px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <h3 style="font-size:22px;margin-bottom:8px;">Live Voting Monitor</h3>
                    <p style="color:#6b7280;">Real-time counts refresh automatically every five seconds.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span style="background:#e0e7ff;color:#3730a3;padding:8px 12px;border-radius:999px;font-weight:700;"><?php echo htmlspecialchars($election_state['status']); ?></span>
                    <span style="background:#dcfce7;color:#166534;padding:8px 12px;border-radius:999px;font-weight:700;"><?php echo $election_state['live_results_enabled'] ? 'Public results enabled' : 'Admin-only results'; ?></span>
                </div>
            </div>

            <div id="liveResultsSummary" class="stats-grid" style="margin-bottom:20px;">
                <div class="stat-card"><h3>Total Votes</h3><div class="stat-value" id="lrTotalVotes">0</div></div>
                <div class="stat-card"><h3>Valid Votes</h3><div class="stat-value" id="lrValidVotes">0</div></div>
                <div class="stat-card"><h3>Tampered Votes</h3><div class="stat-value" id="lrTamperedVotes">0</div></div>
                <div class="stat-card"><h3>Turnout</h3><div class="stat-value" id="lrTurnout">0%</div></div>
            </div>

            <div id="liveResultsWarning" style="display:none;background:#fef3c7;color:#92400e;padding:14px 16px;border-radius:12px;margin-bottom:16px;border:1px solid #fde68a;"></div>
            <div id="liveResultsList" style="display:grid;gap:12px;"></div>
        </div>

        <div class="content-card" style="margin-top: 26px;">
            <h3 style="font-size:22px;margin-bottom:10px;">Audit Activity Snapshot</h3>
            <p style="color:#6b7280;margin-bottom:18px;">Total audit records stored: <strong><?php echo $audit_log_count; ?></strong></p>
            <a href="view_audit.php" class="btn btn-primary">Open Audit Logs</a>
            <a href="tamper_check.php" class="btn btn-secondary" style="margin-left:10px;">Run Tamper Check</a>
        </div>
    </div>
</div>

<script>
async function refreshLiveResults() {
    const list = document.getElementById('liveResultsList');
    const warning = document.getElementById('liveResultsWarning');
    try {
        const response = await fetch('api/live_results.php', { headers: { 'Accept': 'application/json' } });
        const data = await response.json();

        if (!response.ok) {
            warning.style.display = 'block';
            warning.textContent = data.message || 'Unable to load live results.';
            list.innerHTML = '';
            return;
        }

        warning.style.display = data.tampered_votes > 0 ? 'block' : 'none';
        warning.textContent = data.tampered_votes > 0 ? `Warning: ${data.tampered_votes} tampered vote(s) detected. Those records are excluded from the live count.` : '';

        document.getElementById('lrTotalVotes').textContent = data.total_votes ?? 0;
        document.getElementById('lrValidVotes').textContent = data.valid_votes ?? 0;
        document.getElementById('lrTamperedVotes').textContent = data.tampered_votes ?? 0;
        document.getElementById('lrTurnout').textContent = `${data.turnout_percent ?? 0}%`;

        list.innerHTML = '';
        (data.candidates || []).forEach(candidate => {
            const card = document.createElement('div');
            card.style.cssText = 'background:#fff;border:1px solid #dbe5f1;border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(15,23,42,0.05);';
            card.innerHTML = `
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                    <div>
                        <div style="font-weight:800;font-size:16px;">${candidate.name}</div>
                        <div style="color:#64748b;font-size:13px;">${candidate.department} • ${candidate.position}</div>
                    </div>
                    <div style="font-weight:800;color:#1d4ed8;">${candidate.votes} votes</div>
                </div>
                <div style="height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin:14px 0 10px;">
                    <div style="width:${candidate.percent}%;height:100%;background:linear-gradient(90deg,#1d4ed8,#60a5fa);border-radius:inherit;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;color:#64748b;font-size:13px;">
                    <span>${candidate.percent}% of valid votes</span>
                    <span>${candidate.lead_label}</span>
                </div>
            `;
            list.appendChild(card);
        });
    } catch (error) {
        warning.style.display = 'block';
        warning.textContent = 'Unable to refresh live results.';
    }
}

refreshLiveResults();
setInterval(refreshLiveResults, 5000);
</script>
