<?php $title = 'بررسی درخواست‌های مربیگری'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>بررسی درخواست‌های مربیگری</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
<table class="table">
<thead><tr><th>باشگاه</th><th>مربی</th><th>پیشنهاد</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach (($pending ?? []) as $a): ?>
<tr>
    <td><?= htmlspecialchars($a['club_name']) ?></td>
    <td><?= htmlspecialchars($a['coach_name']) ?></td>
    <td>
        <div><strong>انتظارات:</strong> <?= nl2br(htmlspecialchars($a['proposed_expectations'] ?? '')) ?></div>
        <div><strong>وظایف:</strong> <?= nl2br(htmlspecialchars($a['proposed_duties'] ?? '')) ?></div>
        <div><strong>تعهدات:</strong> <?= nl2br(htmlspecialchars($a['proposed_commitments'] ?? '')) ?></div>
    </td>
    <td>
        <form method="post" action="/manager/applications/approve" style="display:inline-block;">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-success" type="submit">تایید</button>
        </form>
        <form method="post" action="/manager/applications/reject" style="display:inline-block;">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-danger" type="submit">رد</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pending)): ?><tr><td colspan="4">درخواستی موجود نیست.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
