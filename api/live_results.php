<?php
header('Content-Type: application/json');

session_start();
include_once __DIR__ . '/../db.php';

$is_admin = isset($_SESSION['admin_id']);
$election_state = evote_election_state($conn);

if (!$is_admin && (!$election_state['exists'] || !$election_state['live_results_enabled'])) {
    http_response_code(403);
    echo json_encode([
        'message' => 'Live results are not available to student accounts yet.'
    ]);
    exit();
}

$candidate_rows = [];
$candidate_lookup = [];
$candidate_result = mysqli_query($conn, "SELECT id, name, department, position FROM candidates ORDER BY department, name");
if ($candidate_result) {
    while ($candidate = mysqli_fetch_assoc($candidate_result)) {
        $candidate['votes'] = 0;
        $candidate['percent'] = 0;
        $candidate['lead_label'] = 'No votes';
        $candidate_rows[] = $candidate;
        $candidate_lookup[(int) $candidate['id']] = count($candidate_rows) - 1;
    }
}

$total_votes = 0;
$valid_votes = 0;
$tampered_votes = 0;
$votes_result = mysqli_query($conn, "SELECT * FROM votes ORDER BY id ASC");
$previous_hash = '';

if ($votes_result) {
    while ($vote = mysqli_fetch_assoc($votes_result)) {
        $total_votes++;
        $verified = evote_verify_row_hash('votes', $vote);
        if (!empty($vote['previous_hash']) && $previous_hash !== '' && $vote['previous_hash'] !== $previous_hash) {
            $verified['status'] = 'tampered';
            $verified['reason'] = 'Previous hash chain mismatch.';
        }

        if ($verified['status'] !== 'valid') {
            $tampered_votes++;
            $previous_hash = (string) ($vote['record_hash'] ?? '');
            continue;
        }

        $valid_votes++;
        foreach (['male_delegate_candidate_id', 'female_delegate_candidate_id', 'departmental_delegate_candidate_id'] as $column) {
            $candidate_id = (int) ($vote[$column] ?? 0);
            if ($candidate_id > 0 && isset($candidate_lookup[$candidate_id])) {
                $candidate_rows[$candidate_lookup[$candidate_id]]['votes']++;
            }
        }

        $previous_hash = (string) ($vote['record_hash'] ?? '');
    }
}

$eligible_voters = 0;
$eligible_result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM students WHERE is_active = 1");
if ($eligible_result) {
    $eligible_voters = (int) (mysqli_fetch_assoc($eligible_result)['count'] ?? 0);
}

$valid_vote_pool = max($valid_votes, 1);
$top_votes = 0;
foreach ($candidate_rows as $row) {
    if ($row['votes'] > $top_votes) {
        $top_votes = $row['votes'];
    }
}

foreach ($candidate_rows as &$row) {
    $row['percent'] = (int) round(($row['votes'] / $valid_vote_pool) * 100);
    $row['lead_label'] = $top_votes > 0 && $row['votes'] === $top_votes ? 'Leading' : 'Trailing';
}
unset($row);

$turnout_percent = $eligible_voters > 0 ? (int) round(($valid_votes / $eligible_voters) * 100) : 0;

echo json_encode([
    'election_status' => $election_state['status'],
    'live_results_enabled' => $election_state['live_results_enabled'],
    'total_votes' => $total_votes,
    'valid_votes' => $valid_votes,
    'tampered_votes' => $tampered_votes,
    'turnout_percent' => $turnout_percent,
    'candidates' => $candidate_rows
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
