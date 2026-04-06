<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

if (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'student' || !isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = (int) ($_SESSION['student_id'] ?? ($_SESSION['user_id'] ?? 0));
if ($student_id > 0 && !isset($_SESSION['student_id'])) {
    $_SESSION['student_id'] = $student_id;
}

$reg_number = (string) ($_SESSION['reg_number'] ?? '');
$department = (string) ($_SESSION['department'] ?? '');
$has_voted = (int) ($_SESSION['has_voted'] ?? 0);
$election_state = evote_election_state($conn);

$status_text = $has_voted ? 'Vote Submitted' : 'Eligible to Vote';
$eligibility_text = $has_voted ? 'Used' : 'Active';
$hero_badge = $has_voted ? 'Ballot Completed' : 'Ready to Vote';
$access_text = $has_voted ? 'Closed for You' : ($election_state['status'] === 'open' ? 'Open for You' : ucfirst($election_state['status']));
$can_view_results = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | E-Voting System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --ev-primary: #1d4ed8;
            --ev-primary-dark: #0f172a;
            --ev-primary-soft: #dbeafe;
            --ev-accent: #2563eb;
            --ev-success: #15803d;
            --ev-success-soft: #dcfce7;
            --ev-warning: #d97706;
            --ev-warning-soft: #fef3c7;
            --ev-danger: #dc2626;
            --ev-bg: #eef4fb;
            --ev-surface: #ffffff;
            --ev-surface-soft: #f8fbff;
            --ev-border: #dbe5f1;
            --ev-text: #0f172a;
            --ev-text-soft: #475569;
            --ev-text-faint: #64748b;
            --ev-shadow-sm: 0 8px 24px rgba(15, 23, 42, 0.06);
            --ev-shadow-md: 0 18px 45px rgba(15, 23, 42, 0.12);
            --ev-radius-lg: 20px;
            --ev-radius-xl: 28px;
        }

        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top left, #dbeafe 0%, transparent 28%),
                radial-gradient(circle at top right, #bfdbfe 0%, transparent 20%),
                linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
            color: var(--ev-text);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .ev-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .ev-topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(14px);
            background: rgba(15, 23, 42, 0.9);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .ev-topbar-inner {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            min-height: 84px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .ev-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ev-brand-mark {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.35);
        }

        .ev-brand-title {
            color: #fff;
            font-size: 1.08rem;
            font-weight: 700;
        }

        .ev-brand-subtitle {
            color: rgba(255,255,255,0.72);
            font-size: 0.88rem;
        }

        .ev-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ev-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            transition: 0.25s ease;
        }

        .ev-btn:hover {
            transform: translateY(-1px);
        }

        .ev-btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25);
        }

        .ev-btn-secondary {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
        }

        .ev-btn-danger {
            background: #fff;
            color: var(--ev-danger);
        }

        .ev-page {
            padding: 34px 0 46px;
        }

        .ev-hero {
            display: grid;
            grid-template-columns: 1.9fr 1fr;
            gap: 24px;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
            border-radius: 30px;
            padding: 34px;
            color: #fff;
            box-shadow: var(--ev-shadow-md);
            margin-bottom: 28px;
        }

        .ev-pill {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.16);
            margin-bottom: 16px;
        }

        .ev-hero h1 {
            font-size: 2.35rem;
            line-height: 1.15;
            margin-bottom: 14px;
            font-weight: 800;
        }

        .ev-hero-text {
            max-width: 760px;
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            margin-bottom: 24px;
        }

        .ev-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .ev-meta-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 18px;
            padding: 16px;
        }

        .ev-meta-label {
            display: block;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.72);
            margin-bottom: 6px;
        }

        .ev-meta-value {
            display: block;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            word-break: break-word;
        }

        .ev-hero-side {
            display: flex;
            align-items: stretch;
        }

        .ev-side-box {
            width: 100%;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 22px;
            padding: 24px;
        }

        .ev-side-kicker {
            display: block;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.72);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .ev-side-title {
            display: block;
            font-size: 1.55rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .ev-side-box p {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.88);
        }

        .ev-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .ev-stat {
            background: var(--ev-surface);
            border: 1px solid var(--ev-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--ev-shadow-sm);
            transition: 0.25s ease;
        }

        .ev-stat:hover {
            transform: translateY(-3px);
        }

        .ev-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: var(--ev-primary-soft);
            color: var(--ev-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .ev-stat h3 {
            color: var(--ev-text-soft);
            font-size: 0.98rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .ev-stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--ev-text);
            margin-bottom: 6px;
        }

        .ev-stat p {
            color: var(--ev-text-faint);
            font-size: 0.93rem;
        }

        .ev-content {
            display: grid;
            grid-template-columns: 1.9fr 1fr;
            gap: 24px;
        }

        .ev-panel,
        .ev-sidebar-card {
            background: var(--ev-surface);
            border: 1px solid var(--ev-border);
            border-radius: var(--ev-radius-xl);
            box-shadow: var(--ev-shadow-sm);
        }

        .ev-panel {
            padding: 28px;
        }

        .ev-panel h2,
        .ev-sidebar-card h2,
        .ev-sidebar-card h3 {
            font-size: 1.45rem;
            margin-bottom: 10px;
            color: var(--ev-text);
        }

        .ev-panel > p,
        .ev-sidebar-card > p {
            color: var(--ev-text-soft);
            margin-bottom: 22px;
        }

        .ev-action-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .ev-action-grid > a[href="view_results.php"] {
            display: none !important;
        }

        .ev-action {
            display: block;
            border-radius: 22px;
            padding: 22px;
            border: 1px solid var(--ev-border);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: var(--ev-shadow-sm);
            transition: 0.25s ease;
            color: var(--ev-text);
        }

        .ev-action:hover {
            transform: translateY(-3px);
        }

        .ev-action-primary {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #bfdbfe;
        }

        .ev-action-muted {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .ev-action-tag {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eaf2ff;
            color: var(--ev-primary);
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .ev-action h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .ev-action p {
            color: var(--ev-text-soft);
            margin-bottom: 18px;
        }

        .ev-action-link {
            color: var(--ev-primary);
            font-weight: 700;
        }

        .ev-notice {
            border-radius: 18px;
            padding: 18px 20px;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }

        .ev-notice-success {
            background: var(--ev-success-soft);
            color: #166534;
            border-color: #bbf7d0;
        }

        .ev-notice-warning {
            background: var(--ev-warning-soft);
            color: #92400e;
            border-color: #fde68a;
        }

        .ev-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .ev-sidebar-card {
            padding: 24px;
        }

        .ev-list {
            list-style: none;
            margin-bottom: 20px;
        }

        .ev-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 14px;
            color: var(--ev-text-soft);
        }

        .ev-list li::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ev-primary);
            position: absolute;
            left: 0;
            top: 10px;
        }

        .ev-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--ev-primary-soft);
            color: var(--ev-primary);
            font-size: 0.83rem;
            font-weight: 700;
        }

        .ev-mini-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--ev-border);
            color: var(--ev-text-soft);
        }

        .ev-mini-row:last-child {
            border-bottom: none;
        }

        .ev-mini-row strong {
            color: var(--ev-text);
        }

        @media (max-width: 1024px) {
            .ev-hero,
            .ev-content {
                grid-template-columns: 1fr;
            }

            .ev-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .ev-meta-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .ev-topbar-inner {
                flex-direction: column;
                align-items: flex-start;
                padding: 14px 0;
            }

            .ev-nav {
                width: 100%;
            }

            .ev-nav .ev-btn {
                flex: 1 1 auto;
            }

            .ev-hero {
                padding: 24px;
            }

            .ev-hero h1 {
                font-size: 1.75rem;
            }

            .ev-stats,
            .ev-action-grid {
                grid-template-columns: 1fr;
            }

            .ev-panel,
            .ev-sidebar-card {
                padding: 22px;
            }
        }

        @media (max-width: 480px) {
            .ev-shell,
            .ev-topbar-inner {
                width: min(100% - 20px, 1180px);
            }

            .ev-btn {
                width: 100%;
            }

            .ev-brand-title {
                font-size: 0.98rem;
            }

            .ev-brand-subtitle {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

<header class="ev-topbar">
    <div class="ev-topbar-inner">
        <div class="ev-brand">
            <div class="ev-brand-mark">E</div>
            <div>
                <div class="ev-brand-title">E-Voting Student Portal</div>
                <div class="ev-brand-subtitle">Secure Departmental Election Workspace</div>
            </div>
        </div>

        <nav class="ev-nav">
            <a href="index.php" class="ev-btn ev-btn-secondary">Home</a>
            <?php if ($has_voted == 0): ?>
                <a href="vote.php" class="ev-btn ev-btn-primary">Vote Now</a>
            <?php endif; ?>
            <?php if ($can_view_results): ?>
                <a href="view_results.php" class="ev-btn ev-btn-secondary">View Results</a>
            <?php endif; ?>
            <a href="logout.php" class="ev-btn ev-btn-danger">Sign Out</a>
        </nav>
    </div>
</header>

<main class="ev-shell ev-page">

    <section class="ev-hero">
        <div>
            <span class="ev-pill"><?php echo $hero_badge; ?></span>
            <h1>Welcome to the Student Voting Dashboard</h1>
            <p class="ev-hero-text">
                Access your departmental ballot, verify your voting eligibility, and participate in a secure,
                transparent, and professionally managed election process designed for accountability and ease of use.
            </p>

            <div class="ev-meta-grid">
                <div class="ev-meta-card">
                    <span class="ev-meta-label">Registration Number</span>
                    <span class="ev-meta-value"><?php echo htmlspecialchars($reg_number); ?></span>
                </div>
                <div class="ev-meta-card">
                    <span class="ev-meta-label">Department</span>
                    <span class="ev-meta-value"><?php echo htmlspecialchars($department); ?></span>
                </div>
                <div class="ev-meta-card">
                    <span class="ev-meta-label">Current Status</span>
                    <span class="ev-meta-value"><?php echo $status_text; ?></span>
                </div>
            </div>

            <div class="ev-notice <?php echo $election_state['status'] === 'open' ? 'ev-notice-success' : 'ev-notice-warning'; ?>" style="margin-top: 18px;">
                <strong>Election State:</strong> <?php echo htmlspecialchars($election_state['message']); ?>
            </div>
        </div>

        <div class="ev-hero-side">
            <div class="ev-side-box">
                <span class="ev-side-kicker">Election Access</span>
                <span class="ev-side-title"><?php echo $access_text; ?></span>
                <p>
                    <?php if ($has_voted == 1): ?>
                        Your ballot has already been submitted successfully, and the system has now locked your voting access.
                    <?php else: ?>
                        You are currently eligible to proceed to the ballot and submit your departmental choices.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <section class="ev-stats">
        <article class="ev-stat">
            <div class="ev-stat-icon">✓</div>
            <h3>Voting Eligibility</h3>
            <div class="ev-stat-value"><?php echo $eligibility_text; ?></div>
            <p>Your voting access status for this election cycle.</p>
        </article>

        <article class="ev-stat">
            <div class="ev-stat-icon">⌂</div>
            <h3>Department Access</h3>
            <div class="ev-stat-value">1</div>
            <p>You may cast a ballot only within your assigned department.</p>
        </article>

        <article class="ev-stat">
            <div class="ev-stat-icon">☰</div>
            <h3>Ballot Positions</h3>
            <div class="ev-stat-value">3</div>
            <p>Male Delegate, Female Delegate, and Departmental Delegate.</p>
        </article>

        <article class="ev-stat">
            <div class="ev-stat-icon">!</div>
            <h3>Submission Rule</h3>
            <div class="ev-stat-value">Final</div>
            <p>Votes cannot be edited after final confirmation.</p>
        </article>
    </section>

    <section class="ev-content">
        <div class="ev-panel">
            <h2>Your Election Workspace</h2>
            <p>
                This dashboard gives you controlled access to the election environment, allowing you to review
                your status, enter the ballot, and make final choices through a guided process.
            </p>

            <div class="ev-action-grid">
                <?php if ($has_voted == 0): ?>
                    <a href="vote.php" class="ev-action ev-action-primary">
                        <div class="ev-action-tag">Primary Action</div>
                        <h3>Open Ballot</h3>
                        <p>Access your departmental ballot and select your preferred candidates with confidence.</p>
                        <span class="ev-action-link">Proceed to Voting →</span>
                    </a>
                <?php else: ?>
                    <div class="ev-action ev-action-muted">
                        <div class="ev-action-tag">Completed</div>
                        <h3>Vote Locked</h3>
                        <p>Your ballot has already been submitted successfully and cannot be changed again.</p>
                        <span class="ev-action-link">Submission Confirmed</span>
                    </div>
                <?php endif; ?>

                <a href="view_results.php" class="ev-action">
                    <div class="ev-action-tag">Public Information</div>
                    <h3>View Results</h3>
                    <p>Review published election results and observe vote totals where available.</p>
                    <span class="ev-action-link">Open Results →</span>
                </a>
            </div>

            <?php if ($has_voted == 1): ?>
                <div class="ev-notice ev-notice-success">
                    <strong>Vote Recorded:</strong> Your vote has already been captured successfully. The platform blocks duplicate submissions to preserve electoral integrity.
                </div>
            <?php else: ?>
                <div class="ev-notice ev-notice-warning">
                    <strong>Voting Reminder:</strong> You are eligible to vote. Please review all candidates carefully before submission because the process becomes irreversible after confirmation.
                </div>
            <?php endif; ?>
        </div>

        <aside class="ev-sidebar">
            <div class="ev-sidebar-card">
                <h2>Important Information</h2>
                <p>These election rules apply to every eligible student using the platform.</p>

                <ul class="ev-list">
                    <li>Only registered and active students are allowed to vote.</li>
                    <li>You may vote only within your assigned department.</li>
                    <li>One candidate must be selected for each listed position.</li>
                    <li>A preview page appears before the final submission step.</li>
                    <li>Once submitted, the vote cannot be altered or withdrawn.</li>
                </ul>

                <span class="ev-badge">Secure Student Access</span>
            </div>

            <div class="ev-sidebar-card">
                <h3>Election Scope</h3>

                <div class="ev-mini-row">
                    <span>Access Type</span>
                    <strong>Departmental</strong>
                </div>

                <div class="ev-mini-row">
                    <span>Positions</span>
                    <strong>3</strong>
                </div>

                <div class="ev-mini-row">
                    <span>Vote Editing</span>
                    <strong>Disabled</strong>
                </div>

                <div class="ev-mini-row">
                    <span>Verification</span>
                    <strong>Required</strong>
                </div>
            </div>
        </aside>
    </section>

</main>

</body>
</html>
