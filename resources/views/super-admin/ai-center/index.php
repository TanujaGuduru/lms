<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>AI Center</span>
    </div>
    <h1 class="page-title">AI Center</h1>
    <p class="page-subtitle">Generate quizzes, lesson content, and assignments using AI</p>
  </div>
  <a href="/super-admin/settings/ai" class="btn btn-secondary btn-sm">
    <i class="fas fa-cog"></i> AI Settings
  </a>
</div>

<!-- AI Tools Grid -->
<div class="row g-3 mb-4">
  <?php
  $aiTools = [
    ['title'=>'Quiz Generator',       'desc'=>'Create MCQ/T-F quizzes from any topic instantly',  'icon'=>'fas fa-question-circle','color'=>'#6366f1', 'tab'=>'quiz'],
    ['title'=>'Content Generator',    'desc'=>'Write lessons, summaries, and outlines automatically','icon'=>'fas fa-file-alt',     'color'=>'#10b981', 'tab'=>'content'],
    ['title'=>'Assignment Creator',   'desc'=>'Generate project, essay, and research assignments',  'icon'=>'fas fa-tasks',         'color'=>'#f59e0b', 'tab'=>'assignment'],
    ['title'=>'Study Notes',          'desc'=>'Convert lesson content into structured study notes', 'icon'=>'fas fa-sticky-note',   'color'=>'#8b5cf6', 'tab'=>'notes'],
  ];
  ?>
  <?php foreach ($aiTools as $t): ?>
  <div class="col-xl-3 col-md-6">
    <button onclick="switchTab('<?= $t['tab'] ?>')"
            class="quick-action-btn ai-tool-btn w-100" id="tab-btn-<?= $t['tab'] ?>"
            style="--qa-color:<?= $t['color'] ?>;align-items:flex-start;padding:20px;flex-direction:row;gap:16px;text-align:left;border:2px solid transparent">
      <div class="qa-icon" style="flex-shrink:0;width:44px;height:44px;font-size:18px"><i class="<?= $t['icon'] ?>"></i></div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0f172a"><?= $t['title'] ?></div>
        <div style="font-size:12px;color:#64748b;line-height:1.4;margin-top:3px"><?= $t['desc'] ?></div>
      </div>
    </button>
  </div>
  <?php endforeach; ?>
</div>

