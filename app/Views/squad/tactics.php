<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="card">
    <h2>تاکتیک تیم و ترکیب</h2>

    <form method="POST" action="/squad/tactics/save" data-ajax>
        <input type="hidden" name="phase_key" value="<?= htmlspecialchars((string)($phase_key ?? 'MATCH_1')) ?>">
        <div class="grid">
            <div class="form-group">
                <label>فرماسیون</label>
                <select name="formation">
                    <?php foreach (($formations ?? []) as $key => $label): ?>
                        <option value="<?= htmlspecialchars((string)$key) ?>" <?= (($selected_formation ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$key) ?> - <?= htmlspecialchars((string)$label) ?>
                        </option>
                    <?php endforeach;?>
                </select>
                <small>برای تغییر فرمیشن و به‌روزرسانی اسلات‌ها، ذخیره کنید.</small>
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

        <h3 style="margin: 20px 0 10px;">Lineup Builder (۱۱ اسلات)</h3>

        <table class="table">
            <tr>
                <th>اسلات</th>
                <th>بازیکن انتخابی</th>
                <th>رتبه‌ی موقعیتی</th>
                <th>بهترین گزینه‌ها</th>
            </tr>

            <?php foreach (($lineup_board ?? []) as $slot): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars((string)$slot['position_slot']) ?></strong>
                    <div style="font-size:12px; color:#666;"><?= htmlspecialchars((string)$slot['slot_label']) ?></div>
                </td>
                <td>
                    <select name="lineup[<?= htmlspecialchars((string)$slot['slot_key']) ?>]" required>
                        <option value="">-- انتخاب بازیکن --</option>
                        <?php foreach (($slot['candidates'] ?? []) as $candidate): ?>
                            <?php
                                $label = trim((string)$candidate['full_name']) !== '' ? (string)$candidate['full_name'] : ('Player #' . (int)$candidate['id']);
                                $isSelected = (int)($slot['selected_player_id'] ?? 0) === (int)$candidate['id'];
                                $oos = !empty($candidate['out_of_position']) ? ' (OOP)' : '';
                            ?>
                            <option value="<?= (int)$candidate['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?> | <?= htmlspecialchars((string)$candidate['position']) ?> | OVR <?= (int)$candidate['overall'] ?> | Rate <?= (int)$candidate['position_rating'] . $oos ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <?php
                        $selectedRating = null;
                        foreach (($slot['candidates'] ?? []) as $candidate) {
                            if ((int)$candidate['id'] === (int)($slot['selected_player_id'] ?? 0)) {
                                $selectedRating = $candidate;
                                break;
                            }
                        }
                    ?>
                    <?php if ($selectedRating): ?>
                        <strong><?= (int)$selectedRating['position_rating'] ?></strong>
                        <?php if (!empty($selectedRating['out_of_position'])): ?>
                            <span style="color:#b91c1c; font-size:12px;">Out of Position</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#666;">انتخاب نشده</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach (($slot['recommended'] ?? []) as $rec): ?>
                        <div style="font-size:12px;">
                            <?= htmlspecialchars((string)$rec['full_name']) ?>
                            <span style="color:#666;">(<?= htmlspecialchars((string)$rec['position']) ?> / <?= (int)$rec['position_rating'] ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <button type="submit" class="btn btn-success" style="margin-top: 15px;">ذخیره تاکتیک</button>
    </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
