<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/question-bank">Question Bank</a>
      <span class="sep">/</span><span>Edit Question</span>
    </div>
    <h1 class="page-title">Edit Question</h1>
  </div>
  <a href="/super-admin/question-bank" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/question-bank/<?= $question['id'] ?>" data-loading="Saving…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="_method" value="PUT">

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Question</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Question Text</label>
            <textarea name="text" class="form-control" rows="4" required><?= \App\Core\View::old('text', $question['text']) ?></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" id="qtype" class="form-select" onchange="switchQType(this.value)">
                  <?php foreach (['mcq'=>'MCQ','msq'=>'MCQ (Multi)','true_false'=>'True/False','short_answer'=>'Short Answer','coding'=>'Coding'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $question['type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Difficulty</label>
                <select name="difficulty" class="form-select">
                  <?php foreach (['easy','medium','hard'] as $d): ?>
                  <option value="<?= $d ?>" <?= $question['difficulty'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Marks</label>
              <input type="number" name="marks" class="form-control" min="1" value="<?= $question['marks'] ?? 1 ?>">
            </div>
          </div>

          <?php $opts = json_decode($question['options'] ?? '[]', true) ?: []; ?>
          <?php $correct = $question['correct_answer'] ?? ''; ?>

          <!-- MCQ Options -->
          <div id="mcqOptions" style="<?= !in_array($question['type'], ['mcq','msq']) ? 'display:none' : '' ?>">
            <div class="form-group mb-3">
              <label class="form-label">Options</label>
              <?php foreach (['A','B','C','D'] as $i => $letter): ?>
              <div class="d-flex align-items-center gap-2 mb-2">
                <input type="radio" name="correct_answer" value="<?= $i ?>"
                       class="form-check-input" style="width:18px;height:18px;flex-shrink:0"
                       <?= (string)$correct === (string)$i ? 'checked' : '' ?>>
                <span style="font-size:13px;font-weight:700;color:#6366f1;width:20px"><?= $letter ?>.</span>
                <input type="text" name="options[]" class="form-control" value="<?= \App\Core\View::e($opts[$i] ?? '') ?>" placeholder="Option <?= $letter ?>">
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- True/False -->
          <div id="tfOptions" style="<?= $question['type'] !== 'true_false' ? 'display:none' : '' ?>">
            <div class="form-group mb-3">
              <label class="form-label">Correct Answer</label>
              <div class="d-flex gap-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                  <input type="radio" name="tf_answer" value="true" <?= $correct === 'true' ? 'checked' : '' ?> class="form-check-input" style="width:18px;height:18px">
                  <span style="font-size:14px;color:#10b981">True</span>
                </label>
                <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                  <input type="radio" name="tf_answer" value="false" <?= $correct === 'false' ? 'checked' : '' ?> class="form-check-input" style="width:18px;height:18px">
                  <span style="font-size:14px;color:#ef4444">False</span>
                </label>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Explanation</label>
            <textarea name="explanation" class="form-control" rows="3"><?= \App\Core\View::old('explanation', $question['explanation'] ?? '') ?></textarea>
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
            <input type="text" name="category_name" class="form-control" value="<?= \App\Core\View::old('category_name', $question['category_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Tags</label>
            <input type="text" name="tags" class="form-control" value="<?= \App\Core\View::old('tags', $question['tags'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <div class="card-body">
          <select name="status" class="form-select">
            <option value="pending_review" <?= $question['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
            <option value="approved" <?= $question['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
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

<script>
function switchQType(type) {
  document.getElementById('mcqOptions').style.display    = ['mcq','msq'].includes(type) ? 'block' : 'none';
  document.getElementById('tfOptions').style.display     = type === 'true_false'   ? 'block' : 'none';
}
</script>