<!-- AI Generator Panel -->
<div class="row g-3">
  <div class="col-xl-5">
    <!-- Input Panel -->
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-robot" style="color:#6366f1"></i> Generate</h3></div>
      <div class="card-body">

        <!-- Quiz Tab -->
        <div class="ai-tab" id="tab-quiz">
          <div class="form-group mb-3">
            <label class="form-label">Topic / Subject <span style="color:#ef4444">*</span></label>
            <input type="text" id="quizTopic" class="form-control" placeholder="e.g. Python Functions, World War II, Photosynthesis">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Count</label>
              <select id="quizCount" class="form-select">
                <?php foreach ([5,10,15,20] as $n): ?><option><?= $n ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Difficulty</label>
              <select id="quizDiff" class="form-select">
                <option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select id="quizType" class="form-select">
                <option value="mcq">MCQ</option><option value="true_false">True/False</option><option value="mixed">Mixed</option>
              </select>
            </div>
          </div>
          <button onclick="generateQuiz()" class="btn btn-primary w-100" id="quizBtn">
            <i class="fas fa-magic"></i> Generate Quiz
          </button>
        </div>

        <!-- Content Tab -->
        <div class="ai-tab d-none" id="tab-content">
          <div class="form-group mb-3">
            <label class="form-label">Topic <span style="color:#ef4444">*</span></label>
            <input type="text" id="contentTopic" class="form-control" placeholder="e.g. Introduction to Machine Learning">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Content Type</label>
              <select id="contentType" class="form-select">
                <option value="lesson">Full Lesson</option>
                <option value="summary">Summary</option>
                <option value="outline">Outline</option>
                <option value="explanation">Explanation</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Length</label>
              <select id="contentLength" class="form-select">
                <option value="short">Short (~200 words)</option>
                <option value="medium" selected>Medium (~500 words)</option>
                <option value="long">Long (~1000 words)</option>
              </select>
            </div>
          </div>
          <button onclick="generateContent()" class="btn btn-primary w-100" id="contentBtn">
            <i class="fas fa-magic"></i> Generate Content
          </button>
        </div>

        <!-- Assignment Tab -->
        <div class="ai-tab d-none" id="tab-assignment">
          <div class="form-group mb-3">
            <label class="form-label">Topic <span style="color:#ef4444">*</span></label>
            <input type="text" id="assignTopic" class="form-control" placeholder="e.g. Build a REST API with Node.js">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Level</label>
              <select id="assignLevel" class="form-select">
                <option value="beginner">Beginner</option>
                <option value="intermediate" selected>Intermediate</option>
                <option value="advanced">Advanced</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Type</label>
              <select id="assignType" class="form-select">
                <option value="project">Project</option>
                <option value="essay">Essay</option>
                <option value="practical">Practical</option>
                <option value="research">Research</option>
              </select>
            </div>
          </div>
          <button onclick="generateAssignment()" class="btn btn-primary w-100" id="assignBtn">
            <i class="fas fa-magic"></i> Generate Assignment
          </button>
        </div>

        <!-- Notes Tab (stub) -->
        <div class="ai-tab d-none" id="tab-notes">
          <div class="form-group mb-3">
            <label class="form-label">Paste Lesson Content</label>
            <textarea id="notesInput" class="form-control" rows="8" placeholder="Paste your lesson text here and AI will create structured study notes…"></textarea>
          </div>
          <button onclick="CGToast.info('Coming soon!')" class="btn btn-primary w-100">
            <i class="fas fa-magic"></i> Generate Notes
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-xl-7">
    <!-- Output Panel -->
    <div class="card h-100" style="min-height:500px">
      <div class="card-header">
        <h3 class="card-title" id="outputTitle"><i class="fas fa-sparkles" style="color:#f59e0b"></i> AI Output</h3>
        <div class="d-flex gap-2" id="outputActions" style="display:none!important">
          <button onclick="saveToBank()" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Save to Question Bank</button>
          <button onclick="copyOutput()" class="btn btn-secondary btn-sm"><i class="fas fa-copy"></i> Copy</button>
        </div>
      </div>
      <div class="card-body" id="outputBody">
        <!-- Empty state -->
        <div class="empty-state" id="outputEmpty">
          <div style="width:80px;height:80px;background:linear-gradient(135deg,#6366f118,#8b5cf618);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px">🤖</div>
          <h4 class="empty-state-title">Ready to Generate</h4>
          <p class="empty-state-desc">Select a tool on the left, fill in the details, and click Generate to see AI magic.</p>
        </div>

        <!-- Loading state -->
        <div class="d-flex flex-column align-items-center justify-content-center h-100 gap-3 d-none" id="outputLoading">
          <div class="loading-spinner" style="width:40px;height:40px;border-width:4px"></div>
          <div style="font-size:14px;color:#64748b;font-weight:500">Generating with AI…</div>
          <div style="font-size:12px;color:#94a3b8">This usually takes 5–15 seconds</div>
        </div>

        <!-- Result -->
        <div class="d-none" id="outputResult"></div>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.ai-tab').forEach(t => t.classList.add('d-none'));
  document.getElementById('tab-' + tab).classList.remove('d-none');
  document.querySelectorAll('.ai-tool-btn').forEach(b => b.style.borderColor = 'transparent');
  document.getElementById('tab-btn-' + tab).style.borderColor = 'var(--cg-primary)';
}

function setLoading(loading) {
  document.getElementById('outputEmpty').classList.toggle('d-none', loading || !!window.__hasResult);
  document.getElementById('outputLoading').classList.toggle('d-none', !loading);
  if (loading) document.getElementById('outputResult').classList.add('d-none');
}

function showResult(html, showActions = false) {
  window.__hasResult = true;
  document.getElementById('outputEmpty').classList.add('d-none');
  document.getElementById('outputLoading').classList.add('d-none');
  const res = document.getElementById('outputResult');
  res.innerHTML = html;
  res.classList.remove('d-none');
  if (showActions) document.getElementById('outputActions').style.display = 'flex';
}

