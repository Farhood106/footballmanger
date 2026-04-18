<?php require_once __DIR__ . '/../layout/header.php'; ?>

<style>
    .tactics-pitch {
        position: relative;
        width: 100%;
        min-height: 640px;
        border-radius: 16px;
        border: 3px solid #1f5f2c;
        background: linear-gradient(180deg, #3fa95a 0%, #2f8d47 100%);
        overflow: hidden;
        margin-top: 14px;
    }
    .tactics-pitch::before,
    .tactics-pitch::after {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        border: 2px solid rgba(255,255,255,0.55);
    }
    .tactics-pitch::before {
        top: 50%;
        width: 92%;
        transform: translate(-50%, -50%);
    }
    .tactics-pitch::after {
        top: 50%;
        width: 140px;
        height: 140px;
        border-radius: 50%;
        transform: translate(-50%, -50%);
    }
    .pitch-slot {
        position: absolute;
        transform: translate(-50%, -50%);
        width: min(230px, 31vw);
        background: rgba(10, 20, 12, 0.78);
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 10px;
        color: #fff;
        padding: 8px;
        font-size: 12px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.25);
    }
    .pitch-slot select { width: 100%; font-size: 12px; margin-top: 6px; }
    .slot-head { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
    .slot-pos { font-weight: 700; color: #dcfce7; }
    .slot-rating { font-weight: 700; }
    .slot-name { margin-top: 5px; font-weight: 700; }
    .slot-rec { margin-top: 4px; color: #bbf7d0; line-height: 1.4; }
    .slot-oop { color: #fecaca; font-size: 11px; }

    @media (max-width: 820px) {
        .tactics-pitch { min-height: 960px; }
        .pitch-slot { width: min(250px, 86vw); }
    }
</style>

<div class="card">
    <h2>تاکتیک / ترکیب / فرمیشن</h2>
    <p style="margin-top:6px; color:#555;">این صفحه برای چیدمان گرافیکی فرمیشن، انتخاب بازیکن‌های هر اسلات و تعیین مسئولیت‌های کلیدی تیم است.</p>
    <p style="margin-top:4px; color:#0f5132; font-weight:700;">فرمیشن فعال: <?= htmlspecialchars((string)($selected_formation ?? '4-3-3')) ?></p>
    <p style="margin-top:4px; color:#555;">پوشش پست‌ها: GK, LB/RB, CB, LWB/RWB, DM(CDM), CM, AM(CAM), LM/RM, LW/RW, ST/CF</p>
    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
        <a class="btn" href="/squad">بازگشت به اسکواد / بازیکنان</a>
        <a class="btn" href="/squad/tactics">تاکتیک / ترکیب</a>
    </div>

    <form method="POST" action="/squad/tactics/save" data-ajax>
        <input type="hidden" name="phase_key" value="<?= htmlspecialchars((string)($phase_key ?? 'MATCH_1')) ?>">
        <div class="grid" style="margin-top:12px;">
            <div class="form-group">
                <label>فرماسیون</label>
                <select name="formation" id="formation-select">
                    <?php foreach (($formations ?? []) as $key => $label): ?>
                        <option value="<?= htmlspecialchars((string)$key) ?>" <?= (($selected_formation ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$key) ?> - <?= htmlspecialchars((string)$label) ?>
                        </option>
                    <?php endforeach;?>
                </select>
                <small>برای به‌روزرسانی چیدمان زمین بر اساس فرمیشن جدید، ذخیره کنید.</small>
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
        </div>

        <h3 style="margin: 22px 0 8px;">بورد گرافیکی تاکتیک</h3>
        <div class="tactics-pitch">
            <?php foreach (($lineup_board ?? []) as $slot): ?>
                <?php $selected = $slot['selected_candidate'] ?? null; ?>
                <div class="pitch-slot" style="left:<?= (int)$slot['board_x'] ?>%; top:<?= (int)$slot['board_y'] ?>%;">
                    <div class="slot-head">
                        <span class="slot-pos"><?= htmlspecialchars((string)$slot['position_slot']) ?></span>
                        <span><?= htmlspecialchars((string)$slot['slot_label']) ?></span>
                    </div>
                    <div class="slot-name">
                        <?= $selected ? htmlspecialchars((string)$selected['full_name']) : 'بازیکن انتخاب نشده' ?>
                    </div>
                    <div class="slot-rating">
                        ریتینگ: <?= $selected ? (int)$selected['position_rating'] : '-' ?>
                        <?php if (!empty($selected['out_of_position'])): ?>
                            <span class="slot-oop">(خارج از پست)</span>
                        <?php endif; ?>
                    </div>
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
                    <?php if (!empty($slot['recommended'])): ?>
                        <div class="slot-rec">
                            پیشنهادی:
                            <?php foreach (array_slice($slot['recommended'], 0, 2) as $idx => $rec): ?>
                                <?= $idx > 0 ? ' | ' : ' ' ?><?= htmlspecialchars((string)$rec['full_name']) ?> (<?= (int)$rec['position_rating'] ?>)
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin:22px 0 10px;">مسئولیت‌های کلیدی تیم</h3>
        <div class="grid">
            <?php
                $roles = [
                    'captain' => 'کاپیتان',
                    'penalty_taker' => 'زننده پنالتی',
                    'freekick_taker' => 'زننده ضربه آزاد',
                    'corner_taker' => 'زننده کرنر',
                ];
            ?>
            <?php foreach ($roles as $field => $label): ?>
                <div class="form-group">
                    <label><?= htmlspecialchars($label) ?></label>
                    <select name="<?= htmlspecialchars($field) ?>">
                        <option value="">-- بدون انتخاب --</option>
                        <?php foreach (($responsibility_options ?? []) as $opt): ?>
                            <option value="<?= (int)$opt['id'] ?>" <?= ((int)($tactic[$field] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$opt['name']) ?> | <?= htmlspecialchars((string)$opt['position']) ?> | OVR <?= (int)$opt['overall'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-success" style="margin-top: 15px;">ذخیره تاکتیک</button>
    </form>
</div>

<script>
    (function () {
        const formationSelect = document.getElementById('formation-select');
        if (!formationSelect) return;
        formationSelect.addEventListener('change', function () {
            const selected = this.value || '';
            const phaseKey = <?= json_encode((string)($phase_key ?? 'MATCH_1')) ?>;
            const url = `/squad/tactics?formation=${encodeURIComponent(selected)}&phase_key=${encodeURIComponent(phaseKey)}`;
            window.location.href = url;
        });
    })();
</script>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
