<?php $title = 'تعریف انتظارات مربی'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>تعریف انتظارات، وظایف و تعهدات مربی</h2>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="get" action="/manager/expectations" style="margin-bottom:20px;">
        <label>انتخاب باشگاه</label>
        <select name="club_id" onchange="this.form.submit()">
            <?php foreach (($clubs ?? []) as $club): ?>
                <option value="<?= (int)$club['id'] ?>" <?= ((int)($selected_club_id ?? 0) === (int)$club['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($club['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="/manager/expectations/save">
        <input type="hidden" name="club_id" value="<?= (int)($selected_club_id ?? 0) ?>">
        <div class="form-group"><label>عنوان</label><input name="title" value="<?= htmlspecialchars($expectation['title'] ?? 'پست سرمربی') ?>"></div>
        <div class="form-group"><label>انتظارات</label><textarea name="expectations" rows="4"><?= htmlspecialchars($expectation['expectations'] ?? '') ?></textarea></div>
        <div class="form-group"><label>وظایف</label><textarea name="duties" rows="4"><?= htmlspecialchars($expectation['duties'] ?? '') ?></textarea></div>
        <div class="form-group"><label>تعهدات</label><textarea name="commitments" rows="4"><?= htmlspecialchars($expectation['commitments'] ?? '') ?></textarea></div>
        <button class="btn btn-success" type="submit">ذخیره</button>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
