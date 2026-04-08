<?php $title = 'تعریف انتظارات مربی'; require __DIR__ . '/../layout/header.php'; ?>

<style>
.exp-card { border:1px solid #e5e7eb; border-radius:10px; margin-bottom:16px; overflow:hidden; }
.exp-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; cursor:pointer; }
.exp-body { padding:16px; border-top:1px solid #e5e7eb; }
.exp-badge { background:#eef2ff; color:#3730a3; padding:4px 10px; border-radius:999px; font-size:12px; }
.exp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:8px; margin-bottom:12px; }
.exp-list { list-style:none; margin:0; padding:0; }
.exp-item { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.exp-item input[type="text"] { flex:1; }
.drag-handle { cursor:grab; padding:6px 10px; border:1px solid #ddd; border-radius:6px; }
.modal-backdrop { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.45); z-index:999; }
.modal { background:#fff; border-radius:12px; width:min(760px,95vw); max-height:85vh; overflow:auto; padding:20px; }
.summary { background:#f8fafc; padding:10px; border-radius:8px; margin-bottom:8px; }
</style>

<div class="card">
    <h2>تعریف انتظارات، وظایف و تعهدات مربی</h2>
    <p>مالک یا مدیر سایت می‌تواند ساختار پیشنهادی پست سرمربی را تعریف کند تا مربیان بر همان اساس درخواست بدهند.</p>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<div class="card">
    <form method="get" action="/manager/expectations" style="margin-bottom:20px;">
        <label>انتخاب باشگاه</label>
        <select name="club_id" onchange="this.form.submit()">
            <?php foreach (($clubs ?? []) as $club): ?>
                <option value="<?= (int)$club['id'] ?>" <?= ((int)($selected_club_id ?? 0) === (int)$club['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$club['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="/manager/expectations/save" id="expectation-form">
        <input type="hidden" name="club_id" value="<?= (int)($selected_club_id ?? 0) ?>">

        <div class="form-group">
            <label>عنوان پست</label>
            <input name="title" value="<?= htmlspecialchars((string)($expectation['title'] ?? 'پست سرمربی')) ?>">
        </div>

        <div class="exp-card" data-section="expectations">
            <div class="exp-head" data-toggle>
                <strong>انتظارات</strong>
                <span class="exp-badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="exp-body">
                <div class="exp-grid" data-presets>
                    <?php foreach (['ارتقای سطح فنی بازیکنان','افزایش تعداد هنرجویان','صعود به رتبه‌های بالاتر','حفظ نظم و انضباط تیم'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="exp-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <div class="exp-card" data-section="duties">
            <div class="exp-head" data-toggle>
                <strong>وظایف</strong>
                <span class="exp-badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="exp-body">
                <div class="exp-grid" data-presets>
                    <?php foreach (['برگزاری تمرینات منظم','انتخاب ترکیب تیم برای مسابقات','بررسی عملکرد بازیکنان','ارائه گزارش هفتگی به مدیریت باشگاه'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="exp-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <div class="exp-card" data-section="commitments">
            <div class="exp-head" data-toggle>
                <strong>تعهدات</strong>
                <span class="exp-badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="exp-body">
                <div class="exp-grid" data-presets>
                    <?php foreach (['رعایت قوانین باشگاه','رفتار حرفه‌ای با بازیکنان','حفظ اعتبار باشگاه','رعایت نظم، ادب و اخلاق'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="exp-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <input type="hidden" name="expectations" data-output="expectations" value="<?= htmlspecialchars((string)($expectation['expectations'] ?? '')) ?>">
        <input type="hidden" name="duties" data-output="duties" value="<?= htmlspecialchars((string)($expectation['duties'] ?? '')) ?>">
        <input type="hidden" name="commitments" data-output="commitments" value="<?= htmlspecialchars((string)($expectation['commitments'] ?? '')) ?>">

        <div style="display:flex; gap:10px;">
            <button type="button" class="btn" id="preview-btn">پیش‌نمایش نهایی</button>
            <button class="btn btn-success" type="submit">ذخیره</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="preview-modal">
    <div class="modal">
        <h3>پیش‌نمایش نهایی تعریف شرح همکاری</h3>
        <div id="preview-content"></div>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" id="confirm-submit" class="btn btn-success">تأیید و ذخیره</button>
            <button type="button" id="close-preview" class="btn">بستن</button>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('expectation-form');
    const modal = document.getElementById('preview-modal');
    const previewContent = document.getElementById('preview-content');

    function createItem(text='') {
        const li = document.createElement('li');
        li.className = 'exp-item';
        li.draggable = true;
        li.innerHTML = `
            <span class="drag-handle">↕</span>
            <input type="text" value="${text.replace(/"/g, '&quot;')}" placeholder="متن مورد...">
            <button type="button" class="btn btn-danger" data-remove>حذف</button>
        `;

        li.querySelector('[data-remove]').addEventListener('click', () => {
            li.remove();
            updateSection(li.closest('[data-section]'));
        });

        li.querySelector('input').addEventListener('input', () => {
            updateSection(li.closest('[data-section]'));
        });

        li.addEventListener('dragstart', () => li.classList.add('dragging'));
        li.addEventListener('dragend', () => li.classList.remove('dragging'));

        return li;
    }

    function getItems(section) {
        const checks = [...section.querySelectorAll('[data-presets] input[type="checkbox"]:checked')].map(i => i.value.trim()).filter(Boolean);
        const dynamic = [...section.querySelectorAll('[data-list] input[type="text"]')].map(i => i.value.trim()).filter(Boolean);
        return [...checks, ...dynamic];
    }

    function updateSection(section) {
        const key = section.dataset.section;
        const items = getItems(section);
        form.querySelector(`[data-output="${key}"]`).value = items.join("\n");
        section.querySelector('[data-count]').textContent = `${items.length} مورد انتخاب شده`;
    }

    function hydrate(section, text) {
        if (!text) return;
        text.split('\n').map(s => s.trim()).filter(Boolean).forEach(item => {
            const preset = section.querySelector(`[data-presets] input[value="${CSS.escape(item)}"]`);
            if (preset) preset.checked = true;
            else section.querySelector('[data-list]').appendChild(createItem(item));
        });
    }

    document.querySelectorAll('[data-section]').forEach(section => {
        const key = section.dataset.section;
        hydrate(section, form.querySelector(`[data-output="${key}"]`).value || '');

        section.querySelectorAll('[data-presets] input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => updateSection(section));
        });

        section.querySelector('[data-add]').addEventListener('click', () => {
            section.querySelector('[data-list]').appendChild(createItem(''));
            updateSection(section);
        });

        const list = section.querySelector('[data-list]');
        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = list.querySelector('.dragging');
            const after = [...list.querySelectorAll('.exp-item:not(.dragging)')].find(el => e.clientY <= el.getBoundingClientRect().top + el.offsetHeight / 2);
            if (!dragging) return;
            if (!after) list.appendChild(dragging); else list.insertBefore(dragging, after);
            updateSection(section);
        });

        const body = section.querySelector('.exp-body');
        section.querySelector('[data-toggle]').addEventListener('click', () => {
            body.style.display = body.style.display === 'none' ? 'block' : 'none';
        });

        updateSection(section);
    });

    document.getElementById('preview-btn').addEventListener('click', () => {
        document.querySelectorAll('[data-section]').forEach(updateSection);
        const e = (form.querySelector('[data-output="expectations"]').value || '').split('\n').filter(Boolean);
        const d = (form.querySelector('[data-output="duties"]').value || '').split('\n').filter(Boolean);
        const c = (form.querySelector('[data-output="commitments"]').value || '').split('\n').filter(Boolean);

        previewContent.innerHTML = `
            <div class="summary"><strong>انتظارات:</strong> ${e.length} مورد</div>
            <div class="summary"><strong>وظایف:</strong> ${d.length} مورد</div>
            <div class="summary"><strong>تعهدات:</strong> ${c.length} مورد</div>
        `;

        modal.style.display = 'flex';
    });

    document.getElementById('close-preview').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('confirm-submit').addEventListener('click', () => form.submit());
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
