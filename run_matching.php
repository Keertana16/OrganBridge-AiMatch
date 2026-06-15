<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
run_organ_expiry_check($conn);
$message = '';
$results = [];

function fallback_rank_matches($organ, $requests) {
    $ranked = [];
    foreach ($requests as $req) {
        if (strcasecmp($organ['organ_type'], $req['organ_needed']) !== 0) {
            continue;
        }
        $bloodOk = blood_compatible_flow($organ['blood_group'], $req['blood_group']);
        if (!$bloodOk) {
            continue;
        }
        $distance = city_distance_km($organ['city'], $req['city']);
        $urgencyScore = max(1, min(5, intval($req['urgency_level'] ?? 1))) / 5;
        $functionScore = max(0, min(100, floatval($organ['organ_function_pct'] ?? 85))) / 100;
        $hlaScore = max(0, min(100, floatval($req['hla_match_score'] ?? $req['gfr_score'] ?? 70))) / 100;
        $distanceScore = max(0, 1 - ($distance / 500));
        $waitingScore = min(1, max(0, intval($req['waiting_days'] ?? 0)) / 365);
        $bpStable = intval($req['systolic_bp'] ?? 120) <= 140 && intval($req['diastolic_bp'] ?? 80) <= 90;
        $bpSeverityScore = $bpStable ? 1 : 0.4;
        $penalty = 0;
        $penalty += intval($req['infection'] ?? 0) ? 0.12 : 0;
        $penalty += intval($req['prev_transplants'] ?? 0) >= 2 ? 0.08 : 0;
        $penalty += intval($req['cardiac_stable'] ?? 1) ? 0 : 0.06;
        $score = (
            (1 * 0.30) +
            ($urgencyScore * 0.22) +
            ($functionScore * 0.15) +
            ($hlaScore * 0.12) +
            ($distanceScore * 0.10) +
            ($waitingScore * 0.07) +
            ($bpSeverityScore * 0.04) -
            $penalty
        ) * 100;
        $factors = [
            'blood_compatible' => true,
            'urgency_component' => round($urgencyScore * 22, 2),
            'organ_function_component' => round($functionScore * 15, 2),
            'hla_component' => round($hlaScore * 12, 2),
            'distance_component' => round($distanceScore * 10, 2),
            'waiting_component' => round($waitingScore * 7, 2),
            'bp_severity_component' => round($bpSeverityScore * 4, 2),
            'penalty_pct' => round($penalty * 100, 2)
        ];
        $ranked[] = [
            'recipient_id' => intval($req['id']),
            'request_id' => intval($req['id']),
            'score' => max(0, min(100, round($score, 2))),
            'distance_km' => $distance,
            'blood_compatible' => 1,
            'city_match' => $distance <= 25 ? 1 : 0,
            'factors' => $factors
        ];
    }
    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($ranked, 0, 3);
}

