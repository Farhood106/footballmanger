<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <!-- Scoreboard -->
    <div style="text-align:center; padding: 20px 0;">
        <div style="display:flex; justify-content:center; align-items:center; gap:40px;">
            <div style="font-size:20px; font-weight:bold;">
                <?= htmlspecialchars($match['home_club_name']) ?>
            </div>

            <div style="font-size:48px; font-weight:bold; color:#2c3e50;">
                <?= $match['home_goals'] ?> - <?= $match['away_goals'] ?>
            </div>

            <div style="font-size:20px; font-weight:bold;">
                <?= htmlspecialchars($match['away_club_name']) ?>
            </div>
        </div>

        <p style="color:#666; margin-top:10px;">
            <?= $match['match_date'] ?> | <?= htmlspecialchars($match['competition_name'] ?? 'دوستانه') ?>
        </p>
    </div>
</div>

<div class="grid">
    <!-- Match Events -->
    <div class="card">
        <h3>رویدادهای بازی</h3>

        <?php if (empty($events)): ?>
            <p>رویدادی ثبت نشده است.</p>
        <?php else: ?>
            <div style="margin-top: 15px;">
                <?php foreach ($events as $e): ?>
                <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #eee;">
                    <span style="background:#2c3e50; color:white; padding:3px 8px; border-radius:4px; font-size:13px; min-width:40px; text-align:center;">
                        <?= $e['minute'] ?>'
                    </span>

                    <span style="font-size:20px;">
                        <?php
                        $icons = [
                            'GOAL'        => '⚽',
                            'YELLOW_CARD' => '🟨',
                            'RED_CARD'    => '🟥',
                            'SUBSTITUTION'=> '🔄',
                            'PENALTY'     => '🎯',
                            'OWN_GOAL'    => '😬',
                        ];
                        echo $icons[$e['event_type']] ?? '📌';
                        ?>
                    </span>

                    <span><?= htmlspecialchars($e['player_name'] ?? '') ?></span>

                    <?php if (!empty($e['secondary_player_name'])): ?>
                        <span style="color:#666; font-size:13px;">
                            (<?= htmlspecialchars($e['secondary_player_name']) ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Match Stats -->
    <div class="card">
        <h3>آمار بازی</h3>

        <?php
        $statLabels = [
            'possession'   => 'تسلط بر توپ',
            'shots'        => 'شوت',
            'shots_on_target' => 'شوت به هدف',
            'corners'      => 'کرنر',
            'fouls'        => 'خطا',
            'xg'           => 'گل مورد انتظار (xG)',
        ];
        ?>

        <table class="table" style="margin-top:10px;">
            <tr>
                <th><?= htmlspecialchars($match['home_club_name']) ?></th>
                <th>آمار</th>
                <th><?= htmlspecialchars($match['away_club_name']) ?></th>
            </tr>

            <?php foreach ($statLabels as $key => $label): ?>
            <tr>
                <td style="text-align:center;"><?= $matchStats['home'][$key] ?? '-' ?></td>
                <td style="text-align:center; font-weight:bold;"><?= $label ?></td>
                <td style="text-align:center;"><?= $matchStats['away'][$key] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- Player Ratings -->
<?php if (!empty($ratings)): ?>
<div class="card">
    <h3>امتیاز بازیکنان</h3>

    <div class="grid" style="margin-top:15px;">
        <?php foreach (['home', 'away'] as $side): ?>
        <?php if (!empty($ratings[$side])): ?>
        <div>
            <h4 style="margin-bottom:10px;">
                <?= $side == 'home'
                    ? htmlspecialchars($match['home_club_name'])
                    : htmlspecialchars($match['away_club_name']) ?>
            </h4>

            <table class="table">
                <tr><th>بازیکن</th><th>امتیاز</th></tr>
                <?php foreach ($ratings[$side] as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['player_name']) ?></td>
                    <td>
                        <span style="color: <?= $r['rating'] >= 7 ? 'green' : ($r['rating'] >= 5 ? 'orange' : 'red') ?>; font-weight:bold;">
                            <?= number_format($r['rating'], 1) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div style="margin-top: 10px;">
    <a href="/matches" class="btn">بازگشت به بازی‌ها</a>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
