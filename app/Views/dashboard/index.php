<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="grid">

    <div class="stat-box">
        <h3><?= htmlspecialchars($club['name']) ?></h3>
        <p>نام باشگاه</p>
    </div>

    <div class="stat-box">
        <h3><?= number_format($finances['balance'] ?? 0) ?> $</h3>
        <p>بودجه باشگاه</p>
    </div>

    <div class="stat-box">
        <h3><?= $club['reputation'] ?>/100</h3>
        <p>شهرت باشگاه</p>
    </div>

    <div class="stat-box">
        <h3><?= count($upcoming) ?></h3>
        <p>بازی‌های پیش‌رو</p>
    </div>

</div>


<div class="card">
    <a class="btn" href="/finance">Finance Ledger & Funding</a>
</div>

<div class="card">
    <h2>بازی‌های پیش‌رو</h2>
    <?php if (empty($upcoming)): ?>
        <p>هیچ بازی برنامه‌ریزی نشده است.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>رقیب</th>
                <th>نوع</th>
                <th>تاریخ</th>
            </tr>
            <?php foreach ($upcoming as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['opponent_name']) ?></td>
                    <td><?= $m['home_away'] == 'HOME' ? 'میزبان' : 'میهمان' ?></td>
                    <td><?= $m['match_date'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>نتایج اخیر</h2>

    <?php if (empty($recent)): ?>
        <p>هنوز هیچ بازی انجام نشده است.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>رقیب</th>
                <th>نتیجه</th>
                <th>لینک</th>
            </tr>
            <?php foreach ($recent as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['opponent_name']) ?></td>
                    <td><?= $m['home_goals'] ?> - <?= $m['away_goals'] ?></td>
                    <td><a class="btn" href="/match/<?= $m['id'] ?>">جزئیات</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>اعلان‌ها</h2>

    <?php if (empty($notifications)): ?>
        <p>اعلان جدیدی وجود ندارد.</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="alert <?= $n['type'] == 'SUCCESS' ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($n['message']) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
