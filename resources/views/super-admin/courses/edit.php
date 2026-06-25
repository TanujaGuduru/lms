<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/courses">Courses</a>
      <span class="sep">/</span><span>Edit</span>
    </div>
    <h1 class="page-title">Edit Course</h1>
    <p class="page-subtitle"><?= \App\Core\View::e($course['title']) ?></p>
  </div>
  <a href="/super-admin/courses" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Edit Tabs -->
<ul class="nav nav-tabs mb-3" style="gap:4px;border-bottom:2px solid #e2e8f0">
  <li class="nav-item"><button class="nav-link active" onclick="switchEditTab('details',this)"><i class="fas fa-info-circle"></i> Details</button></li>
  <li class="nav-item"><button class="nav-link" onclick="switchEditTab('modules',this)"><i class="fas fa-layer-group"></i> Modules</button></li>
  <li class="nav-item"><button class="nav-link" onclick="switchEditTab('settings',this)"><i class="fas fa-cog"></i> Settings</button></li>
</ul>

<!-- Details Tab -->
<div class="edit-tab" id="tab-details">
  <form method="POST" action="/super-admin/courses/<?= $course['id'] ?>" enctype="multipart/form-data" data-loading="Saving…">
    <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
    <div class="row g-3">
      <div class="col-xl-8">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Basic Info</h3></div>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label required">Course Title</label>
              <input type="text" name="title" class="form-control" required value="<?= \App\Core\View::old('title', $course['title']) ?>">
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Short Description</label>
              <textarea name="short_description" class="form-control" rows="2"><?= \App\Core\View::old('short_description', $course['short_description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Full Description</label>
              <textarea name="description" class="form-control" rows="6"><?= \App\Core\View::old('description', $course['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Course Details</h3></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Department</label>
                  <select name="department_id" class="form-select">
                    <option value="">Select…</option>
                    <?php foreach ($depts as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $course['department_id'] ? 'selected' : '' ?>><?= \App\Core\View::e($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Instructor</label>
                  <select name="instructor_id" class="form-select">
                    <option value="">Select…</option>
                    <?php foreach ($teachers as $ins): ?>
                    <option value="<?= $ins['id'] ?>" <?= $ins['id'] == $course['instructor_id'] ? 'selected' : '' ?>>
                      <?= \App\Core\View::e($ins['name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Level</label>
                  <select name="level" class="form-select">
                    <?php foreach (['beginner','intermediate','advanced','all_levels'] as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= $course['level'] === $lvl ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$lvl)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Duration (hours)</label>
                <input type="number" name="duration_hours" class="form-control" min="0" step="0.5" value="<?= \App\Core\View::old('duration_hours', $course['duration_hours'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Language</label>
                <input type="text" name="language" class="form-control" value="<?= \App\Core\View::old('language', $course['language'] ?? 'English') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Price (₹)</label>
                <input type="number" name="price" class="form-control" min="0" value="<?= \App\Core\View::old('price', $course['price'] ?? '0') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Thumbnail</h3></div>
          <div class="card-body">
            <div id="thumbPreview" style="margin-bottom:12px">
              <?php if ($course['thumbnail']): ?>
              <img src="<?= \App\Core\View::e($course['thumbnail']) ?>" style="width:100%;border-radius:8px">
              <?php else: ?>
              <div class="file-upload-area" style="margin:0" onclick="document.getElementById('thumbInput').click()">
                <i class="fas fa-image" style="font-size:32px;color:#cbd5e1;margin-bottom:8px"></i>
                <p style="font-size:13px;color:#94a3b8;margin:0">Click to upload</p>
              </div>
              <?php endif; ?>
            </div>
            <input type="file" id="thumbInput" name="thumbnail" accept="image/*"
                   <?= !$course['thumbnail'] ? 'style="display:none"' : '' ?>
                   onchange="previewThumb(this)">
            <?php if ($course['thumbnail']): ?>
            <button type="button" class="btn btn-ghost btn-sm w-100" onclick="document.getElementById('thumbInput').click()">
              <i class="fas fa-upload"></i> Change Image
            </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <button type="submit" name="status" value="<?= $course['status'] ?>" class="btn btn-primary w-100 mb-2">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- Modules Tab -->
<div class="edit-tab d-none" id="tab-modules">
  <div class="row g-3">
    <div class="col-xl-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Modules</h3>
          <button onclick="addModule()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div id="moduleList" class="card-body" style="padding:8px">
          <?php foreach ($modules as $m): ?>
          <div class="module-item d-flex align-items-center justify-content-between p-2 mb-1 rounded" style="cursor:pointer;background:#f8fafc" onclick="loadLessons(<?= $m['id'] ?>, this)">
            <div>
              <div style="font-size:13.5px;font-weight:600;color:#374151"><?= \App\Core\View::e($m['title']) ?></div>
              <div style="font-size:11.5px;color:#94a3b8"><?= (int)$m['lessons_count'] ?> lessons</div>
            </div>
            <div class="d-flex gap-1">
              <button onclick="editModule(event, <?= $m['id'] ?>)" class="btn btn-ghost btn-sm btn-icon"><i class="fas fa-edit"></i></button>
              <button onclick="deleteModule(event, <?= $m['id'] ?>)" class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($modules)): ?>
          <div class="empty-state" style="padding:24px">
            <p class="empty-state-desc">No modules yet. Click Add to create one.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-xl-8">
      <div class="card" id="lessonsPanel">
        <div class="card-header">
          <h3 class="card-title" id="lessonsPanelTitle">Select a module</h3>
          <button onclick="addLesson()" class="btn btn-primary btn-sm d-none" id="addLessonBtn"><i class="fas fa-plus"></i> Add Lesson</button>
        </div>
        <div id="lessonsList" class="card-body">
          <div class="empty-state" style="padding:40px">
            <i class="fas fa-layer-group empty-state-icon"></i>
            <p class="empty-state-desc">Select a module on the left to manage its lessons.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Settings Tab -->
<div class="edit-tab d-none" id="tab-settings">
  <form method="POST" action="/super-admin/courses/<?= $course['id'] ?>">
    <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="_settings_update" value="1">
    <div class="row g-3">
      <div class="col-xl-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Course Settings</h3></div>
          <div class="card-body">
            <?php
            $toggles = [
              ['name'=>'certificate_enabled','label'=>'Certificate on Completion','hint'=>'Issue certificate when student completes all lessons'],
              ['name'=>'discussion_enabled',  'label'=>'Discussion Forum',         'hint'=>'Allow students to post in course forum'],
              ['name'=>'is_featured',         'label'=>'Featured Course',          'hint'=>'Show on homepage featured section'],
              ['name'=>'drip_content',        'label'=>'Drip Content',             'hint'=>'Release lessons on a schedule'],
            ];
            ?>
            <?php foreach ($toggles as $t): ?>
            <div class="d-flex justify-content-between align-items-center py-3" style="border-bottom:1px solid #f1f5f9">
              <div>
                <div style="font-size:14px;font-weight:600;color:#374151"><?= $t['label'] ?></div>
                <div style="font-size:12px;color:#94a3b8"><?= $t['hint'] ?></div>
              </div>
              <label class="form-switch">
                <input type="checkbox" name="<?= $t['name'] ?>" value="1" <?= !empty($course[$t['name']]) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
let __currentModuleId = null;
const __courseId = <?= $course['id'] ?>;

function switchEditTab(tab, btn) {
  document.querySelectorAll('.edit-tab').forEach(t => t.classList.add('d-none'));
  document.getElementById('tab-' + tab).classList.remove('d-none');
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function loadLessons(moduleId, el) {
  __currentModuleId = moduleId;
  document.querySelectorAll('.module-item').forEach(m => m.style.background = '#f8fafc');
  el.style.background = '#ede9fe';
  document.getElementById('addLessonBtn').classList.remove('d-none');

  const d = await cgFetch(`/super-admin/courses/${__courseId}/modules/${moduleId}/lessons`);
  if (!d.success) return;

  const lessons = d.data || [];
  document.getElementById('lessonsPanelTitle').textContent = el.querySelector('.fw-semibold, div')?.textContent.trim() || 'Lessons';
  document.getElementById('lessonsList').innerHTML = lessons.length
    ? lessons.map((l, i) => `<div class="d-flex align-items-center gap-3 py-2 px-2 mb-1 rounded" style="background:#f8fafc">
        <div style="width:24px;height:24px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6366f1">${i+1}</div>
        <i class="fas fa-${l.type==='video'?'play-circle':'file-alt'}" style="color:#94a3b8;width:14px"></i>
        <div style="flex:1"><div style="font-size:13.5px;font-weight:600;color:#374151">${l.title}</div></div>
        <button onclick="deleteLesson(${l.id})" class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
      </div>`).join('')
    : '<div class="empty-state" style="padding:24px"><p class="empty-state-desc">No lessons in this module.</p></div>';
}

async function addModule() {
  const { value: title } = await Swal.fire({ title: 'Module Title', input: 'text', inputPlaceholder: 'Enter module title', showCancelButton: true });
  if (!title) return;
  const d = await cgFetch(`/super-admin/courses/${__courseId}/modules`, { method: 'POST', body: JSON.stringify({ title }) });
  if (d.success) { CGToast.success('Module added'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}

async function deleteModule(e, id) {
  e.stopPropagation();
  const r = await Swal.fire({ title: 'Delete Module?', text: 'All lessons will be deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' });
  if (!r.isConfirmed) return;
  const d = await cgFetch(`/super-admin/courses/${__courseId}/modules/${id}`, { method: 'DELETE' });
  if (d.success) { CGToast.success('Module deleted'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}

async function addLesson() {
  const { value: title } = await Swal.fire({ title: 'Lesson Title', input: 'text', inputPlaceholder: 'Enter lesson title', showCancelButton: true });
  if (!title) return;
  const d = await cgFetch(`/super-admin/courses/${__courseId}/modules/${__currentModuleId}/lessons`, {
    method: 'POST', body: JSON.stringify({ title, type: 'video' })
  });
  if (d.success) { CGToast.success('Lesson added'); loadLessons(__currentModuleId, document.querySelector('.module-item[onclick*="'+__currentModuleId+'"]') || {}); }
  else CGToast.error(d.message);
}

async function deleteLesson(id) {
  const r = await Swal.fire({ title: 'Delete Lesson?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' });
  if (!r.isConfirmed) return;
  const d = await cgFetch(`/super-admin/courses/${__courseId}/lessons/${id}`, { method: 'DELETE' });
  if (d.success) { CGToast.success('Lesson deleted'); loadLessons(__currentModuleId, {}); }
}

function previewThumb(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('thumbPreview').innerHTML = `<img src="${e.target.result}" style="width:100%;border-radius:8px;margin-bottom:8px">`;
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
