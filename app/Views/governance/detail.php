<?php $title = 'Governance Case Detail'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2><?= htmlspecialchars($case['subject']) ?></h2>
    <p><strong>Club:</strong> <?= htmlspecialchars($case['club_name']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($case['status']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($case['case_type']) ?></p>
    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($case['description'])) ?></p>
</div>

<div class="card">
    <h3>Decisions</h3>
    <?php foreach (($case['decisions'] ?? []) as $d): ?>
        <div style="border-top:1px solid #eee; padding:10px 0;">
            <p><strong><?= htmlspecialchars($d['decision_type']) ?></strong> by <?= htmlspecialchars($d['decided_by_name'] ?? 'System') ?> at <?= htmlspecialchars($d['decided_at']) ?></p>
            <p><?= nl2br(htmlspecialchars($d['decision_summary'])) ?></p>
            <p>Penalty: <?= number_format((int)$d['penalty_amount']) ?> | Compensation: <?= number_format((int)$d['compensation_amount']) ?></p>
        </div>
    <?php endforeach; ?>
    <?php if (empty($case['decisions'])): ?><p>No decisions yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
