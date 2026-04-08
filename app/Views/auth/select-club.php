<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>انتخاب باشگاه</h2>
    <p>لطفاً یک باشگاه برای مدیریت انتخاب کنید:</p>
    
    <div class="grid" style="margin-top: 30px;">
        <?php foreach ($clubs as $club): ?>
        <?php
            $leagueLabel = $club['league'] ?? ($club['country'] ?? 'نامشخص');
            $budgetValue = $club['budget'] ?? ($club['balance'] ?? 0);
            $reputation = (int)($club['reputation'] ?? 50);
        ?>
        <div class="card">
            <h3><?= htmlspecialchars((string)($club['name'] ?? 'باشگاه بدون نام')) ?></h3>
            <p><strong>لیگ/کشور:</strong> <?= htmlspecialchars((string)$leagueLabel) ?></p>
            <p><strong>بودجه:</strong> $<?= number_format((float)$budgetValue) ?></p>
            <p><strong>شهرت:</strong> <?= $reputation ?>/100</p>
            
            <form method="POST" action="/club/assign" data-ajax style="margin-top: 15px;">
                <input type="hidden" name="club_id" value="<?= (int)($club['id'] ?? 0) ?>">
                <button type="submit" class="btn btn-success" style="width: 100%;">انتخاب این باشگاه</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
