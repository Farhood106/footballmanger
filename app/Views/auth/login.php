<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card" style="max-width: 400px; margin: 100px auto;">
    <h2 style="text-align: center; margin-bottom: 20px;">ورود به سیستم</h2>

    <?php if (!empty($error)): ?>
        <div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;margin-bottom:15px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login">
        <div class="form-group">
            <label>ایمیل</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>رمز عبور</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="btn" style="width: 100%;">ورود</button>
    </form>

    <p style="text-align: center; margin-top: 15px;">
        حساب کاربری ندارید؟ <a href="/register">ثبت‌نام کنید</a>
    </p>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
