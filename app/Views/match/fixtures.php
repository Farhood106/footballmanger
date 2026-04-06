<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>بازی‌های پیش‌رو</h2>

    <?php if (empty($upcoming)): ?>
        <p>بازی پیش‌رو ندارید.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>رقیب</th>
                <th>تاریخ</th>
                <th>نوع</th>
            </tr>

            <?php foreach ($upcoming as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['opponent_name']) ?></td>
                <td><?= $m['match_date'] ?></td>
                <td><?= $m['home_away'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>نتایج اخیر</h2>

    <?php if (empty($recent)): ?>
        <p>هنوز بازی انجام نشده است.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>رقیب</th>
                <th>نتیجه</th>
                <th>جزئیات</th>
            </tr>

            <?php foreach ($recent as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['opponent_name']) ?></td>
                <td><?= $m['home_goals'] ?> - <?= $m['away_goals'] ?></td>
                <td><a class="btn" href="/match/<?= $m['id'] ?>">مشاهده</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
