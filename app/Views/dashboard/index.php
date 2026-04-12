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
    <h2>Club Sponsors</h2>
    <?php if (empty($active_sponsors)): ?>
        <p>No active sponsors yet.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($active_sponsors as $sponsor): ?>
                <div class="stat-box">
                    <h3><?= htmlspecialchars((string)$sponsor['brand_name']) ?></h3>
                    <p>
                        Tier:
                        <strong>
                            <?= htmlspecialchars(strtoupper((string)$sponsor['tier'])) ?>
                        </strong>
                    </p>
                    <?php if (!empty($sponsor['description'])): ?>
                        <p><?= htmlspecialchars((string)$sponsor['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sponsor['contact_link'])): ?>
                        <p><a class="btn" href="<?= htmlspecialchars((string)$sponsor['contact_link']) ?>" target="_blank" rel="noopener">Visit Sponsor</a></p>
                    <?php endif; ?>
                    <?php if (!empty($sponsor['banner_url'])): ?>
                        <img src="<?= htmlspecialchars((string)$sponsor['banner_url']) ?>" alt="<?= htmlspecialchars((string)$sponsor['brand_name']) ?>" style="max-width:220px; width:100%; border-radius:8px;">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
