<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>فهرست بازیکنان</h2>

    <table class="table">
        <tr>
            <th>نام</th>
            <th>سن</th>
            <th>پست</th>
            <th>قدرت</th>
            <th>نقش</th>
            <th>آخرین بازی</th>
            <th>هشدار</th>
            <th>جزئیات</th>
        </tr>

        <?php foreach ($squad as $p): ?>
        <tr>
            <td><?= htmlspecialchars((string)($p['display_name'] ?? $p['full_name'] ?? '')) ?></td>
            <td><?= (int)(date('Y') - (int)date('Y', strtotime((string)($p['birth_date'] ?? '2000-01-01')))) ?></td>
            <td><?= htmlspecialchars($p['position']) ?></td>
            <td><?= $p['overall'] ?></td>
            <td>
                <div><?= htmlspecialchars((string)($p['role_label'] ?? 'Rotation')) ?></div>
                <form method="post" action="/squad/role/save" style="margin-top:6px;">
                    <input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>">
                    <select name="squad_role" style="font-size:12px;">
                        <?php foreach (($role_labels ?? []) as $roleCode => $roleText): ?>
                            <option value="<?= htmlspecialchars((string)$roleCode) ?>" <?= (($p['squad_role'] ?? 'ROTATION') === $roleCode) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$roleText) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn" style="padding:4px 8px; font-size:12px;">ذخیره</button>
                </form>
            </td>
            <td>
                <?php if (!empty($p['last_played_at'])): ?>
                    <div><?= htmlspecialchars((string)$p['last_played_at']) ?></div>
                    <small><?= (int)($p['recent_minutes'] ?? 0) ?> دقیقه</small>
                <?php else: ?>
                    <span>بدون بازی اخیر</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($p['inactivity_warning'])): ?>
                    <span style="color:#c0392b; font-weight:700;">کم‌بازی</span><br>
                <?php endif; ?>
                <?php if (!empty($p['overused_warning'])): ?>
                    <span style="color:#d35400; font-weight:700;">بار بالا</span>
                <?php endif; ?>
                <?php if (empty($p['inactivity_warning']) && empty($p['overused_warning'])): ?>
                    <span style="color:#27ae60;">نرمال</span>
                <?php endif; ?>
            </td>
            <td>
                <a class="btn" href="/squad/player/<?= $p['id'] ?>">مشاهده</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>بازیکنان مصدوم</h2>

    <?php if (empty($injured)): ?>
        <p>بازیکن مصدومی ندارید.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>نام</th>
                <th>مدت</th>
            </tr>

            <?php foreach ($injured as $p): ?>
            <tr>
                <td><?= htmlspecialchars((string)($p['full_name'] ?? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')))) ?></td>
                <td><?= $p['injury_days'] ?> روز</td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
