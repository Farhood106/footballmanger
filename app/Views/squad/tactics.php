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

    <div id="tactics-save-status" style="display:none; margin-top:10px; padding:10px; border-radius:8px;"></div>

    <form method="POST" action="/squad/tactics/save" id="tactics-form">
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
                    <?php foreach (($mentalities ?? []) as $key => $label): ?>
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
                    <select class="lineup-slot-select" data-slot-key="<?= htmlspecialchars((string)$slot['slot_key']) ?>" name="lineup[<?= htmlspecialchars((string)$slot['slot_key']) ?>]" required>
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
                    <select class="responsibility-select" data-field="<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>">
                        <option value="">-- بدون انتخاب --</option>
                        <?php foreach (($responsibility_rankings[$field] ?? []) as $idx => $opt): ?>
                            <?php $best = $idx === 0 ? '⭐ ' : ''; ?>
                            <option value="<?= (int)$opt['id'] ?>" <?= ((int)($tactic[$field] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>>
                                <?= $best . htmlspecialchars((string)$opt['name']) ?> | <?= htmlspecialchars((string)$opt['position']) ?> | OVR <?= (int)$opt['overall'] ?> | Score <?= number_format((float)$opt['score'], 1) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($responsibility_rankings[$field] ?? [])): ?>
                        <small style="color:#666;">ابتدا ترکیب ۱۱ نفره را ذخیره کنید تا گزینه‌های مسئولیت از ترکیب اصلی نمایش داده شوند.</small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <small id="responsibility-hint" style="display:block; margin-top:6px; color:#666;">
            مسئولیت‌ها بر اساس ۱۱ بازیکن انتخاب‌شده فعلی در ترکیب اصلی تنظیم می‌شوند.
        </small>

        <button type="submit" class="btn btn-success" style="margin-top: 15px;">ذخیره تاکتیک</button>
    </form>
</div>

<script>
    (function () {
        const responsibilityPlayerPool = <?= json_encode($responsibility_player_pool ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const playerById = new Map((responsibilityPlayerPool || []).map(p => [String(p.id), p]));
        const roleFields = ['captain', 'penalty_taker', 'freekick_taker', 'corner_taker'];
        const formationSelect = document.getElementById('formation-select');
        if (!formationSelect) return;
        formationSelect.addEventListener('change', function () {
            const selected = this.value || '';
            const phaseKey = <?= json_encode((string)($phase_key ?? 'MATCH_1')) ?>;
            const url = `/squad/tactics?formation=${encodeURIComponent(selected)}&phase_key=${encodeURIComponent(phaseKey)}`;
            window.location.href = url;
        });

        const slotSelects = Array.from(document.querySelectorAll('.lineup-slot-select'));
        const roleSelects = Array.from(document.querySelectorAll('.responsibility-select'));
        const roleHint = document.getElementById('responsibility-hint');
        const statusBox = document.getElementById('tactics-save-status');
        const form = document.getElementById('tactics-form');
        const setStatus = (type, text) => {
            if (!statusBox) return;
            statusBox.style.display = 'block';
            statusBox.textContent = text || '';
            if (type === 'success') {
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#166534';
                statusBox.style.border = '1px solid #86efac';
                return;
            }
            statusBox.style.background = '#fee2e2';
            statusBox.style.color = '#991b1b';
            statusBox.style.border = '1px solid #fca5a5';
        };
        const calcScore = (player, role) => {
            const overall = Number(player.overall || 0);
            const shooting = Number(player.shooting || 0);
            const passing = Number(player.passing || 0);
            const dribbling = Number(player.dribbling || 0);
            const pace = Number(player.pace || 0);
            const physical = Number(player.physical || 0);
            const morale = Number(player.morale || 6.5);
            const roleCode = String(player.squad_role || 'ROTATION').toUpperCase();
            const position = String(player.position || '').toUpperCase();
            const stabilityBonus = roleCode === 'KEY_PLAYER' ? 4 : (roleCode === 'REGULAR_STARTER' ? 2 : (roleCode === 'ROTATION' ? 1 : 0));
            const lineupBonus = 5.0;
            if (role === 'captain') return (overall * 0.45) + (morale * 6.0) + (physical * 0.18) + lineupBonus + stabilityBonus;
            if (role === 'penalty_taker') return (shooting * 0.60) + (overall * 0.20) + (morale * 2.5) + (physical * 0.10) + lineupBonus;
            if (role === 'freekick_taker') return (passing * 0.45) + (shooting * 0.25) + (dribbling * 0.15) + (overall * 0.15) + lineupBonus;
            if (role === 'corner_taker') {
                const wideBonus = ['LM', 'RM', 'LW', 'RW', 'CAM'].includes(position) ? 3.0 : 0.0;
                return (passing * 0.45) + (dribbling * 0.25) + (pace * 0.10) + (overall * 0.20) + wideBonus + lineupBonus;
            }
            return overall;
        };
        const getSelectedLineupIds = () => {
            const ids = new Set();
            slotSelects.forEach(select => {
                if (select.value) ids.add(String(select.value));
            });
            return Array.from(ids);
        };
        const rebuildResponsibilitySelectors = () => {
            const selectedIds = getSelectedLineupIds();
            roleFields.forEach(field => {
                const select = roleSelects.find(s => s.dataset.field === field);
                if (!select) return;
                const previouslySelected = select.value;
                select.innerHTML = '<option value="">-- بدون انتخاب --</option>';
                const ranked = selectedIds
                    .map(id => playerById.get(id))
                    .filter(Boolean)
                    .map(player => ({
                        player,
                        score: calcScore(player, field),
                    }))
                    .sort((a, b) => (b.score - a.score) || ((Number(b.player.overall || 0) - Number(a.player.overall || 0))) || (Number(a.player.id || 0) - Number(b.player.id || 0)));

                ranked.forEach((item, idx) => {
                    const p = item.player;
                    const option = document.createElement('option');
                    option.value = String(p.id);
                    const best = idx === 0 ? '⭐ ' : '';
                    option.textContent = `${best}${p.name} | ${p.position} | OVR ${Number(p.overall || 0)} | Score ${item.score.toFixed(1)}`;
                    if (previouslySelected && previouslySelected === String(p.id)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            });

            if (roleHint) {
                if (selectedIds.length < 11) {
                    roleHint.textContent = 'ترکیب هنوز کامل نیست؛ مسئولیت‌ها فقط از بازیکنان انتخاب‌شده فعلی قابل انتخاب هستند.';
                } else {
                    roleHint.textContent = 'مسئولیت‌ها بر اساس ۱۱ بازیکن انتخاب‌شده فعلی در ترکیب اصلی تنظیم می‌شوند.';
                }
            }
        };

        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData(form);
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams(formData).toString()
                    });
                    let result = null;
                    try {
                        result = await response.json();
                    } catch (_) {
                        setStatus('error', 'پاسخ نامعتبر از سرور دریافت شد. لطفاً دوباره تلاش کنید.');
                        return;
                    }
                    if (!response.ok || (result && result.error)) {
                        setStatus('error', (result && result.error) ? result.error : 'ذخیره انجام نشد. لطفاً دوباره تلاش کنید.');
                        return;
                    }

                    setStatus('success', result.message || 'تاکتیک با موفقیت ذخیره شد.');
                    if (result.reload) {
                        setTimeout(() => window.location.reload(), 350);
                    }
                } catch (_) {
                    setStatus('error', 'اتصال با سرور برقرار نشد. اتصال شبکه را بررسی کنید و دوباره تلاش کنید.');
                }
            });
        }

        slotSelects.forEach(select => {
            select.addEventListener('change', function () {
                const selectedPlayerId = this.value;
                if (!selectedPlayerId) return;
                slotSelects.forEach(other => {
                    if (other === this) return;
                    if (other.value === selectedPlayerId) {
                        other.value = '';
                    }
                });
                rebuildResponsibilitySelectors();
            });
        });

        rebuildResponsibilitySelectors();
    })();
</script>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
