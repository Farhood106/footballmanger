<?php $title = 'Competition Operations'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>Admin Competition / Division / Season Management</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
    <h3>Create Competition</h3>
    <form method="post" action="/admin/competitions/create">
        <div class="grid">
            <div class="form-group"><label>Name</label><input name="name" required></div>
            <div class="form-group"><label>Code</label><input name="code"></div>
            <div class="form-group"><label>Type</label>
                <select name="type">
                    <?php foreach (['LEAGUE','CUP','SUPER_CUP','CHAMPIONS_LEAGUE','FRIENDLY'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Country</label><input name="country"></div>
            <div class="form-group"><label>Level/Division</label><input name="level" type="number" min="1" value="1"></div>
            <div class="form-group"><label>Teams Count</label><input name="teams_count" type="number" min="2" value="20" required></div>
            <div class="form-group"><label>Promotion Slots</label><input name="promotion_slots" type="number" min="0" value="0"></div>
            <div class="form-group"><label>Relegation Slots</label><input name="relegation_slots" type="number" min="0" value="0"></div>
            <div class="form-group"><label>Parent Competition (optional)</label>
                <select name="parent_competition_id"><option value="">None</option>
                    <?php foreach (($competitions ?? []) as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
        </div>
        <button class="btn btn-success" type="submit">Create Competition</button>
    </form>
</div>

<?php foreach (($competitions ?? []) as $c): ?>
<div class="card" style="margin-bottom:14px;">
    <h3><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['type']) ?>)</h3>
    <p>Level: <?= (int)$c['level'] ?> | Teams: <?= (int)$c['teams_count'] ?> | Active: <?= (int)$c['is_active'] ? 'Yes' : 'No' ?></p>

    <form method="post" action="/admin/competitions/<?= (int)$c['id'] ?>/update">
        <div class="grid">
            <div class="form-group"><input name="name" value="<?= htmlspecialchars($c['name']) ?>" required></div>
            <div class="form-group"><input name="code" value="<?= htmlspecialchars((string)($c['code'] ?? '')) ?>"></div>
            <div class="form-group"><input name="country" value="<?= htmlspecialchars((string)($c['country'] ?? '')) ?>"></div>
            <div class="form-group"><input name="level" type="number" min="1" value="<?= (int)$c['level'] ?>"></div>
            <div class="form-group"><input name="teams_count" type="number" min="2" value="<?= (int)$c['teams_count'] ?>"></div>
            <div class="form-group"><input name="promotion_slots" type="number" min="0" value="<?= (int)($c['promotion_slots'] ?? 0) ?>"></div>
            <div class="form-group"><input name="relegation_slots" type="number" min="0" value="<?= (int)($c['relegation_slots'] ?? 0) ?>"></div>
            <div class="form-group"><label><input type="checkbox" name="is_active" value="1" <?= (int)$c['is_active'] ? 'checked' : '' ?>> Active</label></div>
        </div>
        <input type="hidden" name="type" value="<?= htmlspecialchars($c['type']) ?>">
        <input type="hidden" name="parent_competition_id" value="<?= (int)($c['parent_competition_id'] ?? 0) ?>">
        <button class="btn" type="submit">Save Competition</button>
    </form>

    <form method="post" action="/admin/competitions/<?= (int)$c['id'] ?>/toggle" style="margin-top:6px;">
        <input type="hidden" name="is_active" value="<?= (int)$c['is_active'] ? 0 : 1 ?>">
        <button class="btn"><?= (int)$c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
    </form>

    <h4>Seasons</h4>
    <table class="table">
        <thead><tr><th>Name</th><th>Dates</th><th>Status</th><th>Participants</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach (($c['seasons'] ?? []) as $s): ?>
            <?php $participants = $s['participants'] ?? []; ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['start_date']) ?> → <?= htmlspecialchars($s['end_date']) ?></td>
                <td><?= htmlspecialchars($s['status']) ?></td>
                <td><?= count($participants) ?> / <?= (int)$c['teams_count'] ?></td>
                <td>
                    <form method="post" action="/admin/seasons/<?= (int)$s['id'] ?>/start" style="display:inline-block;"><button class="btn">Start</button></form>
                    <form method="post" action="/admin/seasons/<?= (int)$s['id'] ?>/end" style="display:inline-block;"><button class="btn">End</button></form>
                    <form method="post" action="/admin/seasons/<?= (int)$s['id'] ?>/fixtures/generate" style="display:inline-block;"><button class="btn btn-success">Generate Fixtures</button></form>
                    <a class="btn" href="/admin/seasons/<?= (int)$s['id'] ?>/fixtures">View Fixtures</a>
                </td>
            </tr>
            <tr>
                <td colspan="5">
                    <strong>Participants:</strong>
                    <table class="table" style="margin-top:8px;">
                        <thead><tr><th>Club</th><th>Entry Type</th><th>Remove</th></tr></thead>
                        <tbody>
                        <?php foreach ($participants as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['club_name']) ?></td>
                                <td><?= htmlspecialchars($p['entry_type'] ?? 'direct') ?></td>
                                <td>
                                    <form method="post" action="/admin/seasons/<?= (int)$s['id'] ?>/participants/<?= (int)$p['club_id'] ?>/remove" style="display:inline-block;">
                                        <button class="btn" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($participants)): ?><tr><td colspan="3">No participants assigned.</td></tr><?php endif; ?>
                        </tbody>
                    </table>

                    <form method="post" action="/admin/seasons/<?= (int)$s['id'] ?>/participants/add">
                        <div class="grid">
                            <div class="form-group">
                                <label>Club</label>
                                <select name="club_id" required>
                                    <option value="">Select club</option>
                                    <?php foreach (($clubs ?? []) as $club): ?>
                                        <option value="<?= (int)$club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Entry Type</label>
                                <select name="entry_type">
                                    <?php foreach (($entry_types ?? []) as $entryType): ?>
                                        <option value="<?= htmlspecialchars($entryType) ?>"><?= htmlspecialchars($entryType) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button class="btn" type="submit">Add Participant</button>
                            </div>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($c['seasons'])): ?><tr><td colspan="5">No seasons.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <form method="post" action="/admin/seasons/create">
        <input type="hidden" name="competition_id" value="<?= (int)$c['id'] ?>">
        <div class="grid">
            <div class="form-group"><input name="name" placeholder="Season name (e.g. 2026/27)" required></div>
            <div class="form-group"><input type="date" name="start_date" required></div>
            <div class="form-group"><input type="date" name="end_date" required></div>
        </div>
        <button class="btn btn-success" type="submit">Create Season</button>
    </form>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
