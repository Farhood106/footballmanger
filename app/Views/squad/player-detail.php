<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2><?= htmlspecialchars((string)($player['full_name'] ?? trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')))) ?></h2>

    <div class="grid">
        <div>
            <table class="table">
                <tr><th>پست</th><td><?= htmlspecialchars($player['position']) ?></td></tr>
                <tr><th>سن</th><td><?= (int)(date('Y') - (int)date('Y', strtotime((string)$player['birth_date']))) ?></td></tr>
                <tr><th>ملیت</th><td><?= htmlspecialchars((string)$player['nationality']) ?></td></tr>
                <tr><th>قدرت کلی</th><td><?= $player['overall'] ?></td></tr>
                <tr><th>استعداد</th><td><?= $player['potential'] ?></td></tr>
                <tr><th>نقش در تیم</th><td><?= htmlspecialchars((string)(($role_labels[$player['squad_role'] ?? 'ROTATION'] ?? ($player['squad_role'] ?? 'ROTATION')))) ?></td></tr>
                <tr><th>منشأ آکادمی</th><td><?= !empty($player['is_academy_origin']) ? 'بله' : 'خیر' ?></td></tr>
                <tr><th>باشگاه آکادمی</th><td><?= !empty($player['academy_origin_club_id']) ? (int)$player['academy_origin_club_id'] : '-' ?></td></tr>
                <tr><th>فصل ورودی آکادمی</th><td><?= !empty($player['academy_intake_season_id']) ? (int)$player['academy_intake_season_id'] : '-' ?></td></tr>
                <tr><th>آخرین بازی</th><td><?= !empty($player['last_played_at']) ? htmlspecialchars((string)$player['last_played_at']) : 'ثبت نشده' ?></td></tr>
                <tr><th>دقایق آخرین بازی</th><td><?= (int)($player['last_minutes_played'] ?? 0) ?></td></tr>
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
            <div style="margin-top:10px; font-size:13px; color:#555;">
                <?php $role = (string)($player['squad_role'] ?? 'ROTATION'); ?>
                <?php if (in_array($role, ['KEY_PLAYER', 'REGULAR_STARTER'], true)): ?>
                    انتظار نقش: بازی منظم و دقایق بالا.
                <?php elseif ($role === 'ROTATION'): ?>
                    انتظار نقش: بازی متناوب و مدیریت بار.
                <?php else: ?>
                    انتظار نقش: فرصت کمتر، توسعه یا پشتیبانی ترکیب.
                <?php endif; ?>
            </div>
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
                <small>کد: <?= htmlspecialchars((string)$ab['code']) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($season_stats)): ?>
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
                <td><?= $season_stats['appearances'] ?? 0 ?></td>
                <td><?= $season_stats['goals'] ?? 0 ?></td>
                <td><?= $season_stats['assists'] ?? 0 ?></td>
                <td><?= $season_stats['yellow_cards'] ?? 0 ?></td>
                <td><?= $season_stats['red_cards'] ?? 0 ?></td>
                <td><?= number_format($season_stats['avg_rating'] ?? 0, 1) ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($career_stats)): ?>
    <div style="margin-top: 20px;">
        <h3>آمار حرفه‌ای</h3>
        <table class="table">
            <tr><th>بازی</th><th>گل</th><th>پاس گل</th><th>دقایق</th><th>امتیاز میانگین</th></tr>
            <tr>
                <td><?= (int)($career_stats['career_apps'] ?? 0) ?></td>
                <td><?= (int)($career_stats['career_goals'] ?? 0) ?></td>
                <td><?= (int)($career_stats['career_assists'] ?? 0) ?></td>
                <td><?= (int)($career_stats['career_minutes'] ?? 0) ?></td>
                <td><?= number_format((float)($career_stats['career_rating'] ?? 0), 2) ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="/squad" class="btn">بازگشت به اسکواد / بازیکنان</a>
        <a href="/squad/tactics" class="btn">رفتن به تاکتیک / ترکیب</a>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
