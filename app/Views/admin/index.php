<?php $title = 'پنل مدیریت'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>پنل مدیریت سایت</h2>
    <p>از اینجا می‌تونی باشگاه و بازیکن جدید تزریق کنی.</p>
</div>

<div class="grid">
    <div class="stat-box"><h3><?= $stats['users'] ?></h3><p>کاربران</p></div>
    <div class="stat-box"><h3><?= $stats['clubs'] ?></h3><p>باشگاه‌ها</p></div>
    <div class="stat-box"><h3><?= $stats['players'] ?></h3><p>بازیکنان</p></div>
</div>

<div class="card">
    <a class="btn" href="/admin/clubs/create">➕ ایجاد باشگاه</a>
    <a class="btn btn-success" href="/admin/players/create">➕ ایجاد بازیکن</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
