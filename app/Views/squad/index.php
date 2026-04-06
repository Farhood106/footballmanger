<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>فهرست بازیکنان</h2>

    <table class="table">
        <tr>
            <th>نام</th>
            <th>سن</th>
            <th>پست</th>
            <th>قدرت</th>
            <th>جزئیات</th>
        </tr>

        <?php foreach ($squad as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= $p['age'] ?></td>
            <td><?= htmlspecialchars($p['position']) ?></td>
            <td><?= $p['overall'] ?></td>
            <td>
                <a class="btn" href="/squad/player/<?= $p['id'] ?>">مشاهده</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>بازیکنان مصدوم</h2>

    <?php if (empty($injured)): ?>
        <p>بازیکن مصدومی ندارید.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>نام</th>
                <th>مدت</th>
            </tr>

            <?php foreach ($injured as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= $p['injury_days'] ?> روز</td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
