<?php $title = 'Season Fixtures'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>Season Fixtures #<?= (int)$season_id ?></h2>
    <a class="btn" href="/admin/competitions">Back</a>
</div>
<div class="card">
<table class="table">
<thead><tr><th>Week</th><th>Scheduled</th><th>Home</th><th>Away</th><th>Status</th></tr></thead>
<tbody>
<?php foreach (($fixtures ?? []) as $f): ?>
<tr>
    <td><?= (int)$f['week'] ?></td>
    <td><?= htmlspecialchars($f['scheduled_at']) ?></td>
    <td><?= htmlspecialchars($f['home_club_name']) ?></td>
    <td><?= htmlspecialchars($f['away_club_name']) ?></td>
    <td><?= htmlspecialchars($f['status']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($fixtures)): ?><tr><td colspan="5">No fixtures generated.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
