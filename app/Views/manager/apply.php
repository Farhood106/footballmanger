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
