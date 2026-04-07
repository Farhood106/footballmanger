<?php $title = 'ایجاد بازیکن'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>ایجاد بازیکن جدید</h2>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" action="/admin/players/create">
        <div class="form-group"><label>نام</label><input name="first_name" required></div>
        <div class="form-group"><label>نام خانوادگی</label><input name="last_name" required></div>
        <div class="form-group"><label>باشگاه</label>
            <select name="club_id" required>
                <option value="">انتخاب باشگاه</option>
                <?php foreach (($clubs ?? []) as $club): ?>
                    <option value="<?= (int)$club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>ملیت</label><input name="nationality" value="Iran"></div>
        <div class="form-group"><label>تاریخ تولد</label><input type="date" name="birth_date" value="2000-01-01"></div>
        <div class="form-group"><label>پست</label><input name="position" value="CM"></div>
        <div class="form-group"><label>پای تخصصی</label><input name="preferred_foot" value="RIGHT"></div>
        <div class="grid">
            <div class="form-group"><label>pace</label><input type="number" name="pace" value="60"></div>
            <div class="form-group"><label>shooting</label><input type="number" name="shooting" value="60"></div>
            <div class="form-group"><label>passing</label><input type="number" name="passing" value="60"></div>
            <div class="form-group"><label>dribbling</label><input type="number" name="dribbling" value="60"></div>
            <div class="form-group"><label>defending</label><input type="number" name="defending" value="60"></div>
            <div class="form-group"><label>physical</label><input type="number" name="physical" value="60"></div>
        </div>
        <div class="form-group"><label>overall</label><input type="number" name="overall" value="60"></div>
        <div class="form-group"><label>potential</label><input type="number" name="potential" value="75"></div>
        <div class="form-group"><label>wage</label><input type="number" name="wage" value="0"></div>
        <div class="form-group"><label>market_value</label><input type="number" name="market_value" value="0"></div>

        <button class="btn btn-success" type="submit">ثبت بازیکن</button>
        <a class="btn" href="/admin">بازگشت</a>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
