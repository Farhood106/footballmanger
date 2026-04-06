<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2>جدول لیگ — <?= htmlspecialchars($competition['name']) ?></h2>
        <a href="/competition/<?= $competition['id'] ?>/fixtures" class="btn">برنامه بازی‌ها</a>
    </div>

    <table class="table">
        <tr>
            <th>#</th>
            <th>باشگاه</th>
            <th>بازی</th>
            <th>برد</th>
            <th>مساوی</th>
            <th>باخت</th>
            <th>گل زده</th>
            <th>گل خورده</th>
            <th>تفاضل</th>
            <th>امتیاز</th>
        </tr>

        <?php foreach ($standings as $i => $row): ?>
        <tr style="<?= $row['club_id'] == $userClubId ? 'background:#fff3cd; font-weight:bold;' : '' ?>">
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($row['club_name']) ?></td>
            <td><?= $row['played'] ?></td>
            <td><?= $row['won'] ?></td>
            <td><?= $row['drawn'] ?></td>
            <td><?= $row['lost'] ?></td>
            <td><?= $row['goals_for'] ?></td>
            <td><?= $row['goals_against'] ?></td>
            <td><?= $row['goals_for'] - $row['goals_against'] ?></td>
            <td style="font-size:18px; color:#2c3e50;"><?= $row['points'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
