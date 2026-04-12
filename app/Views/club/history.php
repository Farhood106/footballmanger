<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2><?= htmlspecialchars((string)($club['name'] ?? 'Club')) ?> — Awards & History</h2>
    <p class="text-muted">MVP overview of recognitions, honors, records, and club legends.</p>
</div>

<div class="card">
    <h3>Recent Match Recognitions</h3>
    <?php if (empty($recent_recognitions)): ?>
        <p>No recent player-of-match/week recognitions yet.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>Type</th>
                <th>Player</th>
                <th>Competition</th>
                <th>Week</th>
                <th>Score</th>
            </tr>
            <?php foreach ($recent_recognitions as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['award_type']) ?></td>
                    <td><?= htmlspecialchars((string)$row['player_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['competition_name']) ?></td>
                    <td><?= (int)($row['week_number'] ?? 0) ?: '-' ?></td>
                    <td><?= number_format((float)($row['score_value'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Season Awards</h3>
    <?php if (empty($season_awards)): ?>
        <p>No season awards for this club yet.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>Season</th>
                <th>Award</th>
                <th>Player</th>
                <th>Competition</th>
            </tr>
            <?php foreach ($season_awards as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['season_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['award_type']) ?></td>
                    <td><?= htmlspecialchars((string)$row['player_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['competition_name']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Club Honors</h3>
    <?php if (empty($honors)): ?>
        <p>No club honors recorded yet.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>Season</th>
                <th>Honor</th>
                <th>Competition</th>
                <th>Details</th>
            </tr>
            <?php foreach ($honors as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['season_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['honor_type']) ?></td>
                    <td><?= htmlspecialchars((string)$row['competition_name']) ?></td>
                    <td><?= htmlspecialchars((string)($row['details'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="grid">
    <div class="card">
        <h3>Club Records</h3>
        <?php if (empty($records)): ?>
            <p>No records calculated yet.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Record</th><th>Player</th><th>Value</th></tr>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['record_key']) ?></td>
                        <td><?= htmlspecialchars((string)($row['player_name'] ?? ('#' . (int)$row['player_id']))) ?></td>
                        <td><?= (int)$row['record_value'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Club Legends</h3>
        <?php if (empty($legends)): ?>
            <p>No legends yet.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Player</th><th>Status</th><th>Score</th></tr>
                <?php foreach ($legends as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['player_name']) ?></td>
                        <td><?= htmlspecialchars((string)$row['status']) ?></td>
                        <td><?= (int)$row['legend_score'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
