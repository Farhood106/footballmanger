<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2>برنامه بازی‌ها — <?= htmlspecialchars($competition['name']) ?></h2>
        <a href="/competition/<?= $competition['id'] ?>/standings" class="btn">جدول لیگ</a>
    </div>

    <?php
    // Group fixtures by matchday
    $grouped = [];
    foreach ($fixtures as $f) {
        $grouped[$f['matchday']][] = $f;
    }
    ksort($grouped);
    ?>

    <?php foreach ($grouped as $matchday => $games): ?>
    <div style="margin-bottom: 25px;">
        <h3 style="background:#2c3e50; color:white; padding:8px 15px; border-radius:4px; margin-bottom:10px;">
            هفته <?= $matchday ?>
        </h3>

        <table class="table">
            <tr>
                <th>میزبان</th>
                <th>نتیجه</th>
                <th>میهمان</th>
                <th>تاریخ</th>
                <th></th>
            </tr>

            <?php foreach ($games as $g): ?>
            <tr style="<?= ($g['home_club_id'] == $userClubId || $g['away_club_id'] == $userClubId) ? 'background:#e8f4fd;' : '' ?>">
                <td><?= htmlspecialchars($g['home_club_name']) ?></td>
                <td style="text-align:center; font-weight:bold;">
                    <?php if ($g['status'] == 'FINISHED'): ?>
                        <?= $g['home_goals'] ?> - <?= $g['away_goals'] ?>
                    <?php else: ?>
                        <span style="color:#999;">vs</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($g['away_club_name']) ?></td>
                <td><?= $g['match_date'] ?></td>
                <td>
                    <?php if ($g['status'] == 'FINISHED'): ?>
                        <a href="/match/<?= $g['id'] ?>" class="btn" style="padding:5px 10px; font-size:13px;">جزئیات</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
