<?php $title = 'مدیریت درخواست‌های خرید باشگاه'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>مدیریت درخواست‌های خرید باشگاه</h2>
    <p>
        تا وقتی باشگاه مالک ندارد، ادمین می‌تواند تأیید/رد کند.
        پس از مالک‌دار شدن، فقط مالک فعلی (و ادمین) می‌تواند انتقال مالکیت را بررسی کند.
    </p>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>باشگاه</th>
                <th>متقاضی</th>
                <th>مبلغ پیشنهادی</th>
                <th>تاریخ</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($pending ?? []) as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['club_name']) ?></td>
                <td><?= htmlspecialchars($r['requester_name']) ?></td>
                <td><?= number_format((int)$r['offer_amount']) ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
                <td>
                    <form method="post" action="/ownership/approve" style="display:inline-block">
                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-success" type="submit">تایید</button>
                    </form>
                    <form method="post" action="/ownership/reject" style="display:inline-block">
                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-danger" type="submit">رد</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pending)): ?>
            <tr><td colspan="5">درخواست در انتظار بررسی وجود ندارد.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
