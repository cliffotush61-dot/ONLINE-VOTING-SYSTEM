<?php
$page_title = "View Results";
$compact_admin_layout = true;
include 'includes/admin_header.php';

if (!function_exists('normalize_position_label')) {
    function normalize_position_label($position) {
        $position = strtolower(trim($position));
        $position = str_replace([' ', '-'], '_', $position);
        return preg_replace('/[^a-z0-9_]/', '', $position);
    }
}

if (!function_exists('format_position_label')) {
    function format_position_label($position) {
        return ucwords(str_replace('_', ' ', trim($position)));
    }
}

$sql = "
SELECT c.id, c.reg_number, c.name, c.department, c.position,
(
    (SELECT COUNT(*) FROM votes WHERE male_delegate_candidate_id = c.id) +
    (SELECT COUNT(*) FROM votes WHERE female_delegate_candidate_id = c.id) +
    (SELECT COUNT(*) FROM votes WHERE departmental_delegate_candidate_id = c.id)
) AS total_votes
FROM candidates c
ORDER BY c.department, total_votes DESC, c.name
";

$result = mysqli_query($conn, $sql);
$query_error = $result ? null : mysqli_error($conn);

$rows = [];
$grouped = [];
$departments = [];
$ballots_submitted = 0;
$leading_candidate = null;

$ballots_result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM votes");
if ($ballots_result) {
    $ballots_submitted = (int) (mysqli_fetch_assoc($ballots_result)['count'] ?? 0);
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['total_votes'] = (int) $row['total_votes'];
        $department = $row['department'] ?: 'Unknown Department';
        $position_key = normalize_position_label($row['position']);

        $rows[] = $row;
        $departments[$department] = true;
        $grouped[$department][$position_key][] = $row;

        if ($leading_candidate === null || $row['total_votes'] > $leading_candidate['total_votes']) {
            $leading_candidate = $row;
        }
    }
}

$department_count = count($departments);
$candidate_count = count($rows);
$position_order = [
    'male_delegate' => 'Male Delegate',
    'female_delegate' => 'Female Delegate',
    'departmental_delegate' => 'Departmental Delegate',
];
?>

