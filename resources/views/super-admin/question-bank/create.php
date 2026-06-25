<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/question-bank">Question Bank</a>
      <span class="sep">/</span><span>Add Question</span>
    </div>
    <h1 class="page-title">Add Question</h1>
    <p class="page-subtitle">Add a new question to the bank</p>
  </div>
  <a href="/super-admin/question-bank" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/question-bank" data-loading="Saving…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Question</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Question Text</label>
            <textarea name="text" class="form-control" rows="4" required placeholder="Enter the question…"><?= \App\Core\View::old('text') ?></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label required">Type</label>
                <select name="type" id="qtype" class="form-select" onchange="switchQType(this.value)" required>
                  <option value="mcq">MCQ (Single Answer)</option>
                  <option value="msq">MCQ (Multiple Answer)</option>
                  <option value="true_false">True / False</option>
                  <option value="short_answer">Short Answer</option>
                  <option value="coding">Coding</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Difficulty</label>
                <select name="difficulty" class="form-select">
                  <option value="easy">Easy</option>
                  <option value="medium" selected>Medium</option>
                  <option value="hard">Hard</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Marks</label>
                <input type="number" name="marks" class="form-control" min="1" max="100" value="<?= \App\Core\View::old('marks', '1') ?>">
              </div>
            </div>
          </div>

          <!-- MCQ Options -->
          <div id="mcqOptions">
            <div class="form-group mb-3">
              <label class="form-label required">Options (check the correct answer)</label>
              <div id="optionsList">
                <?php foreach (['A','B','C','D'] as $i => $letter): ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <input type="radio" name="correct_answer" value="<?= $i ?>" form="qform"
                         class="form-check-input" style="width:18px;height:18px;flex-shrink:0" <?= $i === 0 ? 'checked' : '' ?>>
                  <span style="font-size:13px;font-weight:700;color:#6366f1;width:20px"><?= $letter ?>.</span>
                  <input type="text" name="options[]" class="form-control" required placeholder="Option <?= $letter ?>">
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- True/False -->
          <div id="tfOptions" style="display:none">
            <div class="form-group mb-3">
              <label class="form-label">Correct Answer</label>
              <div class="d-flex gap-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                  <input type="radio" name="tf_answer" value="true" checked class="form-check-input" style="width:18px;height:18px">
                  <span style="font-size:14px;color:#10b981">True</span>
                </label>
                <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                  <input type="radio" name="tf_answer" value="false" class="form-check-input" style="width:18px;height:18px">
                  <span style="font-size:14px;color:#ef4444">False</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Short Answer -->
          <div id="shortOptions" style="display:none">
            <div class="form-group mb-3">
              <label class="form-label">Expected Answer</label>
              <textarea name="expected_answer" class="form-control" rows="3" placeholder="Model answer…"><?= \App\Core\View::old('expected_answer') ?></textarea>
            </div>
          </div>

          <!-- Coding -->
          <div id="codingOptions" style="display:none">
            <div class="form-group mb-3">
              <label class="form-label">Starting Code</label>
              <textarea name="starting_code" class="form-control" rows="6" style="font-family:monospace;font-size:13px" placeholder="def solution():\n    pass"></textarea>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Test Cases (JSON)</label>
              <textarea name="test_cases" class="form-control" rows="4" style="font-family:monospace;font-size:13px" placeholder='[{"input":"1","expected":"1"}]'></textarea>
            </div>
          </div>

          <!-- Explanation -->
          <div class="form-group">
            <label class="form-label">Explanation <span style="font-size:12px;color:#94a3b8">(shown after submission)</span></label>
            <textarea name="explanation" class="form-control" rows="3" placeholder="Explain the correct answer…"><?= \App\Core\View::old('explanation') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Category & Tags</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label">Category</label>
            <input type="text" name="category_name" class="form-control" placeholder="e.g. Python, Data Structures" value="<?= \App\Core\View::old('category_name') ?>" list="categoryList">
            <datalist id="categoryList">
              <?php foreach ($categories ?? [] as $cat): ?>
              <option value="<?= \App\Core\View::e($cat['name']) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Tags</label>
            <input type="text" name="tags" class="form-control" placeholder="comma separated tags" value="<?= \App\Core\View::old('tags') ?>">
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <div class="card-body">
          <select name="status" class="form-select">
            <option value="pending_review">Pending Review</option>
            <option value="approved">Approved</option>
          </select>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Question</button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function switchQType(type) {
  document.getElementById('mcqOptions').style.display   = ['mcq','msq'].includes(type) ? 'block' : 'none';
  document.getElementById('tfOptions').style.display    = type === 'true_false'   ? 'block' : 'none';
  document.getElementById('shortOptions').style.display = type === 'short_answer' ? 'block' : 'none';
  document.getElementById('codingOptions').style.display= type === 'coding'       ? 'block' : 'none';

  if (type === 'msq') {
    document.querySelectorAll('#optionsList input[type="radio"]').forEach(r => {
      r.type = 'checkbox'; r.name = 'correct_answers[]';
    });
  } else {
    document.querySelectorAll('#optionsList input[type="checkbox"]').forEach(r => {
      r.type = 'radio'; r.name = 'correct_answer';
    });
  }
}
</script>
