<?php $title = 'بررسی درخواست‌های مربیگری'; require __DIR__ . '/../layout/header.php'; ?>
<div class="card">
    <h2>بررسی درخواست‌های مربیگری</h2>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
<table class="table">
<thead><tr><th>باشگاه</th><th>مربی</th><th>پیشنهاد</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach (($pending ?? []) as $a): ?>
<tr>
    <td><?= htmlspecialchars($a['club_name']) ?></td>
    <td><?= htmlspecialchars($a['coach_name']) ?></td>
    <td>
        <div><strong>انتظارات:</strong> <?= nl2br(htmlspecialchars($a['proposed_expectations'] ?? '')) ?></div>
        <div><strong>وظایف:</strong> <?= nl2br(htmlspecialchars($a['proposed_duties'] ?? '')) ?></div>
        <div><strong>تعهدات:</strong> <?= nl2br(htmlspecialchars($a['proposed_commitments'] ?? '')) ?></div>
    </td>
    <td style="min-width:360px;">
        <form method="post" action="/manager/applications/approve" style="display:inline-block; margin-bottom:8px;">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-success" type="submit">تایید مستقیم</button>
        </form>

        <form method="post" action="/manager/applications/offer" style="margin-bottom:8px;">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <label><strong>ارسال پیشنهاد قرارداد</strong></label>
            <div class="grid">
                <div class="form-group"><input type="number" name="offered_salary_per_cycle" min="0" placeholder="Salary / cycle" required></div>
                <div class="form-group"><input type="number" name="offered_contract_length_cycles" min="1" placeholder="Length (cycles)" required></div>
            </div>
            <div class="grid">
                <div class="form-group"><input type="text" name="club_objective" placeholder="Club objective (optional)"></div>
                <div class="form-group"><input type="number" name="bonus_promotion" min="0" placeholder="Promotion bonus"></div>
                <div class="form-group"><input type="number" name="bonus_title" min="0" placeholder="Title bonus"></div>
            </div>
            <button class="btn" type="submit">Send Offer</button>
        </form>

        <form method="post" action="/manager/applications/reject">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <label for="rejection_reason_<?= (int)$a['id'] ?>"><strong>Reason for rejection</strong></label>
            <textarea id="rejection_reason_<?= (int)$a['id'] ?>" name="rejection_reason" rows="3" required
                      placeholder="Explain briefly why this application is being rejected."></textarea>
            <button class="btn btn-danger" type="submit">رد</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pending)): ?><tr><td colspan="4">درخواستی موجود نیست.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="card">
    <h3>Open Negotiations</h3>
    <table class="table">
        <thead><tr><th>Club</th><th>Coach</th><th>Salary</th><th>Length</th><th>Objective</th><th>Bonuses</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach (($offers ?? []) as $offer): ?>
            <tr>
                <td><?= htmlspecialchars((string)$offer['club_name']) ?></td>
                <td><?= htmlspecialchars((string)$offer['coach_name']) ?></td>
                <td><?= (int)$offer['offered_salary_per_cycle'] ?></td>
                <td><?= (int)$offer['offered_contract_length_cycles'] ?> cycles</td>
                <td><?= htmlspecialchars((string)($offer['club_objective'] ?? '-')) ?></td>
                <td>P: <?= (int)($offer['bonus_promotion'] ?? 0) ?> / T: <?= (int)($offer['bonus_title'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string)$offer['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($offers)): ?><tr><td colspan="7">No open negotiations.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Active Manager Contracts</h3>
    <table class="table">
        <thead><tr><th>Club</th><th>Owner</th><th>Coach</th><th>Salary</th><th>End date</th><th>Termination</th></tr></thead>
        <tbody>
        <?php foreach (($active_contracts ?? []) as $contract): ?>
            <tr>
                <td><?= htmlspecialchars((string)$contract['club_name']) ?></td>
                <td><?= htmlspecialchars((string)($contract['owner_name'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string)($contract['coach_name'] ?? '-')) ?></td>
                <td><?= number_format((int)($contract['salary'] ?? 0)) ?></td>
                <td><?= htmlspecialchars((string)($contract['end_date'] ?? '-')) ?></td>
                <td style="min-width:360px;">
                    <form method="post" action="/manager/contracts/terminate">
                        <input type="hidden" name="club_id" value="<?= (int)$contract['club_id'] ?>">
                        <div class="grid">
                            <div class="form-group">
                                <select name="termination_type">
                                    <option value="OWNER_TERMINATION">Owner termination</option>
                                    <option value="MUTUAL_TERMINATION">Mutual termination</option>
                                    <?php if (\Auth::isAdmin()): ?>
                                        <option value="ADMIN_FORCED_TERMINATION">Admin forced termination</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group"><input type="number" min="0" name="compensation_amount" placeholder="Compensation amount"></div>
                        </div>
                        <div class="form-group">
                            <textarea name="termination_reason" rows="2" placeholder="Termination reason"></textarea>
                        </div>
                        <label><input type="checkbox" name="open_governance_case" value="1"> Open governance case for review</label><br>
                        <button class="btn btn-danger" type="submit">Terminate Contract</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($active_contracts)): ?><tr><td colspan="6">No active contracts.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