async function generateQuiz() {
  const topic = document.getElementById('quizTopic').value.trim();
  if (!topic) { CGToast.warning('Enter a topic first'); return; }
  setLoading(true);

  const d = await cgFetch('/super-admin/ai-center/generate-quiz', {
    method: 'POST',
    body: JSON.stringify({
      topic, count: document.getElementById('quizCount').value,
      difficulty: document.getElementById('quizDiff').value,
      type: document.getElementById('quizType').value,
    })
  });

  setLoading(false);
  if (!d.success) { CGToast.error(d.message); return; }

  window.__aiQuestions = d.data?.questions || [];
  let html = `<div style="font-size:12px;color:#64748b;margin-bottom:12px">${window.__aiQuestions.length} questions generated for "<strong>${topic}</strong>"</div>`;
  window.__aiQuestions.forEach((q, i) => {
    const correct = Array.isArray(q.correct_answer) ? q.correct_answer.join(', ') : q.correct_answer;
    html += `<div style="background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:10px;border-left:3px solid #6366f1">
      <div style="font-size:13.5px;font-weight:600;color:#0f172a;margin-bottom:8px">${i+1}. ${q.text}</div>
      ${q.options ? q.options.map((o, j) => `<div style="font-size:12.5px;padding:3px 8px;border-radius:6px;margin-bottom:2px;background:${o === correct ? '#10b98118' : 'transparent'};color:${o === correct ? '#059669' : '#64748b'}">
        ${['A','B','C','D'][j] || (j+1)}. ${o} ${o === correct ? '✓' : ''}
      </div>`).join('') : ''}
      ${q.explanation ? `<div style="font-size:12px;color:#94a3b8;margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0"><i class="fas fa-lightbulb" style="color:#f59e0b"></i> ${q.explanation}</div>` : ''}
    </div>`;
  });
  showResult(html, true);
}

async function generateContent() {
  const topic = document.getElementById('contentTopic').value.trim();
  if (!topic) { CGToast.warning('Enter a topic first'); return; }
  setLoading(true);

  const d = await cgFetch('/super-admin/ai-center/generate-content', {
    method: 'POST',
    body: JSON.stringify({
      topic, type: document.getElementById('contentType').value,
      length: document.getElementById('contentLength').value
    })
  });

  setLoading(false);
  if (!d.success) { CGToast.error(d.message); return; }

  const content = d.data?.content || '';
  const html = `<div style="font-size:14px;line-height:1.8;color:#374151;white-space:pre-wrap">${content}</div>`;
  showResult(html);
  window.__aiContent = content;
}

async function generateAssignment() {
  const topic = document.getElementById('assignTopic').value.trim();
  if (!topic) { CGToast.warning('Enter a topic first'); return; }
  setLoading(true);

  const d = await cgFetch('/super-admin/ai-center/generate-assignment', {
    method: 'POST',
    body: JSON.stringify({
      topic, level: document.getElementById('assignLevel').value,
      type: document.getElementById('assignType').value
    })
  });

  setLoading(false);
  if (!d.success) { CGToast.error(d.message); return; }

  const content = d.data?.assignment || '';
  showResult(`<div style="font-size:13.5px;line-height:1.8;color:#374151;white-space:pre-wrap">${content}</div>`);
}

async function saveToBank() {
  if (!window.__aiQuestions?.length) return;
  const d = await cgFetch('/super-admin/question-bank/store-bulk', {
    method: 'POST',
    body: JSON.stringify({ questions: window.__aiQuestions })
  });
  if (d.success) CGToast.success(`${window.__aiQuestions.length} questions saved to bank!`);
  else CGToast.error(d.message || 'Save failed');
}

function copyOutput() {
  const text = document.getElementById('outputResult').innerText;
  navigator.clipboard.writeText(text).then(() => CGToast.success('Copied to clipboard'));
}

// Activate first tab by default
switchTab('quiz');
</script>

<style>
.ai-tool-btn { cursor:pointer;border:none;width:100%; }
.ai-tool-btn:hover { border-color:var(--qa-color)!important; }
</style>
