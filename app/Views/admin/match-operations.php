<?php $title = 'Admin Match Operations'; require __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>Admin Match Operations</h2>
    <p>Operational tools for safe match repair/rerun/reset and daily cycle-state inspection.</p>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
    <h3>Match Filters</h3>
    <form method="get" action="/admin/match-operations">
        <div class="grid">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (($statuses ?? []) as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Competition</label>
                <select name="competition_id">
                    <option value="">All</option>
                    <?php foreach (($competitions ?? []) as $competition): ?>
                        <option value="<?= (int)$competition['id'] ?>" <?= ((int)($filters['competition_id'] ?? 0) === (int)$competition['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($competition['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Season</label>
                <select name="season_id">
                    <option value="">All</option>
                    <?php foreach (($seasons ?? []) as $season): ?>
                        <option value="<?= (int)$season['id'] ?>" <?= ((int)($filters['season_id'] ?? 0) === (int)$season['id']) ? 'selected' : '' ?>>
                            #<?= (int)$season['id'] ?> - <?= htmlspecialchars($season['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Club</label>
                <select name="club_id">
                    <option value="">All</option>
                    <?php foreach (($clubs ?? []) as $club): ?>
                        <option value="<?= (int)$club['id'] ?>" <?= ((int)($filters['club_id'] ?? 0) === (int)$club['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($club['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn" type="submit">Apply Filters</button>
        <a class="btn" href="/admin/match-operations">Clear</a>
    </form>
</div>

<div class="card">
    <h3>Matches</h3>
    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Scheduled</th>
            <th>Home</th>
            <th>Away</th>
            <th>Status</th>
            <th>Score</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($matches ?? []) as $match): ?>
            <tr>
                <td><?= (int)$match['id'] ?></td>
                <td><?= htmlspecialchars((string)$match['scheduled_at']) ?></td>
                <td><?= htmlspecialchars((string)$match['home_club_name']) ?></td>
                <td><?= htmlspecialchars((string)$match['away_club_name']) ?></td>
                <td><?= htmlspecialchars((string)$match['status']) ?></td>
                <td>
                    <?php if ($match['home_score'] !== null && $match['away_score'] !== null): ?>
                        <?= (int)$match['home_score'] ?> - <?= (int)$match['away_score'] ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (AdminMatchOperationsService::canRepairLiveMatch($match)): ?>
                        <form method="post" action="/admin/match-operations/<?= (int)$match['id'] ?>/repair" style="display:inline-block;">
                            <button class="btn" type="submit">Repair LIVE</button>
                        </form>
                    <?php endif; ?>

                    <?php if (AdminMatchOperationsService::canRerunMatch($match, false)): ?>
                        <form method="post" action="/admin/match-operations/<?= (int)$match['id'] ?>/rerun" style="display:inline-block;">
                            <button class="btn btn-success" type="submit">Rerun</button>
                        </form>
                    <?php elseif (AdminMatchOperationsService::canRerunMatch($match, true)): ?>
                        <form method="post" action="/admin/match-operations/<?= (int)$match['id'] ?>/rerun" style="display:inline-block;">
                            <input type="hidden" name="override" value="1">
                            <button class="btn btn-success" type="submit">Rerun (Override)</button>
                        </form>
                    <?php endif; ?>

                    <?php if (($match['status'] ?? '') !== 'FINISHED'): ?>
                        <form method="post" action="/admin/match-operations/<?= (int)$match['id'] ?>/reset-lineup" style="display:inline-block;">
                            <button class="btn" type="submit">Reset lineup lock</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($matches)): ?><tr><td colspan="7">No matches found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Club Daily Cycle States</h3>

    <form method="get" action="/admin/match-operations" style="margin-bottom: 10px;">
        <div class="grid">
            <div class="form-group">
                <label>Cycle Date</label>
                <input type="date" name="cycle_date" value="<?= htmlspecialchars((string)($cycle_date ?? date('Y-m-d'))) ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn" type="submit">Load States</button>
            </div>
        </div>
    </form>

    <table class="table">
        <thead>
        <tr>
            <th>Club</th>
            <th>Date</th>
            <th>Matches Today</th>
            <th>Profile</th>
            <th>Current Phase</th>
            <th>Updated</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($cycle_states ?? []) as $state): ?>
            <tr>
                <td><?= htmlspecialchars((string)$state['club_name']) ?></td>
                <td><?= htmlspecialchars((string)$state['cycle_date']) ?></td>
                <td><?= (int)$state['matches_today'] ?></td>
                <td><?= htmlspecialchars((string)$state['profile_key']) ?></td>
                <td><?= htmlspecialchars((string)$state['current_phase_key']) ?></td>
                <td><?= htmlspecialchars((string)($state['updated_at'] ?? '-')) ?></td>
                <td>
                    <form method="post" action="/admin/match-operations/cycle/sync" style="display:inline-block;">
                        <input type="hidden" name="club_id" value="<?= (int)$state['club_id'] ?>">
                        <input type="hidden" name="cycle_date" value="<?= htmlspecialchars((string)$state['cycle_date']) ?>">
                        <button class="btn" type="submit">Sync Cycle State</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($cycle_states)): ?><tr><td colspan="7">No cycle states for selected date.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
