<?php $title = 'Seed Import'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>Seed Import (Admin)</h2>
    <p>Import datasets from <code>database/seed_sets</code>. Only pre-approved folders are allowed.</p>
</div>

<div class="card">
    <form method="post" action="/admin/seed/import">
        <label for="dataset"><strong>Dataset</strong></label><br>
        <select id="dataset" name="dataset" required>
            <?php foreach (($seed_sets ?? []) as $set): ?>
                <option value="<?= htmlspecialchars((string)$set['key']) ?>" <?= empty($set['is_valid']) ? 'disabled' : '' ?>>
                    <?= htmlspecialchars((string)$set['key']) ?><?= empty($set['is_valid']) ? ' (invalid)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="margin-top:10px;">
            <label>
                <input type="checkbox" name="dry_run" value="1" checked>
                Dry-run (validate and preview only)
            </label>
        </div>

        <div style="margin-top:10px;">
            <button class="btn btn-success" type="submit">Run Import</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Available seed sets</h3>
    <?php if (empty($seed_sets ?? [])): ?>
        <p>No seed sets found.</p>
    <?php else: ?>
        <table class="table">
            <tr><th>Key</th><th>Status</th><th>Notes</th></tr>
            <?php foreach (($seed_sets ?? []) as $set): ?>
                <tr>
                    <td><code><?= htmlspecialchars((string)$set['key']) ?></code></td>
                    <td><?= !empty($set['is_valid']) ? 'Ready' : 'Invalid' ?></td>
                    <td>
                        <?php if (!empty($set['missing'])): ?>
                            Missing: <?= htmlspecialchars(implode(', ', (array)$set['missing'])) ?>
                        <?php else: ?>
                            required files present
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($import_report)): ?>
<div class="card">
    <h3>Last import result</h3>
    <pre style="white-space:pre-wrap;"><?= htmlspecialchars(json_encode($import_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