function call_match_api($organ, $requests) {
    $payload = json_encode(['organ' => $organ, 'requests' => $requests]);
    $ch = curl_init("http://127.0.0.1:5000/match");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    return ($http === 200 && isset($data['matches'])) ? $data['matches'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organs = [];
    $requests = [];
    $organResult = mysqli_query($conn, "SELECT * FROM organ_listings WHERE status = 'Available' AND (viable_until IS NULL OR viable_until > NOW())");
    $requestResult = mysqli_query($conn, "SELECT * FROM recipients WHERE status = 'Waiting'");
    while ($o = mysqli_fetch_assoc($organResult)) $organs[] = $o;
    while ($r = mysqli_fetch_assoc($requestResult)) $requests[] = $r;
    $created = 0;
    $candidatePairs = [];
    $requestById = [];
    foreach ($requests as $request) {
        $requestById[intval($request['id'])] = $request;
    }

    mysqli_query($conn, "DELETE am FROM ai_matches am
        JOIN organ_listings ol ON am.organ_id = ol.id
        LEFT JOIN transfers t ON t.match_id = am.id
        WHERE am.decision = 'Pending'
          AND ol.status = 'Available'
          AND t.id IS NULL");

    foreach ($organs as $organ) {
        $matches = call_match_api($organ, $requests);
        if ($matches === null) {
            $matches = fallback_rank_matches($organ, $requests);
        }
        foreach ($matches as $match) {
            $requestId = intval($match['recipient_id'] ?? $match['request_id']);
            $request = $requestById[$requestId] ?? null;
            if (!$request) {
                continue;
            }
            $bloodOk = intval($match['blood_compatible'] ?? blood_compatible_flow($organ['blood_group'], $request['blood_group']));
            $cityMatch = intval($match['city_match'] ?? 0);
            if (!$bloodOk) {
                continue;
            }
            $candidatePairs[] = [
                'organ' => $organ,
                'request' => $request,
                'match' => $match,
                'score' => floatval($match['score']),
                'distance' => floatval($match['distance_km'] ?? city_distance_km($organ['city'], $request['city'])),
                'blood_ok' => $bloodOk,
                'city_match' => $cityMatch
            ];
        }
    }

    usort($candidatePairs, fn($a, $b) => $b['score'] <=> $a['score']);
    $usedOrgans = [];
    $usedRecipients = [];
    foreach ($candidatePairs as $pair) {
        $organ = $pair['organ'];
        $request = $pair['request'];
        $match = $pair['match'];
        $organId = intval($organ['id']);
        $requestId = intval($request['id']);
        if (isset($usedOrgans[$organId]) || isset($usedRecipients[$requestId])) {
            continue;
        }

        $score = $pair['score'];
        $distance = $pair['distance'];
        $bloodOk = $pair['blood_ok'];
        $cityMatch = $pair['city_match'];
        $factorsJson = mysqli_real_escape_string($conn, json_encode($match['factors'] ?? []));
        $priority = mysqli_real_escape_string($conn, $request['urgency_level'] >= 5 ? 'critical' : ($request['urgency_level'] >= 4 ? 'high' : 'medium'));
        mysqli_query($conn, "INSERT INTO ai_matches
            (organ_id, request_id, recipient_id, match_score, donor_age, donor_blood_group, donor_city, organ_type, recipient_age, recipient_blood_group, recipient_city, waiting_days, priority_level, distance_km, organ_type_match, blood_group_compatible, city_match, decision, match_status, score_factors)
            VALUES ($organId, $requestId, $requestId, $score, " . intval($organ['donor_age']) . ", '" . mysqli_real_escape_string($conn, $organ['blood_group']) . "', '" . mysqli_real_escape_string($conn, $organ['city']) . "', '" . mysqli_real_escape_string($conn, $organ['organ_type']) . "', " . intval($request['age']) . ", '" . mysqli_real_escape_string($conn, $request['blood_group']) . "', '" . mysqli_real_escape_string($conn, $request['city']) . "', " . intval($request['waiting_days']) . ", '$priority', $distance, 1, $bloodOk, $cityMatch, 'Pending', 'pending', '$factorsJson')");
        notify_coordinators($conn, "AI matched one " . $organ['organ_type'] . " (" . $organ['blood_group'] . ") to Patient #" . $requestId . ", Score: " . round($score, 1) . "%", "AI match found", "match_found", "review_matches.php?organ_type=" . urlencode($organ['organ_type']));
        $usedOrgans[$organId] = true;
        $usedRecipients[$requestId] = true;
        $created++;
    }

    $message = "AI matching completed. Created $created best organ-recipient pair(s). Each available organ is matched to only one recipient.";
    notify_coordinators($conn, "AI matching run completed. Created $created best organ-recipient pair(s).", "AI matching run", "match_found", "review_matches.php");
}
$top = mysqli_query($conn, "SELECT am.*, ol.organ_type, ol.blood_group AS donor_blood, ol.condition, ol.city AS donor_city, dh.name AS donor_hospital, req.patient_name, req.blood_group AS recipient_blood, req.city AS recipient_city, rh.name AS recipient_hospital
    FROM ai_matches am
    JOIN organ_listings ol ON am.organ_id=ol.id
    JOIN hospitals dh ON ol.hospital_id=dh.id
    JOIN recipients req ON am.recipient_id=req.id
    JOIN hospitals rh ON req.hospital_id=rh.id
    ORDER BY am.matched_at DESC, am.match_score DESC
    LIMIT 20");
html_head('Run AI Matching');
page_header('Run AI Matching', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Run AI Matching</h1><p>Stores the top ranked matches per available organ.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="POST" class="card"><button class="btn btn-teal" type="submit">Run AI Matching</button><a class="btn btn-outline" href="review_matches.php">Review Matches</a></form>
    <div class="match-grid">
        <?php if ($top && mysqli_num_rows($top) > 0): while ($r = mysqli_fetch_assoc($top)): ?>
            <?php $factors = json_decode($r['score_factors'] ?? '', true) ?: []; ?>
            <article class="match-card">
                <h3><?php echo htmlspecialchars($r['organ_type'] . ' ' . $r['donor_blood']); ?> -> <?php echo htmlspecialchars($r['patient_name']); ?></h3>
                <?php echo score_bar($r['match_score']); ?>
                <div class="match-meta">
                    <p><strong>Organ</strong><br><?php echo htmlspecialchars($r['condition'] . ' / ' . $r['donor_hospital'] . ' / ' . $r['donor_city']); ?></p>
                    <p><strong>Recipient</strong><br><?php echo htmlspecialchars($r['recipient_blood'] . ' / ' . $r['recipient_hospital'] . ' / ' . $r['recipient_city']); ?></p>
                </div>
                <div class="factor-list">
                    <span class="factor-chip"><?php echo $r['blood_group_compatible'] ? 'Compatible blood' : 'Blood review'; ?></span>
                    <span class="factor-chip"><?php echo round($r['distance_km'], 1); ?> km</span>
                    <span class="factor-chip"><?php echo htmlspecialchars($r['decision']); ?></span>
                    <?php if (isset($factors['penalty_pct'])): ?><span class="factor-chip">Penalty <?php echo htmlspecialchars($factors['penalty_pct']); ?>%</span><?php endif; ?>
                </div>
            </article>
        <?php endwhile; else: ?>
            <div class="card">No AI matches have been generated yet.</div>
        <?php endif; ?>
    </div>
</main>
<?php html_end(); ?>
