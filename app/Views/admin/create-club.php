<?php $title = 'ایجاد باشگاه'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>ایجاد باشگاه جدید</h2>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" action="/admin/clubs/create">
        <div class="form-group"><label>نام باشگاه</label><input name="name" required></div>
        <div class="form-group"><label>نام کوتاه</label><input name="short_name" maxlength="10" required></div>
        <div class="form-group"><label>کشور</label><input name="country" required></div>
        <div class="form-group"><label>شهر</label><input name="city" required></div>
        <div class="form-group"><label>سال تاسیس</label><input type="number" name="founded" value="2000" required></div>
        <div class="form-group"><label>نام ورزشگاه</label><input name="stadium_name" required></div>
        <div class="form-group"><label>ظرفیت ورزشگاه</label><input type="number" name="stadium_capacity" value="30000"></div>
        <div class="form-group"><label>اعتبار</label><input type="number" name="reputation" value="50"></div>
        <div class="form-group"><label>موجودی</label><input type="number" name="balance" value="10000000"></div>
        <button class="btn btn-success" type="submit">ثبت باشگاه</button>
        <a class="btn" href="/admin">بازگشت</a>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
