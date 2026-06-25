<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Integrations</span>
    </div>
    <h1 class="page-title">Integrations</h1>
    <p class="page-subtitle">Connect CodeGurukul with your favourite tools and services</p>
  </div>
</div>

<?php
$byCategory = [];
foreach ($integrations as $int) {
    $byCategory[$int['category']][] = $int;
}

$catIcons = [
  'payment'      => ['icon'=>'fas fa-credit-card',      'color'=>'#10b981', 'label'=>'Payment Gateways'],
  'communication'=> ['icon'=>'fas fa-comments',          'color'=>'#6366f1', 'label'=>'Communication'],
  'analytics'    => ['icon'=>'fas fa-chart-line',        'color'=>'#f59e0b', 'label'=>'Analytics'],
  'storage'      => ['icon'=>'fas fa-cloud-upload-alt',  'color'=>'#06b6d4', 'label'=>'Cloud Storage'],
  'video'        => ['icon'=>'fas fa-video',             'color'=>'#8b5cf6', 'label'=>'Video Conferencing'],
  'ai'           => ['icon'=>'fas fa-robot',             'color'=>'#ec4899', 'label'=>'AI & Automation'],
  'sso'          => ['icon'=>'fas fa-key',               'color'=>'#ef4444', 'label'=>'Single Sign-On'],
];
?>

<?php if (empty($integrations)): ?>
<div class="card">
  <div class="card-body">
    <div class="empty-state" style="padding:60px">
      <i class="fas fa-plug empty-state-icon"></i>
      <h4 class="empty-state-title">No Integrations Configured</h4>
      <p class="empty-state-desc">Add integrations to the database via schema to see them here.</p>
    </div>
  </div>
</div>
<?php else: ?>
<?php foreach ($byCategory as $cat => $items):
  $ci = $catIcons[$cat] ?? ['icon'=>'fas fa-plug','color'=>'#64748b','label'=>ucfirst($cat)];
?>
<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">
      <div class="card-icon" style="background:<?= $ci['color'] ?>18;color:<?= $ci['color'] ?>">
        <i class="<?= $ci['icon'] ?>"></i>
      </div>
      <?= $ci['label'] ?>
    </h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($items as $int):
        $config = json_decode($int['config'] ?? '{}', true) ?: [];
      ?>
      <div class="col-xl-4 col-md-6">
        <div class="card" style="border:2px solid <?= $int['is_active'] ? '#10b98133' : '#e2e8f0' ?>;transition:all .2s">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;background:<?= $int['is_active'] ? '#10b98118' : '#f1f5f9' ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">
                  <?php
                  $logos = ['razorpay'=>'💳','stripe'=>'💵','twilio'=>'📱','msg91'=>'💬','google_analytics'=>'📊','aws_s3'=>'☁️','zoom'=>'📹','openai'=>'🤖','google'=>'🔑','github'=>'🐙'];
                  echo $logos[strtolower($int['slug'])] ?? '🔌';
                  ?>
                </div>
                <div>
                  <div style="font-size:14px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($int['name']) ?></div>
                  <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::e($int['description'] ?? '') ?></div>
                </div>
              </div>
              <label class="form-switch" style="margin:0">
                <input type="hidden" value="0">
                <input type="checkbox" <?= $int['is_active'] ? 'checked' : '' ?>
                       onchange="toggleIntegration(<?= $int['id'] ?>, this)">
                <span class="toggle-track"></span>
              </label>
            </div>

            <?php if (!empty($config)): ?>
            <div style="font-size:12px;color:#94a3b8;margin-bottom:12px">
              <?= count(array_filter($config)) ?> of <?= count($config) ?> keys configured
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
              <button onclick="openConfigModal(<?= $int['id'] ?>, '<?= \App\Core\View::e($int['name']) ?>')"
                      class="btn btn-secondary btn-sm flex-fill">
                <i class="fas fa-cog"></i> Configure
              </button>
              <?php if ($int['is_active']): ?>
              <button onclick="testIntegration(<?= $int['id'] ?>)" class="btn btn-ghost btn-sm" title="Test connection">
                <i class="fas fa-plug"></i>
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Config Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="configModalTitle"><i class="fas fa-cog" style="color:#6366f1"></i> Configure Integration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="configModalBody">Loading…</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveConfigBtn"><i class="fas fa-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const integrations = <?= json_encode(array_map(fn($i) => ['id'=>$i['id'],'name'=>$i['name'],'config'=>json_decode($i['config']??'{}',true)?:[],'config_schema'=>json_decode($i['config_schema']??'[]',true)?:[]], $integrations)) ?>;

function toggleIntegration(id, cb) {
  cgFetch(`/super-admin/integrations/${id}/toggle`, { method: 'POST' }).then(d => {
    if (d.success) {
      CGToast.success(d.message);
      setTimeout(() => location.reload(), 600);
    } else {
      cb.checked = !cb.checked;
      CGToast.error(d.message);
    }
  });
}

function openConfigModal(id, name) {
  const int = integrations.find(i => i.id === id);
  if (!int) return;

  document.getElementById('configModalTitle').innerHTML = `<i class="fas fa-cog" style="color:#6366f1"></i> Configure ${name}`;
  window.__configId = id;

  const schema = int.config_schema || [];
  let html = schema.length
    ? schema.map(field => `
      <div class="form-group mb-3">
        <label class="form-label">${field.label || field.key}</label>
        <input type="${field.type === 'password' ? 'password' : 'text'}"
               class="form-control config-field" data-key="${field.key}"
               value="${int.config[field.key] || ''}"
               placeholder="${field.placeholder || ''}">
        ${field.hint ? `<div class="form-hint">${field.hint}</div>` : ''}
      </div>`).join('')
    : '<div class="alert alert-info" style="font-size:13px">This integration has no configurable fields.</div>';

  document.getElementById('configModalBody').innerHTML = html;
  new bootstrap.Modal(document.getElementById('configModal')).show();
}

document.getElementById('saveConfigBtn').addEventListener('click', async () => {
  const config = {};
  document.querySelectorAll('.config-field').forEach(f => { config[f.dataset.key] = f.value; });
  const d = await cgFetch(`/super-admin/integrations/${window.__configId}/save`, {
    method: 'POST', body: JSON.stringify({ config })
  });
  if (d.success) {
    CGToast.success('Configuration saved');
    bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
  } else CGToast.error(d.message);
});

function testIntegration(id) {
  CGToast.info('Testing connection…');
  cgFetch(`/super-admin/integrations/${id}/test`, { method: 'POST' }).then(d => {
    if (d.success) CGToast.success(d.message || 'Connection successful!');
    else CGToast.error(d.message || 'Connection failed');
  });
}
</script>
