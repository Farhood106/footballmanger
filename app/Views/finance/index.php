<?php $title = 'Club Finance'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>Club Finance Ledger</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars((string)$error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars((string)$success) ?></div><?php endif; ?>
</div>

<div class="card">
    <form method="get" action="/finance">
        <label>Club</label>
        <select name="club_id" onchange="this.form.submit()">
            <?php foreach (($clubs ?? []) as $club): ?>
                <option value="<?= (int)$club['id'] ?>" <?= ((int)$selected_club_id === (int)$club['id']) ? 'selected' : '' ?>><?= htmlspecialchars($club['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <h3>Owner Funding</h3>
    <form method="post" action="/finance/owner-funding">
        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
        <div class="grid">
            <div class="form-group"><input type="number" min="1" name="amount" placeholder="Funding amount" required></div>
            <div class="form-group"><input type="text" name="external_reference" placeholder="External/payment reference"></div>
            <div class="form-group"><input type="text" name="note" placeholder="Note"></div>
        </div>
        <button class="btn" type="submit">Post Owner Funding</button>
    </form>
</div>

<div class="card">
    <h3>Sponsors</h3>
    <form method="post" action="/finance/sponsors/add" style="margin-bottom:8px;">
        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
        <div class="grid">
            <div class="form-group"><input name="brand_name" placeholder="Brand name" required></div>
            <div class="form-group">
                <select name="tier">
                    <option value="main">main</option>
                    <option value="secondary">secondary</option>
                    <option value="minor" selected>minor</option>
                </select>
            </div>
            <div class="form-group"><input name="contact_link" placeholder="Contact link"></div>
            <div class="form-group"><input name="banner_url" placeholder="Banner/image URL"></div>
        </div>
        <div class="form-group"><input name="description" placeholder="Short description"></div>
        <button class="btn" type="submit">Add Sponsor</button>
    </form>

    <table class="table">
        <thead><tr><th>Brand</th><th>Tier</th><th>Status</th><th>Details</th><th>Manage</th><th>Income</th></tr></thead>
        <tbody>
        <?php foreach (($sponsors ?? []) as $s): ?>
            <tr>
                <td><?= htmlspecialchars((string)$s['brand_name']) ?></td>
                <td><?= htmlspecialchars((string)$s['tier']) ?></td>
                <td><?= !empty($s['is_active']) ? 'Active' : 'Inactive' ?></td>
                <td><?= htmlspecialchars((string)($s['description'] ?? '-')) ?></td>
                <td>
                    <form method="post" action="/finance/sponsors/update" style="margin-bottom:8px;">
                        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
                        <input type="hidden" name="sponsor_id" value="<?= (int)$s['id'] ?>">
                        <input name="brand_name" value="<?= htmlspecialchars((string)$s['brand_name']) ?>" required>
                        <select name="tier">
                            <option value="main" <?= (($s['tier'] ?? '') === 'main') ? 'selected' : '' ?>>main</option>
                            <option value="secondary" <?= (($s['tier'] ?? '') === 'secondary') ? 'selected' : '' ?>>secondary</option>
                            <option value="minor" <?= (($s['tier'] ?? '') === 'minor') ? 'selected' : '' ?>>minor</option>
                        </select>
                        <input name="contact_link" value="<?= htmlspecialchars((string)($s['contact_link'] ?? '')) ?>" placeholder="Contact link">
                        <input name="banner_url" value="<?= htmlspecialchars((string)($s['banner_url'] ?? '')) ?>" placeholder="Banner URL">
                        <input name="description" value="<?= htmlspecialchars((string)($s['description'] ?? '')) ?>" placeholder="Short description">
                        <label><input type="checkbox" name="is_active" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?>> Active</label>
                        <button class="btn" type="submit">Save</button>
                    </form>
                    <form method="post" action="/finance/sponsors/toggle">
                        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
                        <input type="hidden" name="sponsor_id" value="<?= (int)$s['id'] ?>">
                        <button class="btn" type="submit"><?= !empty($s['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/finance/sponsors/income">
                        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
                        <input type="hidden" name="sponsor_id" value="<?= (int)$s['id'] ?>">
                        <input type="number" min="1" name="amount" placeholder="Income" required>
                        <input type="text" name="note" placeholder="Note">
                        <button class="btn" type="submit" <?= empty($s['is_active']) ? 'disabled' : '' ?>>Post Sponsor Income</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($sponsors)): ?><tr><td colspan="6">No sponsors yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card">
    <h3>Manual Admin Adjustment</h3>
    <form method="post" action="/finance/admin-adjust">
        <input type="hidden" name="club_id" value="<?= (int)$selected_club_id ?>">
        <div class="grid">
            <div class="form-group"><input type="number" name="amount" placeholder="+/- amount" required></div>
            <div class="form-group"><input type="text" name="note" placeholder="Reason"></div>
        </div>
        <button class="btn" type="submit">Post Adjustment</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Ledger Entries</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Type</th><th>Amount</th><th>Description</th><th>Reference</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach (($ledger ?? []) as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars((string)$row['entry_type']) ?></td>
                <td><?= (int)$row['amount'] ?></td>
                <td><?= htmlspecialchars((string)($row['description'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)(($row['reference_type'] ?? '-') . ':' . ($row['reference_id'] ?? '-'))) ?></td>
                <td><?= htmlspecialchars((string)($row['created_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($ledger)): ?><tr><td colspan="6">No entries.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
