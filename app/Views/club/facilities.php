<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>Club Facilities</h2>
    <p>Upgrade, downgrade, and maintain core club infrastructure.</p>
</div>

<?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars((string)$success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars((string)$error) ?></div><?php endif; ?>

<div class="card">
    <form method="get" action="/club/facilities">
        <label>Club</label>
        <select name="club_id">
            <?php foreach (($clubs ?? []) as $club): ?>
                <option value="<?= (int)$club['id'] ?>" <?= (int)$club['id'] === (int)$selected_club_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$club['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Load</button>
    </form>

    <?php if (!empty($selected_club)): ?>
        <div style="margin-top:10px;">
            <strong><?= htmlspecialchars((string)$selected_club['name']) ?></strong>
            — Balance: $<?= number_format((int)($selected_club['balance'] ?? 0)) ?>
            — Reputation: <?= (int)($selected_club['reputation'] ?? 0) ?>/100
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Infrastructure Slots</h3>
    <?php if (empty($facilities)): ?>
        <p>No facilities available for this club.</p>
    <?php else: ?>
        <table class="table">
            <tr>
                <th>Facility</th>
                <th>Level</th>
                <th>Upgrade Cost</th>
                <th>Downgrade Refund</th>
                <th>Daily Maintenance</th>
                <th>Image Ref</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($facilities as $facility): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$facility['label']) ?></td>
                    <td><?= (int)$facility['level'] ?> / <?= (int)$facility['max_level'] ?></td>
                    <td>$<?= number_format((int)$facility['next_upgrade_cost']) ?></td>
                    <td>$<?= number_format((int)$facility['downgrade_refund']) ?></td>
                    <td>$<?= number_format((int)$facility['daily_maintenance_cost']) ?></td>
                    <td><small><?= htmlspecialchars((string)$facility['image_ref']) ?></small></td>
                    <td>
                        <form method="post" action="/club/facilities/upgrade" style="display:inline-block;">
                            <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
                            <input type="hidden" name="facility_type" value="<?= htmlspecialchars((string)$facility['facility_type']) ?>">
                            <button class="btn btn-success" type="submit" <?= (int)$facility['level'] >= (int)$facility['max_level'] ? 'disabled' : '' ?>>Upgrade</button>
                        </form>
                        <form method="post" action="/club/facilities/downgrade" style="display:inline-block;">
                            <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
                            <input type="hidden" name="facility_type" value="<?= htmlspecialchars((string)$facility['facility_type']) ?>">
                            <button class="btn" type="submit" <?= (int)$facility['level'] <= (int)$facility['min_level'] ? 'disabled' : '' ?>>Downgrade</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
