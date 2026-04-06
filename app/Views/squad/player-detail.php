<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2><?= htmlspecialchars($player['name']) ?></h2>

    <div class="grid">
        <div>
            <table class="table">
                <tr><th>پست</th><td><?= htmlspecialchars($player['position']) ?></td></tr>
                <tr><th>سن</th><td><?= $player['age'] ?></td></tr>
                <tr><th>ملیت</th><td><?= htmlspecialchars($player['nationality']) ?></td></tr>
                <tr><th>قدرت کلی</th><td><?= $player['overall'] ?></td></tr>
                <tr><th>استعداد</th><td><?= $player['potential'] ?></td></tr>
                <tr><th>ارزش</th><td><?= number_format($player['market_value']) ?> $</td></tr>
                <tr><th>حقوق</th><td><?= number_format($player['wage']) ?> $ / هفته</td></tr>
                <tr>
                    <th>وضعیت</th>
                    <td>
                        <?php if ($player['is_injured']): ?>
                            <span style="color:red;">مصدوم (<?= $player['injury_days'] ?> روز)</span>
                        <?php else: ?>
                            <span style="color:green;">سالم</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div>
            <h3 style="margin-bottom: 10px;">ویژگی‌های فنی</h3>
            <table class="table">
                <tr><th>سرعت</th><td><?= $player['pace'] ?></td></tr>
                <tr><th>شوت</th><td><?= $player['shooting'] ?></td></tr>
                <tr><th>پاس</th><td><?= $player['passing'] ?></td></tr>
                <tr><th>دریبل</th><td><?= $player['dribbling'] ?></td></tr>
                <tr><th>دفاع</th><td><?= $player['defending'] ?></td></tr>
                <tr><th>فیزیک</th><td><?= $player['physical'] ?></td></tr>
            </table>
        </div>
    </div>

    <?php if (!empty($abilities)): ?>
    <div style="margin-top: 20px;">
        <h3>توانایی‌های ویژه</h3>
        <div class="grid" style="margin-top: 10px;">
            <?php foreach ($abilities as $ab): ?>
            <div class="card" style="background:#f8f9fa; padding:10px;">
                <strong><?= htmlspecialchars($ab['name']) ?></strong>
                <p style="font-size:13px; color:#666;"><?= htmlspecialchars($ab['description']) ?></p>
                <small>سطح: <?= $ab['level'] ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($stats)): ?>
    <div style="margin-top: 20px;">
        <h3>آمار فصل جاری</h3>
        <table class="table">
            <tr>
                <th>بازی</th>
                <th>گل</th>
                <th>پاس گل</th>
                <th>کارت زرد</th>
                <th>کارت قرمز</th>
                <th>امتیاز میانگین</th>
            </tr>
            <tr>
                <td><?= $stats['appearances'] ?? 0 ?></td>
                <td><?= $stats['goals'] ?? 0 ?></td>
                <td><?= $stats['assists'] ?? 0 ?></td>
                <td><?= $stats['yellow_cards'] ?? 0 ?></td>
                <td><?= $stats['red_cards'] ?? 0 ?></td>
                <td><?= number_format($stats['avg_rating'] ?? 0, 1) ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="/squad" class="btn">بازگشت به ترکیب</a>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