<style>
    .results-hero {
        display: grid;
        grid-template-columns: 1.4fr 0.9fr;
        gap: 20px;
        margin-bottom: 22px;
    }

    .results-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
        color: #fff;
        border-radius: 22px;
        padding: 24px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
    }

    .results-hero-card h1 {
        font-size: 30px;
        line-height: 1.1;
        margin-bottom: 10px;
    }

    .results-hero-card p {
        color: rgba(255, 255, 255, 0.88);
        max-width: 760px;
    }

    .results-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .results-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 16px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
    }

    .results-btn-primary {
        background: #fff;
        color: #1d4ed8;
    }

    .results-btn-secondary {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .results-summary {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }

    .summary-card {
        background: #fff;
        border: 1px solid #dbe5f1;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .summary-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
    }

    .summary-value {
        font-size: 1.55rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .summary-note {
        color: #475569;
        font-size: 0.92rem;
    }

    .department-block {
        margin-bottom: 20px;
    }

    .department-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    .department-header h2 {
        font-size: 22px;
        color: #0f172a;
    }

    .department-header p {
        color: #64748b;
        font-size: 14px;
    }

    .position-block {
        margin-bottom: 18px;
    }

    .position-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .position-header h3 {
        font-size: 18px;
        color: #111827;
    }

    .position-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #e0e7ff;
        color: #3730a3;
        font-size: 12px;
        font-weight: 800;
    }

    .candidate-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
    }

    .candidate-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #dbe5f1;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
    }

    .candidate-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .candidate-name {
        font-size: 17px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 5px;
    }

    .candidate-meta {
        color: #64748b;
        font-size: 13px;
    }

    .votes-badge {
        min-width: 68px;
        text-align: center;
        border-radius: 14px;
        padding: 10px 12px;
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 800;
        line-height: 1.1;
    }

    .votes-badge small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-top: 3px;
    }

    .vote-track {
        height: 10px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        margin: 14px 0 10px;
    }

    .vote-track span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #1d4ed8, #60a5fa);
    }

    .candidate-footer {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        color: #475569;
        font-size: 13px;
    }

    .empty-state {
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 18px;
        padding: 40px 24px;
        text-align: center;
        color: #64748b;
    }

    .error-box {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #7f1d1d;
        border-radius: 18px;
        padding: 18px;
    }

    @media (max-width: 980px) {
        .results-hero {
            grid-template-columns: 1fr;
        }

        .results-summary {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 640px) {
        .results-summary {
            grid-template-columns: 1fr;
        }

        .department-header,
        .position-header,
        .candidate-top,
        .candidate-footer {
            flex-direction: column;
            align-items: flex-start;
        }

        .votes-badge {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title">Election Results</h1>
    <p class="page-subtitle">A grouped, department-by-department view of vote totals and leaders</p>
</div>

<?php if ($query_error): ?>
    <div class="content-card">
        <div class="error-box">
            <strong>Unable to load results.</strong>
            <div style="margin-top: 8px;"><?php echo htmlspecialchars($query_error); ?></div>
        </div>
    </div>
<?php else: ?>
    <div class="results-hero">
        <div class="results-hero-card">
            <h1>Live election scoreboard</h1>
            <p>
                Results are grouped by department and position so you can quickly see who is leading in each contest
                without scanning a dense table.
            </p>
            <div class="results-actions">
                <a href="admin_dashboard.php" class="results-btn results-btn-primary">Back Home</a>
                <a href="view_audit.php" class="results-btn results-btn-secondary">Open Audit Log</a>
            </div>
        </div>

        <div class="results-summary">
            <div class="summary-card">
                <span class="summary-label">Candidates listed</span>
                <div class="summary-value"><?php echo $candidate_count; ?></div>
                <div class="summary-note">All registered candidates in the current database</div>
            </div>
            <div class="summary-card">
                <span class="summary-label">Departments</span>
                <div class="summary-value"><?php echo $department_count; ?></div>
                <div class="summary-note">Departments currently appearing in results</div>
            </div>
            <div class="summary-card">
                <span class="summary-label">Ballots submitted</span>
                <div class="summary-value"><?php echo $ballots_submitted; ?></div>
                <div class="summary-note">One record per completed student ballot</div>
            </div>
            <div class="summary-card">
                <span class="summary-label">Current leader</span>
                <div class="summary-value">
                    <?php echo $leading_candidate ? (int) $leading_candidate['total_votes'] : 0; ?>
                </div>
                <div class="summary-note">
                    <?php echo $leading_candidate
                        ? htmlspecialchars($leading_candidate['name'] . ' - ' . $leading_candidate['department'])
                        : 'No votes recorded yet'; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="content-card">
            <div class="empty-state">
                <h3 style="margin-bottom: 10px; color: #0f172a;">No results to show yet</h3>
                <p>No candidates were found in the database, so there is nothing to display on the results board.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $department => $positions): ?>
            <div class="content-card department-block">
                <div class="department-header">
                    <div>
                        <h2><?php echo htmlspecialchars($department); ?></h2>
                        <p>Position-by-position vote totals for this department</p>
                    </div>
                    <span class="position-pill">
                        <?php echo count($positions); ?> position group<?php echo count($positions) === 1 ? '' : 's'; ?>
                    </span>
                </div>

                <?php foreach ($position_order as $position_key => $position_label): ?>
                    <?php if (empty($positions[$position_key])) continue; ?>
                    <?php
                        $candidates = $positions[$position_key];
                        $position_max = 0;
                        foreach ($candidates as $candidate) {
                            if ($candidate['total_votes'] > $position_max) {
                                $position_max = $candidate['total_votes'];
                            }
                        }
                    ?>
                    <div class="position-block">
                        <div class="position-header">
                            <h3><?php echo htmlspecialchars($position_label); ?></h3>
                            <span class="position-pill"><?php echo count($candidates); ?> candidate<?php echo count($candidates) === 1 ? '' : 's'; ?></span>
                        </div>

                        <div class="candidate-grid">
                            <?php foreach ($candidates as $candidate): ?>
                                <?php
                                    $percent = $position_max > 0 ? (int) round(($candidate['total_votes'] / $position_max) * 100) : 0;
                                    $is_leader = $position_max > 0 && $candidate['total_votes'] === $position_max;
                                ?>
                                <article class="candidate-card">
                                    <div class="candidate-top">
                                        <div>
                                            <div class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                            <div class="candidate-meta">
                                                <?php echo htmlspecialchars($candidate['reg_number']); ?> | <?php echo htmlspecialchars($candidate['department']); ?>
                                            </div>
                                        </div>
                                        <div class="votes-badge">
                                            <?php echo $candidate['total_votes']; ?>
                                            <small>votes</small>
                                        </div>
                                    </div>

                                    <div class="vote-track" aria-hidden="true">
                                        <span style="width: <?php echo $percent; ?>%;"></span>
                                    </div>

                                    <div class="candidate-footer">
                                        <span><?php echo $is_leader ? 'Leading in this position' : 'Chasing the leader'; ?></span>
                                        <span><?php echo $percent; ?>% of top score</span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php include 'includes/admin_footer.php'; ?>
