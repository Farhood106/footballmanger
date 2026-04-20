<?php $title = 'ارسال درخواست مربیگری'; require __DIR__ . '/../layout/header.php'; ?>

<style>
.status-chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; }
.status-pending { background:#fff7ed; color:#9a3412; }
.status-approved { background:#ecfdf5; color:#166534; }
.status-rejected { background:#fef2f2; color:#991b1b; }
</style>

<div class="card">
    <h2>درخواست پست مربیگری</h2>
    <p>ابتدا شرح همکاری تعریف‌شده توسط مالک را ببین و سپس نسخه پیشنهادی خودت را ارسال کن.</p>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<?php if (empty($clubs)): ?>
<div class="card">
    <p>در حال حاضر باشگاهی برای درخواست مربیگری در دسترس نیست.</p>
</div>
<?php endif; ?>

<?php foreach (($clubs ?? []) as $club): ?>
<?php
$exp = $club['expectation'] ?? [];
$ownerExpectations = trim((string)($exp['expectations'] ?? ''));
$ownerDuties = trim((string)($exp['duties'] ?? ''));
$ownerCommitments = trim((string)($exp['commitments'] ?? ''));
?>
<div class="card" style="margin-bottom:24px;">
    <h3><?= htmlspecialchars((string)($club['name'] ?? 'باشگاه')) ?></h3>

    <div class="grid" style="margin:12px 0;">
        <div class="card" style="background:#f8fafc;">
            <h4>انتظارات مالک</h4>
            <div><?= nl2br(htmlspecialchars($ownerExpectations !== '' ? $ownerExpectations : 'هنوز موردی تعریف نشده است.')) ?></div>
        </div>
        <div class="card" style="background:#f8fafc;">
            <h4>وظایف مالک</h4>
            <div><?= nl2br(htmlspecialchars($ownerDuties !== '' ? $ownerDuties : 'هنوز موردی تعریف نشده است.')) ?></div>
        </div>
        <div class="card" style="background:#f8fafc;">
            <h4>تعهدات مالک</h4>
            <div><?= nl2br(htmlspecialchars($ownerCommitments !== '' ? $ownerCommitments : 'هنوز موردی تعریف نشده است.')) ?></div>
        </div>
    </div>

    <form method="post" action="/manager/apply/submit">
        <input type="hidden" name="club_id" value="<?= (int)($club['id'] ?? 0) ?>">

        <div class="form-group">
            <label>نسخه پیشنهادی انتظارات شما</label>
            <textarea name="proposed_expectations" rows="4"><?= htmlspecialchars($ownerExpectations) ?></textarea>
        </div>

        <div class="form-group">
            <label>نسخه پیشنهادی وظایف شما</label>
            <textarea name="proposed_duties" rows="4"><?= htmlspecialchars($ownerDuties) ?></textarea>
        </div>

        <div class="form-group">
            <label>نسخه پیشنهادی تعهدات شما</label>
            <textarea name="proposed_commitments" rows="4"><?= htmlspecialchars($ownerCommitments) ?></textarea>
        </div>

        <div class="form-group">
            <label>توضیحات و نامه درخواست مربیگری</label>
            <textarea name="cover_letter" rows="4" placeholder="در این قسمت شرایط همکاری، برنامه فنی و دلایل خود را توضیح بدهید."></textarea>
        </div>

        <button type="submit" class="btn btn-success">ارسال درخواست مربیگری</button>
    </form>
</div>
<?php endforeach; ?>


<div class="card">
    <h2>Contract Offers</h2>
    <table class="table">
        <thead><tr><th>Club</th><th>Status</th><th>Terms</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach (($offers ?? []) as $offer): ?>
            <tr>
                <td><?= htmlspecialchars((string)$offer['club_name']) ?></td>
                <td><?= htmlspecialchars((string)$offer['status']) ?></td>
                <td>
                    Salary/cycle: <?= (int)$offer['offered_salary_per_cycle'] ?><br>
                    Length: <?= (int)$offer['offered_contract_length_cycles'] ?> cycles<br>
                    Objective: <?= htmlspecialchars((string)($offer['club_objective'] ?? '-')) ?><br>
                    Bonus(Promotion/Title): <?= (int)($offer['bonus_promotion'] ?? 0) ?> / <?= (int)($offer['bonus_title'] ?? 0) ?>
                </td>
                <td style="min-width:320px;">
                    <?php if (($offer['status'] ?? '') === 'open'): ?>
                        <form method="post" action="/manager/offers/<?= (int)$offer['id'] ?>/accept" style="display:inline-block; margin-bottom:6px;">
                            <button class="btn btn-success" type="submit">Accept</button>
                        </form>
                        <form method="post" action="/manager/offers/<?= (int)$offer['id'] ?>/reject" style="display:inline-block; margin-bottom:6px;">
                            <button class="btn btn-danger" type="submit">Reject</button>
                        </form>
                        <form method="post" action="/manager/offers/<?= (int)$offer['id'] ?>/counter">
                            <div class="grid">
                                <div class="form-group"><input type="number" min="0" name="offered_salary_per_cycle" placeholder="Counter salary" required></div>
                                <div class="form-group"><input type="number" min="1" name="offered_contract_length_cycles" placeholder="Counter length" required></div>
                            </div>
                            <div class="grid">
                                <div class="form-group"><input type="text" name="club_objective" placeholder="Counter objective"></div>
                                <div class="form-group"><input type="number" min="0" name="bonus_promotion" placeholder="Promotion bonus"></div>
                                <div class="form-group"><input type="number" min="0" name="bonus_title" placeholder="Title bonus"></div>
                            </div>
                            <button class="btn" type="submit">Send Counter Offer</button>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($offers)): ?><tr><td colspan="4">No offers yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Active Contract Lifecycle</h2>
    <table class="table">
        <thead><tr><th>Club</th><th>Salary</th><th>End date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach (($active_contracts ?? []) as $contract): ?>
            <tr>
                <td><?= htmlspecialchars((string)($contract['club_name'] ?? '-')) ?></td>
                <td><?= number_format((int)($contract['salary'] ?? 0)) ?></td>
                <td><?= htmlspecialchars((string)($contract['end_date'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string)($contract['status'] ?? 'ACTIVE')) ?></td>
                <td style="min-width:280px;">
                    <?php if ((int)($contract['coach_user_id'] ?? 0) === (int)\Auth::id()): ?>
                        <form method="post" action="/manager/contracts/terminate">
                            <input type="hidden" name="club_id" value="<?= (int)$contract['club_id'] ?>">
                            <input type="hidden" name="termination_type" value="MUTUAL_TERMINATION">
                            <input type="number" min="0" name="compensation_amount" placeholder="Mutual compensation" style="width:160px;">
                            <textarea name="termination_reason" rows="2" placeholder="Mutual termination reason"></textarea>
                            <button class="btn btn-danger" type="submit">Request Mutual Termination</button>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($active_contracts)): ?><tr><td colspan="5">No active contract assigned.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>My Manager Applications</h2>
    <?php if (empty($history)): ?>
        <p>شما هنوز درخواست مربیگری ثبت نکرده‌اید.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>باشگاه</th>
                <th>تاریخ ارسال</th>
                <th>وضعیت</th>
                <th>جزئیات</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $application): ?>
                <?php
                $status = strtolower((string)($application['status'] ?? 'pending'));
                $statusClass = 'status-pending';
                $statusText = 'Pending';
                if ($status === 'approved') { $statusClass = 'status-approved'; $statusText = 'Approved'; }
                if ($status === 'rejected') { $statusClass = 'status-rejected'; $statusText = 'Rejected'; }
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$application['club_name']) ?></td>
                    <td><?= htmlspecialchars((string)$application['created_at']) ?></td>
                    <td><span class="status-chip <?= $statusClass ?>"><?= $statusText ?></span></td>
                    <td>
                        <?php if ($status === 'rejected'): ?>
                            <strong>Reason:</strong>
                            <?= nl2br(htmlspecialchars((string)($application['rejection_reason'] ?: 'No reason provided.'))) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
