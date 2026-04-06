<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>تاکتیک تیم</h2>

    <form method="POST" action="/squad/tactics/save" data-ajax>
        <div class="grid">
            <div class="form-group">
                <label>فرماسیون</label>
                <select name="formation">
                    <?php
                    $formations = ['4-4-2','4-3-3','4-2-3-1','3-5-2','5-3-2','4-5-1','3-4-3'];
                    foreach ($formations as $f):
                    ?>
                        <option value="<?= $f ?>" <?= ($tactic['formation'] ?? '') == $f ? 'selected' : '' ?>>
                            <?= $f ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>ذهنیت</label>
                <select name="mentality">
                    <?php
                    $mentalities = [
                        'ULTRA_ATTACK' => 'حمله کامل',
                        'ATTACK'       => 'تهاجمی',
                        'BALANCED'     => 'متعادل',
                        'DEFEND'       => 'دفاعی',
                        'ULTRA_DEFEND' => 'دفاع کامل',
                    ];
                    foreach ($mentalities as $key => $label):
                    ?>
                        <option value="<?= $key ?>" <?= ($tactic['mentality'] ?? '') == $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>فشار</label>
                <select name="pressing">
                    <option value="HIGH"   <?= ($tactic['pressing'] ?? '') == 'HIGH'   ? 'selected' : '' ?>>بالا</option>
                    <option value="MEDIUM" <?= ($tactic['pressing'] ?? '') == 'MEDIUM' ? 'selected' : '' ?>>متوسط</option>
                    <option value="LOW"    <?= ($tactic['pressing'] ?? '') == 'LOW'    ? 'selected' : '' ?>>پایین</option>
                </select>
            </div>

            <div class="form-group">
                <label>سبک پاس</label>
                <select name="passing_style">
                    <option value="SHORT" <?= ($tactic['passing_style'] ?? '') == 'SHORT' ? 'selected' : '' ?>>کوتاه</option>
                    <option value="MIXED" <?= ($tactic['passing_style'] ?? '') == 'MIXED' ? 'selected' : '' ?>>ترکیبی</option>
                    <option value="LONG"  <?= ($tactic['passing_style'] ?? '') == 'LONG'  ? 'selected' : '' ?>>بلند</option>
                </select>
            </div>
        </div>

        <h3 style="margin: 20px 0 10px;">ترکیب اصلی (۱۱ نفر)</h3>

        <table class="table">
            <tr>
                <th>بازیکن</th>
                <th>پست</th>
                <th>قدرت</th>
                <th>در ترکیب</th>
            </tr>

            <?php foreach ($squad as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['position']) ?></td>
                <td><?= $p['overall'] ?></td>
                <td>
                    <input type="checkbox"
                           name="lineup[]"
                           value="<?= $p['id'] ?>"
                           <?= in_array($p['id'], $lineup ?? []) ? 'checked' : '' ?>>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <button type="submit" class="btn btn-success" style="margin-top: 15px;">ذخیره تاکتیک</button>
    </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
