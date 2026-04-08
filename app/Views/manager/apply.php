<?php $title = 'ارسال درخواست مربیگری'; require __DIR__ . '/../layout/header.php'; ?>

<style>
.section-card { border:1px solid #e5e7eb; border-radius:10px; margin-bottom:16px; overflow:hidden; }
.section-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#f8fafc; cursor:pointer; }
.section-body { padding:16px; border-top:1px solid #e5e7eb; }
.badge { background:#eef2ff; color:#3730a3; padding:4px 10px; border-radius:999px; font-size:12px; }
.preset-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:8px; margin-bottom:12px; }
.dynamic-list { list-style:none; padding:0; margin:0; }
.dynamic-item { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.dynamic-item input[type="text"] { flex:1; }
.drag-handle { cursor:grab; padding:6px 10px; border:1px solid #ddd; border-radius:6px; background:#fff; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:999; }
.modal { background:#fff; width:min(760px,95vw); max-height:85vh; overflow:auto; border-radius:12px; padding:20px; }
.summary-box { background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:10px; }
</style>

<div class="card">
    <h2>درخواست پست مربیگری</h2>
    <p>انتظارات، وظایف و تعهدات مالک را ببین، ویرایش کن و درخواست حرفه‌ای خودت را ارسال کن.</p>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
</div>

<?php if (empty($clubs)): ?>
<div class="card">
    <p>در حال حاضر باشگاهِ بدون مربی برای درخواست وجود ندارد.</p>
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
    <h3><?= htmlspecialchars((string)$club['name']) ?></h3>

    <form method="post" action="/manager/apply/submit" class="manager-apply-form">
        <input type="hidden" name="club_id" value="<?= (int)$club['id'] ?>">

        <div class="section-card" data-section="expectations">
            <div class="section-head" data-toggle>
                <strong>انتظارات</strong>
                <span class="badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="section-body">
                <div class="preset-grid" data-presets>
                    <?php foreach (['ارتقای سطح فنی بازیکنان','افزایش تعداد هنرجویان','صعود به رتبه‌های بالاتر','حفظ نظم و انضباط تیم'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="dynamic-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <div class="section-card" data-section="duties">
            <div class="section-head" data-toggle>
                <strong>وظایف</strong>
                <span class="badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="section-body">
                <div class="preset-grid" data-presets>
                    <?php foreach (['برگزاری تمرینات منظم','انتخاب ترکیب تیم برای مسابقات','بررسی عملکرد بازیکنان','ارائه گزارش هفتگی به مدیریت باشگاه'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="dynamic-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <div class="section-card" data-section="commitments">
            <div class="section-head" data-toggle>
                <strong>تعهدات</strong>
                <span class="badge" data-count>0 مورد انتخاب شده</span>
            </div>
            <div class="section-body">
                <div class="preset-grid" data-presets>
                    <?php foreach (['رعایت قوانین باشگاه','رفتار حرفه‌ای با بازیکنان','حفظ اعتبار باشگاه','رعایت نظم، ادب و اخلاق'] as $item): ?>
                    <label><input type="checkbox" value="<?= htmlspecialchars($item) ?>"> <?= htmlspecialchars($item) ?></label>
                    <?php endforeach; ?>
                </div>
                <ul class="dynamic-list" data-list></ul>
                <button type="button" class="btn" data-add>+ افزودن مورد جدید</button>
            </div>
        </div>

        <div class="card" style="background:#f8fafc;">
            <h4>توضیحات و پیشنهادات مربی</h4>
            <textarea name="cover_letter" rows="4" placeholder="اینجا شرایط، خواسته‌ها و پیشنهادات خود را بنویسید."></textarea>
        </div>

        <input type="hidden" name="proposed_expectations" data-output="expectations" value="<?= htmlspecialchars($ownerExpectations) ?>">
        <input type="hidden" name="proposed_duties" data-output="duties" value="<?= htmlspecialchars($ownerDuties) ?>">
        <input type="hidden" name="proposed_commitments" data-output="commitments" value="<?= htmlspecialchars($ownerCommitments) ?>">

        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="btn" data-preview>پیش‌نمایش نهایی</button>
            <button type="submit" class="btn btn-success">ارسال مستقیم</button>
        </div>
    </form>
</div>
<?php endforeach; ?>

<div class="modal-backdrop" id="preview-modal">
    <div class="modal">
        <h3>پیش‌نمایش نهایی درخواست مربیگری</h3>
        <div id="preview-content"></div>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="btn btn-success" id="confirm-submit">تأیید و ارسال درخواست</button>
            <button type="button" class="btn" id="close-preview">بستن</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('preview-modal');
    const previewContent = document.getElementById('preview-content');
    let activeForm = null;

    function createItem(text = '') {
        const li = document.createElement('li');
        li.className = 'dynamic-item';
        li.draggable = true;
        li.innerHTML = `
            <span class="drag-handle" title="جابجایی">↕</span>
            <input type="text" value="${text.replace(/"/g, '&quot;')}" placeholder="متن مورد...">
            <button type="button" class="btn btn-danger" data-remove>حذف</button>
        `;

        li.addEventListener('dragstart', () => li.classList.add('dragging'));
        li.addEventListener('dragend', () => li.classList.remove('dragging'));

        li.querySelector('[data-remove]').addEventListener('click', () => {
            li.remove();
            const section = li.closest('[data-section]');
            updateSection(section);
        });

        li.querySelector('input').addEventListener('input', () => {
            const section = li.closest('[data-section]');
            updateSection(section);
        });

        return li;
    }

    function getItems(section) {
        const checks = [...section.querySelectorAll('[data-presets] input[type="checkbox"]:checked')].map(c => c.value.trim()).filter(Boolean);
        const dynamics = [...section.querySelectorAll('[data-list] input[type="text"]')].map(i => i.value.trim()).filter(Boolean);
        return [...checks, ...dynamics];
    }

    function updateSection(section) {
        const key = section.dataset.section;
        const items = getItems(section);
        const output = section.closest('form').querySelector(`[data-output="${key}"]`);
        output.value = items.join("\n");
        section.querySelector('[data-count]').textContent = `${items.length} مورد انتخاب شده`;
    }

    function hydrateFromOwnerText(section, text) {
        if (!text) return;
        text.split('\n').map(s => s.trim()).filter(Boolean).forEach(item => {
            const preset = section.querySelector(`[data-presets] input[value="${CSS.escape(item)}"]`);
            if (preset) {
                preset.checked = true;
            } else {
                section.querySelector('[data-list]').appendChild(createItem(item));
            }
        });
    }

    function setupSection(section) {
        const key = section.dataset.section;
        const form = section.closest('form');
        const output = form.querySelector(`[data-output="${key}"]`);
        hydrateFromOwnerText(section, output.value || '');

        section.querySelectorAll('[data-presets] input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => updateSection(section));
        });

        const list = section.querySelector('[data-list]');
        section.querySelector('[data-add]').addEventListener('click', () => {
            list.appendChild(createItem(''));
            updateSection(section);
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = list.querySelector('.dragging');
            const after = [...list.querySelectorAll('.dynamic-item:not(.dragging)')].find(item => e.clientY <= item.getBoundingClientRect().top + item.offsetHeight / 2);
            if (!dragging) return;
            if (!after) list.appendChild(dragging);
            else list.insertBefore(dragging, after);
            updateSection(section);
        });

        const body = section.querySelector('.section-body');
        section.querySelector('[data-toggle]').addEventListener('click', () => {
            body.style.display = body.style.display === 'none' ? 'block' : 'none';
        });

        updateSection(section);
    }

    document.querySelectorAll('.manager-apply-form').forEach(form => {
        form.querySelectorAll('[data-section]').forEach(setupSection);

        form.querySelector('[data-preview]').addEventListener('click', () => {
            form.querySelectorAll('[data-section]').forEach(updateSection);
            const exp = (form.querySelector('[data-output="expectations"]').value || '').split('\n').filter(Boolean);
            const duties = (form.querySelector('[data-output="duties"]').value || '').split('\n').filter(Boolean);
            const comm = (form.querySelector('[data-output="commitments"]').value || '').split('\n').filter(Boolean);
            const note = form.querySelector('[name="cover_letter"]').value || '';

            previewContent.innerHTML = `
                <div class="summary-box"><strong>انتظارات:</strong> ${exp.length} مورد</div>
                <div class="summary-box"><strong>وظایف:</strong> ${duties.length} مورد</div>
                <div class="summary-box"><strong>تعهدات:</strong> ${comm.length} مورد</div>
                <div class="summary-box"><strong>توضیحات آزاد:</strong><br>${(note || '-').replace(/</g, '&lt;').replace(/\n/g, '<br>')}</div>
            `;

            activeForm = form;
            modal.style.display = 'flex';
        });
    });

    document.getElementById('close-preview').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('confirm-submit').addEventListener('click', () => {
        if (activeForm) activeForm.submit();
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
