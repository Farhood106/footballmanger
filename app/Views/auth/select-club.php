<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>انتخاب باشگاه</h2>
    <p>لطفاً یک باشگاه برای مدیریت انتخاب کنید:</p>
    
    <div class="grid" style="margin-top: 30px;">
        <?php foreach ($clubs as $club): ?>
        <div class="card">
            <h3><?= htmlspecialchars($club['name']) ?></h3>
            <p><strong>لیگ:</strong> <?= htmlspecialchars($club['league']) ?></p>
            <p><strong>بودجه:</strong> $<?= number_format($club['budget']) ?></p>
            <p><strong>شهرت:</strong> <?= $club['reputation'] ?>/100</p>
            
            <form method="POST" action="/club/assign" data-ajax style="margin-top: 15px;">
                <input type="hidden" name="club_id" value="<?= $club['id'] ?>">
                <button type="submit" class="btn btn-success" style="width: 100%;">انتخاب این باشگاه</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
