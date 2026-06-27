<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/certificates">Certificates</a>
      <span class="sep">/</span><span>Templates</span>
    </div>
    <h1 class="page-title">Certificate Templates</h1>
    <p class="page-subtitle">Design and manage reusable certificate templates</p>
  </div>
  <button onclick="openCreateTemplate()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Template</button>
</div>

<div class="row g-3">
  <?php if (empty($templates)): ?>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="empty-state" style="padding:60px">
          <i class="fas fa-certificate empty-state-icon"></i>
          <h4 class="empty-state-title">No Templates Yet</h4>
          <p class="empty-state-desc">Create a certificate template to start issuing certificates.</p>
          <button onclick="openCreateTemplate()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Template</button>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <?php foreach ($templates as $tpl): ?>
  <div class="col-xl-4 col-md-6">
    <div class="card" style="overflow:hidden">
      <!-- Preview -->
      <div style="height:180px;background:<?= $tpl['background_color'] ?? 'linear-gradient(135deg,#6366f1,#8b5cf6)' ?>;display:flex;align-items:center;justify-content:center;padding:20px;position:relative">
        <?php if ($tpl['background_image']): ?>
        <img src="<?= \App\Core\View::e($tpl['background_image']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.3">
        <?php endif; ?>
        <!-- Fixed dark overlay — background_color is admin-chosen and could be
             anything including white/light, but the text below is always
             white, so this guarantees readability regardless of that choice. -->
        <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,0,0,.45),rgba(0,0,0,.25))"></div>
        <div style="text-align:center;position:relative;z-index:1">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:3px;color:rgba(255,255,255,.7)">This certifies that</div>
          <div style="font-size:20px;font-weight:900;color:#fff;font-family:Georgia,serif;margin:6px 0">Student Name</div>
          <div style="font-size:11px;color:rgba(255,255,255,.8)">has successfully completed</div>
          <div style="font-size:14px;font-weight:700;color:#fff;margin-top:4px"><?= \App\Core\View::e($tpl['name']) ?></div>
        </div>
      </div>
      <div class="card-body">
        <div style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px"><?= \App\Core\View::e($tpl['name']) ?></div>
        <div style="font-size:12.5px;color:#94a3b8;margin-bottom:12px"><?= \App\Core\View::e($tpl['description'] ?? '') ?></div>
        <div class="d-flex gap-2">
          <?= \App\Core\View::badge($tpl['is_active'] ? 'active' : 'inactive') ?>
          <span style="font-size:12px;color:#94a3b8">Default: <?= $tpl['is_default'] ? '<span style="color:#10b981">Yes</span>' : 'No' ?></span>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button onclick="previewTemplate(<?= $tpl['id'] ?>)" class="btn btn-primary btn-sm flex-fill">
            <i class="fas fa-eye"></i> Preview
          </button>
          <button onclick="openEditTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)" class="btn btn-ghost btn-sm btn-icon" title="Edit">
            <i class="fas fa-edit"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Template Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tplModalTitle">New Template</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="tplForm" method="POST" action="/super-admin/certificates/templates" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" id="tplMethod" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Template Name</label>
                <input type="text" name="name" id="tplName" class="form-control" required placeholder="e.g. Standard Gold Certificate">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Background Color</label>
                <input type="color" name="background_color" id="tplBgColor" class="form-control form-control-color" value="#6366f1">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="tplDesc" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Header Text</label>
                <input type="text" name="header_text" id="tplHeader" class="form-control" placeholder="Certificate of Completion">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Signatory Name</label>
                <input type="text" name="signatory_name" id="tplSign" class="form-control" placeholder="Director / Dean">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Background Image</label>
                <input type="file" name="background_image" class="form-control" accept="image/*">
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center pt-4">
                <span style="font-size:13.5px;color:#374151">Set as Default</span>
                <label class="form-switch">
                  <input type="checkbox" name="is_default" id="tplDefault" value="1">
                  <span class="toggle-track"></span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="tplSubmit">Create Template</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openCreateTemplate() {
  document.getElementById('tplModalTitle').textContent = 'New Template';
  document.getElementById('tplMethod').value = '';
  document.getElementById('tplForm').action  = '/super-admin/certificates/templates';
  document.getElementById('tplSubmit').textContent = 'Create Template';
  document.getElementById('tplName').value = '';
  document.getElementById('tplDesc').value = '';
  new bootstrap.Modal(document.getElementById('tplModal')).show();
}

function openEditTemplate(tpl) {
  document.getElementById('tplModalTitle').textContent = 'Edit Template';
  document.getElementById('tplMethod').value = 'PUT';
  document.getElementById('tplForm').action  = `/super-admin/certificates/templates/${tpl.id}`;
  document.getElementById('tplName').value   = tpl.name;
  document.getElementById('tplDesc').value   = tpl.description || '';
  document.getElementById('tplHeader').value = tpl.header_text || '';
  document.getElementById('tplSign').value   = tpl.signatory_name || '';
  document.getElementById('tplDefault').checked = !!tpl.is_default;
  document.getElementById('tplSubmit').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('tplModal')).show();
}

function previewTemplate(id) {
  window.open(`/super-admin/certificates/templates/${id}/preview`, '_blank');
}
</script>
