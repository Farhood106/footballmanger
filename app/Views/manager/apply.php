<?php $title = 'ارسال درخواست مربیگری'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>درخواست پست مربیگری</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<?php foreach (($clubs ?? []) as $club): ?>
<div class="card">
    <h3><?= htmlspecialchars($club['name']) ?></h3>
    <?php $exp = $club['expectation'] ?? null; ?>
    <p><strong>انتظارات مالک:</strong> <?= nl2br(htmlspecialchars($exp['expectations'] ?? 'هنوز ثبت نشده')) ?></p>
    <p><strong>وظایف:</strong> <?= nl2br(htmlspecialchars($exp['duties'] ?? 'هنوز ثبت نشده')) ?></p>
    <p><strong>تعهدات:</strong> <?= nl2br(htmlspecialchars($exp['commitments'] ?? 'هنوز ثبت نشده')) ?></p>

    <form method="post" action="/manager/apply/submit">
        <input type="hidden" name="club_id" value="<?= (int)$club['id'] ?>">
        <div class="form-group"><label>نسخه پیشنهادی انتظارات</label><textarea name="proposed_expectations" rows="3"><?= htmlspecialchars($exp['expectations'] ?? '') ?></textarea></div>
        <div class="form-group"><label>نسخه پیشنهادی وظایف</label><textarea name="proposed_duties" rows="3"><?= htmlspecialchars($exp['duties'] ?? '') ?></textarea></div>
        <div class="form-group"><label>نسخه پیشنهادی تعهدات</label><textarea name="proposed_commitments" rows="3"><?= htmlspecialchars($exp['commitments'] ?? '') ?></textarea></div>
        <div class="form-group"><label>توضیح درخواست</label><textarea name="cover_letter" rows="3" placeholder="دلایل و برنامه شما"></textarea></div>
        <button class="btn btn-success" type="submit">ارسال درخواست مربیگری</button>
    </form>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
