<?php $title = 'Submit Governance Case'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>Submit Governance Case</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="/governance/cases/create">
        <div class="form-group">
            <label>Club</label>
            <select name="club_id" required>
                <option value="">Select club</option>
                <?php foreach (($clubs ?? []) as $club): ?>
                    <option value="<?= (int)$club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Case type</label>
            <select name="case_type" required>
                <?php foreach (['UNFAIR_DISMISSAL','COMPENSATION_DISAGREEMENT','CONTRACT_BREACH','MUTUAL_TERMINATION_DISPUTE','OTHER'] as $t): ?>
                    <option value="<?= $t ?>"><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" required maxlength="255">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Create Case</button>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
