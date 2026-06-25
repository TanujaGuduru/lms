<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/exams">Exams</a>
      <span class="sep">/</span><span>Create Exam</span>
    </div>
    <h1 class="page-title">Create Exam</h1>
    <p class="page-subtitle">Set up a new exam with questions from the bank</p>
  </div>
  <a href="/super-admin/exams" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/exams" id="examForm" data-loading="Creating…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="question_ids" id="questionIdsField" value="">

  <div class="row g-3">
    <!-- Main Details -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Exam Details</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Title</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Python Basics - Mid Term" value="<?= \App\Core\View::old('title') ?>">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Instructions</label>
            <textarea name="instructions" class="form-control" rows="4" placeholder="Exam instructions shown to students…"><?= \App\Core\View::old('instructions') ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration_minutes" class="form-control" min="1" value="<?= \App\Core\View::old('duration_minutes', '60') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Pass Mark (%)</label>
                <input type="number" name="pass_mark" class="form-control" min="1" max="100" value="<?= \App\Core\View::old('pass_mark', '60') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Max Attempts</label>
                <input type="number" name="max_attempts" class="form-control" min="1" value="<?= \App\Core\View::old('max_attempts', '1') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="datetime-local" name="start_date" class="form-control" value="<?= \App\Core\View::old('start_date') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="datetime-local" name="end_date" class="form-control" value="<?= \App\Core\View::old('end_date') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Question Picker -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-question-circle" style="color:#6366f1"></i> Questions</h3>
          <button type="button" onclick="openQPicker()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Questions</button>
        </div>
        <div id="selectedQList" class="card-body" style="min-height:100px">
          <div class="empty-state" style="padding:24px" id="noQSelected">
            <p class="empty-state-desc">No questions added yet. Click "Add Questions" to pick from the bank.</p>
          </div>
        </div>
        <div class="card-footer" id="qFooter" style="display:none">
          <div class="d-flex justify-content-between align-items-center" style="font-size:13px;color:#64748b">
            <span>Total: <strong id="totalQ">0</strong> questions · <strong id="totalMarks">0</strong> marks</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Options</h3></div>
        <div class="card-body">
          <?php
          $examOpts = [
            ['name'=>'shuffle_questions', 'label'=>'Shuffle Questions'],
            ['name'=>'shuffle_options',   'label'=>'Shuffle Answer Options'],
            ['name'=>'show_results',      'label'=>'Show Results Immediately'],
          ];
          ?>
          <?php foreach ($examOpts as $opt): ?>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span style="font-size:13.5px;color:#374151"><?= $opt['label'] ?></span>
            <label class="form-switch">
              <input type="checkbox" name="<?= $opt['name'] ?>" value="1" checked>
              <span class="toggle-track"></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Course / Batch</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label">Linked Course</label>
            <select name="course_id" class="form-select">
              <option value="">None</option>
              <?php foreach ($courses ?? [] as $c): ?>
              <option value="<?= $c['id'] ?>"><?= \App\Core\View::e($c['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" name="exam_status" value="draft" class="btn btn-secondary w-100 mb-2">
            <i class="fas fa-save"></i> Save as Draft
          </button>
          <button type="submit" name="exam_status" value="published" class="btn btn-primary w-100">
            <i class="fas fa-globe"></i> Create & Publish
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Question Picker Modal -->
<div class="modal fade" id="qPickerModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-question-circle" style="color:#6366f1"></i> Question Bank</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 mb-3">
          <input type="text" id="qSearch" class="form-control" placeholder="Search questions…" oninput="filterQuestions()">
          <select id="qTypeFilter" class="form-select" style="width:160px" onchange="filterQuestions()">
            <option value="">All Types</option>
            <option value="mcq">MCQ</option>
            <option value="true_false">True/False</option>
            <option value="coding">Coding</option>
          </select>
        </div>
        <div id="qPickerList" style="max-height:400px;overflow-y:auto">
          <?php foreach ($questions ?? [] as $q): ?>
          <div class="d-flex align-items-start gap-3 p-3 mb-2 rounded q-pick-item" data-type="<?= $q['type'] ?>" data-text="<?= strtolower(\App\Core\View::e($q['text'])) ?>" style="background:#f8fafc;cursor:pointer" onclick="toggleQ(this, <?= $q['id'] ?>)" data-marks="<?= $q['marks'] ?? 1 ?>">
            <input type="checkbox" class="form-check-input q-checkbox" style="width:18px;height:18px;margin-top:2px;flex-shrink:0">
            <div style="flex:1">
              <div style="font-size:13.5px;font-weight:600;color:#0f172a"><?= \App\Core\View::e(substr($q['text'], 0, 120)) ?><?= strlen($q['text']) > 120 ? '…' : '' ?></div>
              <div class="d-flex gap-2 mt-1" style="font-size:12px">
                <span class="badge" style="background:#6366f118;color:#6366f1"><?= $q['type'] ?></span>
                <span class="badge" style="background:#f59e0b18;color:#f59e0b"><?= $q['difficulty'] ?></span>
                <span style="color:#94a3b8"><?= $q['marks'] ?? 1 ?> mark<?= ($q['marks']??1) > 1 ? 's' : '' ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($questions ?? [])): ?>
          <div class="empty-state" style="padding:40px">
            <p class="empty-state-desc">No approved questions in the bank yet.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <span style="font-size:13px;color:#64748b"><span id="pickerCount">0</span> questions selected</span>
        <div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="addSelectedQuestions()"><i class="fas fa-plus"></i> Add Selected</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let selectedIds = [];
const selectedData = {};

function openQPicker() {
  new bootstrap.Modal(document.getElementById('qPickerModal')).show();
}

function toggleQ(el, id) {
  const cb = el.querySelector('.q-checkbox');
  if (selectedIds.includes(id)) {
    selectedIds = selectedIds.filter(i => i !== id);
    cb.checked = false;
    el.style.background = '#f8fafc';
  } else {
    selectedIds.push(id);
    cb.checked = true;
    el.style.background = '#ede9fe';
    selectedData[id] = { text: el.dataset.text, marks: parseInt(el.dataset.marks) };
  }
  document.getElementById('pickerCount').textContent = selectedIds.length;
}

function filterQuestions() {
  const search = document.getElementById('qSearch').value.toLowerCase();
  const type   = document.getElementById('qTypeFilter').value;
  document.querySelectorAll('.q-pick-item').forEach(el => {
    const visible = (!search || el.dataset.text.includes(search)) && (!type || el.dataset.type === type);
    el.style.display = visible ? 'flex' : 'none';
  });
}

function addSelectedQuestions() {
  bootstrap.Modal.getInstance(document.getElementById('qPickerModal')).hide();
  updateSelectedList();
}

function updateSelectedList() {
  const list = document.getElementById('selectedQList');
  const footer = document.getElementById('qFooter');
  const noQ = document.getElementById('noQSelected');

  document.getElementById('questionIdsField').value = selectedIds.join(',');

  if (!selectedIds.length) {
    noQ.style.display = 'block';
    footer.style.display = 'none';
    return;
  }
  noQ.style.display = 'none';
  footer.style.display = 'block';

  let marks = 0;
  let html = '';
  selectedIds.forEach((id, i) => {
    const q = document.querySelector(`.q-pick-item[onclick*=",${id})"]`);
    if (!q) return;
    const text = q.querySelector('div div:first-child').textContent;
    const qmarks = parseInt(q.dataset.marks) || 1;
    marks += qmarks;
    html += `<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:#f8fafc">
      <div style="width:24px;height:24px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6366f1;flex-shrink:0">${i+1}</div>
      <div style="flex:1;font-size:13px;color:#374151">${text}</div>
      <span style="font-size:12px;color:#94a3b8">${qmarks}m</span>
      <button type="button" onclick="removeQ(${id})" style="background:none;border:none;color:#94a3b8;font-size:14px;cursor:pointer"><i class="fas fa-times"></i></button>
    </div>`;
  });

  const container = document.getElementById('selectedQList');
  container.innerHTML = html;
  container.appendChild(document.getElementById('noQSelected'));

  document.getElementById('totalQ').textContent = selectedIds.length;
  document.getElementById('totalMarks').textContent = marks;
}

function removeQ(id) {
  selectedIds = selectedIds.filter(i => i !== id);
  updateSelectedList();
}
</script>
