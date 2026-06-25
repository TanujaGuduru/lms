<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/exams">Exams</a>
      <span class="sep">/</span><span>Edit</span>
    </div>
    <h1 class="page-title">Edit Exam</h1>
    <p class="page-subtitle"><?= \App\Core\View::e($exam['title']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/exams/<?= $exam['id'] ?>/results" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar"></i> Results</a>
    <a href="/super-admin/exams" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<form method="POST" action="/super-admin/exams/<?= $exam['id'] ?>/update" id="examEditForm" data-loading="Saving…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="question_ids" id="questionIdsField" value="<?= implode(',', array_column($examQuestions ?? [], 'id')) ?>">

  <div class="row g-3">
    <!-- Main Details -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Exam Details</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Title</label>
            <input type="text" name="title" class="form-control" required value="<?= \App\Core\View::old('title', $exam['title']) ?>">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Instructions</label>
            <textarea name="instructions" class="form-control" rows="4"><?= \App\Core\View::old('instructions', $exam['instructions'] ?? '') ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration_minutes" class="form-control" min="1"
                       value="<?= \App\Core\View::old('duration_minutes', $exam['duration_minutes'] ?? 60) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Pass Mark (%)</label>
                <input type="number" name="pass_mark" class="form-control" min="1" max="100"
                       value="<?= \App\Core\View::old('pass_mark', $exam['pass_mark'] ?? 60) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Max Attempts</label>
                <input type="number" name="max_attempts" class="form-control" min="1"
                       value="<?= \App\Core\View::old('max_attempts', $exam['max_attempts'] ?? 1) ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="datetime-local" name="start_date" class="form-control"
                       value="<?= $exam['start_date'] ? date('Y-m-d\TH:i', strtotime($exam['start_date'])) : '' ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="datetime-local" name="end_date" class="form-control"
                       value="<?= $exam['end_date'] ? date('Y-m-d\TH:i', strtotime($exam['end_date'])) : '' ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Questions -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-question-circle" style="color:#6366f1"></i> Questions (<?= count($examQuestions ?? []) ?>)</h3>
          <button type="button" onclick="openQPicker()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Questions</button>
        </div>
        <div id="selectedQList" class="card-body" style="min-height:80px">
          <?php if (empty($examQuestions)): ?>
          <div class="empty-state" style="padding:24px" id="noQSelected">
            <p class="empty-state-desc">No questions added yet.</p>
          </div>
          <?php else: ?>
          <div id="noQSelected" style="display:none"></div>
          <?php foreach ($examQuestions as $i => $q): ?>
          <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded exam-q-item" data-qid="<?= $q['id'] ?>" style="background:#f8fafc">
            <div style="width:24px;height:24px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6366f1;flex-shrink:0"><?= $i+1 ?></div>
            <div style="flex:1;font-size:13px;color:#374151"><?= \App\Core\View::e(substr($q['text'], 0, 100)) ?><?= strlen($q['text']) > 100 ? '…' : '' ?></div>
            <span style="font-size:12px;color:#94a3b8"><?= $q['marks'] ?? 1 ?>m</span>
            <button type="button" onclick="removeQ(<?= $q['id'] ?>)" style="background:none;border:none;color:#94a3b8;font-size:14px;cursor:pointer"><i class="fas fa-times"></i></button>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="card-footer" id="qFooter">
          <div style="font-size:13px;color:#64748b">
            Total: <strong id="totalQ"><?= count($examQuestions ?? []) ?></strong> questions ·
            <strong id="totalMarks"><?= array_sum(array_column($examQuestions ?? [], 'marks')) ?></strong> marks
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
              <input type="checkbox" name="<?= $opt['name'] ?>" value="1"
                     <?= !empty($exam[$opt['name']]) ? 'checked' : '' ?>>
              <span class="toggle-track"></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <div class="card-body">
          <select name="exam_status" class="form-select">
            <option value="draft" <?= $exam['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $exam['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="archived" <?= $exam['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Changes</button>
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
        <h5 class="modal-title"><i class="fas fa-question-circle" style="color:#6366f1"></i> Add Questions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 mb-3">
          <input type="text" id="qSearch" class="form-control" placeholder="Search questions…" oninput="filterQs()">
          <select id="qTypeF" class="form-select" style="width:160px" onchange="filterQs()">
            <option value="">All Types</option>
            <option value="mcq">MCQ</option>
            <option value="true_false">True/False</option>
            <option value="coding">Coding</option>
          </select>
        </div>
        <div id="qPickerList" style="max-height:400px;overflow-y:auto">
          <?php foreach ($allQuestions ?? [] as $q):
            $alreadyAdded = in_array($q['id'], array_column($examQuestions ?? [], 'id'));
          ?>
          <div class="d-flex align-items-start gap-3 p-3 mb-2 rounded q-item"
               data-type="<?= $q['type'] ?>"
               data-text="<?= strtolower(htmlspecialchars($q['text'], ENT_QUOTES)) ?>"
               data-marks="<?= $q['marks'] ?? 1 ?>"
               style="background:<?= $alreadyAdded ? '#f0fdf4' : '#f8fafc' ?>;cursor:pointer"
               onclick="<?= $alreadyAdded ? '' : "pickQ(this, {$q['id']})" ?>">
            <input type="checkbox" class="form-check-input" style="width:18px;height:18px;flex-shrink:0" <?= $alreadyAdded ? 'checked disabled' : '' ?>>
            <div style="flex:1">
              <div style="font-size:13.5px;font-weight:600;color:<?= $alreadyAdded ? '#94a3b8' : '#0f172a' ?>"><?= \App\Core\View::e(substr($q['text'], 0, 120)) ?></div>
              <div class="d-flex gap-2 mt-1">
                <span class="badge" style="background:#6366f118;color:#6366f1"><?= $q['type'] ?></span>
                <span class="badge" style="background:#f59e0b18;color:#f59e0b"><?= $q['difficulty'] ?></span>
                <span style="font-size:12px;color:#94a3b8"><?= $q['marks'] ?? 1 ?>m</span>
                <?php if ($alreadyAdded): ?><span class="badge" style="background:#10b98118;color:#059669">Added</span><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($allQuestions ?? [])): ?>
          <div class="empty-state" style="padding:40px"><p class="empty-state-desc">No approved questions in the bank.</p></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <span style="font-size:13px;color:#64748b"><span id="pickerCount">0</span> new questions selected</span>
        <div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="addPicked()"><i class="fas fa-plus"></i> Add to Exam</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let selectedIds = <?= json_encode(array_column($examQuestions ?? [], 'id')) ?>;
let pendingPicks = [];
const pickedData = {};

function openQPicker() {
  pendingPicks = [];
  document.getElementById('pickerCount').textContent = '0';
  document.querySelectorAll('.q-item input[type="checkbox"]:not([disabled])').forEach(cb => { cb.checked = false; cb.closest('.q-item').style.background = '#f8fafc'; });
  new bootstrap.Modal(document.getElementById('qPickerModal')).show();
}

function pickQ(el, id) {
  const cb = el.querySelector('input[type="checkbox"]');
  if (pendingPicks.includes(id)) {
    pendingPicks = pendingPicks.filter(i => i !== id);
    cb.checked = false;
    el.style.background = '#f8fafc';
  } else {
    pendingPicks.push(id);
    cb.checked = true;
    el.style.background = '#ede9fe';
    pickedData[id] = { text: el.querySelector('div div:first-child').textContent, marks: parseInt(el.dataset.marks) };
  }
  document.getElementById('pickerCount').textContent = pendingPicks.length;
}

function addPicked() {
  bootstrap.Modal.getInstance(document.getElementById('qPickerModal')).hide();
  pendingPicks.forEach(id => {
    if (!selectedIds.includes(id)) {
      selectedIds.push(id);
      const idx = selectedIds.length;
      const d = pickedData[id];
      const div = document.createElement('div');
      div.className = 'exam-q-item d-flex align-items-center gap-2 mb-2 p-2 rounded';
      div.dataset.qid = id;
      div.style.background = '#f8fafc';
      div.innerHTML = `<div style="width:24px;height:24px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6366f1;flex-shrink:0">${idx}</div>
        <div style="flex:1;font-size:13px;color:#374151">${d.text.substring(0,100)}</div>
        <span style="font-size:12px;color:#94a3b8">${d.marks}m</span>
        <button type="button" onclick="removeQ(${id})" style="background:none;border:none;color:#94a3b8;font-size:14px;cursor:pointer"><i class="fas fa-times"></i></button>`;
      document.getElementById('selectedQList').appendChild(div);
    }
  });
  updateIdsField();
  pendingPicks = [];
}

function removeQ(id) {
  selectedIds = selectedIds.filter(i => i !== id);
  document.querySelector(`.exam-q-item[data-qid="${id}"]`)?.remove();
  updateIdsField();
}

function updateIdsField() {
  document.getElementById('questionIdsField').value = selectedIds.join(',');
  const items = document.querySelectorAll('.exam-q-item');
  document.getElementById('totalQ').textContent = items.length;
  document.getElementById('noQSelected').style.display = items.length ? 'none' : 'block';
}

function filterQs() {
  const s = document.getElementById('qSearch').value.toLowerCase();
  const t = document.getElementById('qTypeF').value;
  document.querySelectorAll('.q-item').forEach(el => {
    el.style.display = (!s || el.dataset.text.includes(s)) && (!t || el.dataset.type === t) ? 'flex' : 'none';
  });
}
</script>
