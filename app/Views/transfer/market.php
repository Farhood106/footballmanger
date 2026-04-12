<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>بازیکنان باشگاه من (لیست فروش)</h2>
    <table class="table">
        <tr>
            <th>نام</th><th>پست</th><th>قدرت</th><th>ارزش بازار</th><th>وضعیت لیست</th><th>عملیات</th>
        </tr>
        <?php foreach (($squad ?? []) as $p): ?>
            <tr>
                <td><?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)$p['position']) ?></td>
                <td><?= (int)$p['overall'] ?></td>
                <td><?= number_format((int)($p['market_value'] ?? 0)) ?> $</td>
                <td><?= !empty($p['is_transfer_listed']) ? 'Listed' : 'Not listed' ?></td>
                <td>
                    <form method="POST" action="/transfer/listing">
                        <input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="listed" value="<?= !empty($p['is_transfer_listed']) ? 0 : 1 ?>">
                        <input type="number" name="asking_price" min="1" value="<?= (int)($p['asking_price'] ?? $p['market_value'] ?? 1) ?>" style="width:120px;">
                        <button class="btn"><?= !empty($p['is_transfer_listed']) ? 'Unlist' : 'List' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($squad)): ?><tr><td colspan="6">بازیکنی یافت نشد.</td></tr><?php endif; ?>
    </table>
</div>

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
                <th>ارزش/قیمت</th>
                <th>خرید</th>
            </tr>

            <?php foreach ($players as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['position']) ?></td>
                <td><?= $p['overall'] ?></td>
                <td><?= number_format((int)($p['market_value'] ?? 0)) ?> $ / <?= number_format((int)($p['asking_price'] ?? 0)) ?> $</td>
                <td>
                    <form method="POST" action="/transfer/bid" data-ajax>
                        <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                        <input type="number" name="amount" value="<?= (int)($p['asking_price'] ?? $p['market_value'] ?? 1) ?>" style="width:120px;">
                        <button class="btn btn-success">پیشنهاد</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>پیشنهادهای دریافتی</h2>
    <table class="table">
        <tr><th>بازیکن</th><th>باشگاه خریدار</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th></tr>
        <?php foreach (($incoming_offers ?? []) as $o): ?>
            <tr>
                <td><?= htmlspecialchars((string)$o['player_name']) ?></td>
                <td><?= htmlspecialchars((string)($o['to_club_name'] ?? '-')) ?></td>
                <td><?= number_format((int)$o['fee']) ?> $</td>
                <td><?= htmlspecialchars((string)$o['status']) ?></td>
                <td>
                    <form method="POST" action="/transfer/accept/<?= (int)$o['id'] ?>" data-ajax style="display:inline-block;">
                        <button class="btn btn-success">قبول</button>
                    </form>
                    <form method="POST" action="/transfer/reject/<?= (int)$o['id'] ?>" data-ajax style="display:inline-block;">
                        <button class="btn">رد</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($incoming_offers)): ?><tr><td colspan="5">پیشنهاد فعالی وجود ندارد.</td></tr><?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
