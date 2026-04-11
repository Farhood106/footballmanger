<?php $title = 'Governance Review'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>Open Governance Cases (Commission)</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
</div>
<?php foreach (($cases ?? []) as $case): ?>
<div class="card" style="margin-bottom:16px;">
    <h3><?= htmlspecialchars($case['club_name']) ?> - <?= htmlspecialchars($case['subject']) ?></h3>
    <p><strong>Type:</strong> <?= htmlspecialchars($case['case_type']) ?></p>
    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($case['description'])) ?></p>

    <form method="post" action="/governance/review/<?= (int)$case['id'] ?>/resolve">
        <div class="form-group">
            <label>Decision type</label>
            <select name="decision_type" required>
                <?php foreach (['CASE_UPHELD','CASE_REJECTED','WARNING','PENALTY','COMPENSATION','MIXED'] as $d): ?>
                    <option value="<?= $d ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Decision summary</label>
            <textarea name="decision_summary" rows="3" required></textarea>
        </div>
        <div class="grid">
            <div class="form-group">
                <label>Penalty amount</label>
                <input type="number" name="penalty_amount" min="0" value="0">
            </div>
            <div class="form-group">
                <label>Compensation amount</label>
                <input type="number" name="compensation_amount" min="0" value="0">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Resolve Case</button>
    </form>
</div>
<?php endforeach; ?>
<?php if (empty($cases)): ?><div class="card">No open cases.</div><?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
