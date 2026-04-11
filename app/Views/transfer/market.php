<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>بازیکنان در لیست انتقال</h2>

    <?php if (empty($players)): ?>
        <p>بازیکن در لیست انتقال وجود ندارد.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>نام</th>
                <th>پست</th>
                <th>قدرت</th>
                <th>قیمت</th>
                <th>خرید</th>
            </tr>

            <?php foreach ($players as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['position']) ?></td>
                <td><?= $p['overall'] ?></td>
                <td><?= number_format($p['market_value']) ?> $</td>
                <td>
                    <form method="POST" action="/transfer/bid" data-ajax>
                        <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                        <input type="number" name="amount" value="<?= $p['market_value'] ?>" style="width:100px;">
                        <button class="btn btn-success">پیشنهاد</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
