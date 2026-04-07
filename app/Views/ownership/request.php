<?php $title = 'درخواست خرید باشگاه'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>ثبت درخواست مالکیت باشگاه</h2>
    <p>شما با نقش «مالک» ثبت‌نام کرده‌اید. از اینجا درخواست خرید باشگاه ثبت کنید.</p>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" action="/ownership/request">
        <div class="form-group">
            <label>باشگاه بدون مالک</label>
            <select name="club_id" required>
                <option value="">انتخاب باشگاه</option>
                <?php foreach (($clubs ?? []) as $club): ?>
                    <option value="<?= (int)$club['id'] ?>"><?= htmlspecialchars($club['name']) ?> - <?= htmlspecialchars($club['city']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>مبلغ پیشنهادی (اختیاری)</label>
            <input type="number" name="offer_amount" min="0" value="0">
        </div>

        <div class="form-group">
            <label>توضیحات</label>
            <textarea name="message" rows="4" placeholder="مثلاً برنامه سرمایه‌گذاری و مدیریت باشگاه"></textarea>
        </div>

        <button class="btn btn-success" type="submit">ثبت درخواست</button>
    </form>
</div>

<div class="card">
    <h3>درخواست‌های من</h3>
    <table class="table">
        <thead><tr><th>باشگاه</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody>
        <?php foreach (($requests ?? []) as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['club_name']) ?></td>
                <td><?= number_format((int)$r['offer_amount']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($requests)): ?>
            <tr><td colspan="4">درخواستی ثبت نشده است.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
