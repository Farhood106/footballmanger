<?php $title = 'Governance Cases'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>My Governance Cases</h2>
    <a class="btn" href="/governance/cases/new">New Case</a>
</div>
<div class="card">
<table class="table">
<thead><tr><th>Club</th><th>Type</th><th>Subject</th><th>Status</th><th>Opened</th><th></th></tr></thead>
<tbody>
<?php foreach (($cases ?? []) as $case): ?>
<tr>
    <td><?= htmlspecialchars($case['club_name']) ?></td>
    <td><?= htmlspecialchars($case['case_type']) ?></td>
    <td><?= htmlspecialchars($case['subject']) ?></td>
    <td><?= htmlspecialchars($case['status']) ?></td>
    <td><?= htmlspecialchars($case['opened_at']) ?></td>
    <td><a class="btn" href="/governance/cases/<?= (int)$case['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($cases)): ?><tr><td colspan="6">No cases yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
