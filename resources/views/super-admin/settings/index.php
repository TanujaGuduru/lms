<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Settings</span>
    </div>
    <h1 class="page-title">Platform Settings</h1>
    <p class="page-subtitle">Configure every aspect of your CodeGurukul LMS</p>
  </div>
</div>

<div class="row g-3">
  <!-- Settings Sidebar -->
  <div class="col-xl-3 col-md-4">
    <div class="card" style="position:sticky;top:80px">
      <div class="card-body p-2">
        <?php
        $settingGroups = [
          ['key'=>'general',    'label'=>'General',      'icon'=>'fas fa-cog',           'color'=>'#6366f1'],
          ['key'=>'email',      'label'=>'Email (SMTP)',  'icon'=>'fas fa-envelope',      'color'=>'#06b6d4'],
          ['key'=>'sms',        'label'=>'SMS',           'icon'=>'fas fa-sms',           'color'=>'#10b981'],
          ['key'=>'payment',    'label'=>'Payment',       'icon'=>'fas fa-credit-card',   'color'=>'#f59e0b'],
          ['key'=>'security',   'label'=>'Security',      'icon'=>'fas fa-shield-alt',    'color'=>'#ef4444'],
          ['key'=>'ai',         'label'=>'AI Settings',   'icon'=>'fas fa-robot',         'color'=>'#8b5cf6'],
          ['key'=>'storage',    'label'=>'Storage',       'icon'=>'fas fa-cloud',         'color'=>'#0891b2'],
          ['key'=>'branding',   'label'=>'Branding',      'icon'=>'fas fa-paint-brush',   'color'=>'#ec4899'],
        ];
        $activeGroup = $group ?? 'general';
        ?>
        <div class="d-flex flex-column gap-1">
          <?php foreach ($settingGroups as $sg): ?>
          <a href="/super-admin/settings/<?= $sg['key'] ?>"
             class="nav-link <?= $activeGroup === $sg['key'] ? 'active' : '' ?>"
             style="border-radius:8px;margin:0;font-size:13.5px">
            <span class="nav-icon" style="color:<?= $sg['color'] ?>"><i class="<?= $sg['icon'] ?>"></i></span>
            <span class="nav-label"><?= $sg['label'] ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Settings Panel -->
  <div class="col-xl-9 col-md-8">
    <div class="card">
      <div class="card-header">
        <?php
        $grpInfo = array_filter($settingGroups, fn($g) => $g['key'] === $activeGroup);
        $grpInfo = array_values($grpInfo)[0] ?? $settingGroups[0];
        ?>
        <h3 class="card-title">
          <div class="card-icon" style="background:<?= $grpInfo['color'] ?>18;color:<?= $grpInfo['color'] ?>">
            <i class="<?= $grpInfo['icon'] ?>"></i>
          </div>
          <?= $grpInfo['label'] ?> Settings
        </h3>
      </div>

      <form method="POST" action="/super-admin/settings/<?= $activeGroup ?>/save" id="settingsForm" data-loading="Saving…">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

        <div class="card-body">
          <?php
          $db       = \App\Core\Database::getInstance();
          $settings = $db->select("SELECT * FROM settings WHERE `group` = ? ORDER BY id", [$activeGroup]);
          ?>

          <?php if (empty($settings)): ?>
          <div class="empty-state" style="padding:40px">
            <i class="fas fa-cog empty-state-icon"></i>
            <p class="empty-state-desc">No settings found for this group.</p>
          </div>
          <?php else: ?>

          <div class="row g-3">
            <?php foreach ($settings as $s): ?>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">
                  <?= \App\Core\View::e($s['label']) ?>
                  <?php if ($s['is_public']): ?><span title="Public setting" style="font-size:10px;color:#94a3b8;font-weight:400"> (public)</span><?php endif; ?>
                </label>

                <?php if ($s['type'] === 'boolean'): ?>
                  <label class="form-switch">
                    <input type="hidden"   name="<?= $s['key'] ?>" value="0">
                    <input type="checkbox" name="<?= $s['key'] ?>" value="1" <?= $s['value'] === '1' ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                    <span style="font-size:13px;color:#64748b"><?= $s['value'] === '1' ? 'Enabled' : 'Disabled' ?></span>
                  </label>

                <?php elseif ($s['type'] === 'textarea'): ?>
                  <textarea name="<?= $s['key'] ?>" class="form-control" rows="3"><?= \App\Core\View::e($s['value']) ?></textarea>

                <?php elseif ($s['type'] === 'select'): ?>
                  <select name="<?= $s['key'] ?>" class="form-select">
                    <?php foreach (json_decode($s['description'] ?? '[]', true) ?: [] as $opt): ?>
                    <option value="<?= $opt['value'] ?>" <?= $s['value'] === $opt['value'] ? 'selected' : '' ?>><?= \App\Core\View::e($opt['label']) ?></option>
                    <?php endforeach; ?>
                  </select>

                <?php elseif ($s['type'] === 'color'): ?>
                  <div class="d-flex gap-2 align-items-center">
                    <input type="color" name="<?= $s['key'] ?>" value="<?= \App\Core\View::e($s['value'] ?: '#6366f1') ?>" style="width:44px;height:38px;border-radius:8px;border:1.5px solid #e2e8f0;cursor:pointer;padding:2px">
                    <input type="text" value="<?= \App\Core\View::e($s['value'] ?: '#6366f1') ?>" class="form-control" style="width:120px" placeholder="#6366f1">
                  </div>

                <?php elseif ($s['type'] === 'file'): ?>
                  <div class="d-flex flex-column gap-2">
                    <?php if ($s['value']): ?>
                    <img src="<?= \App\Core\View::e($s['value']) ?>" style="max-height:60px;border-radius:8px;border:1px solid #e2e8f0;padding:4px;background:#f8fafc">
                    <?php endif; ?>
                    <input type="file" name="<?= $s['key'] ?>_file" class="form-control" accept="image/*">
                  </div>

                <?php elseif ($s['type'] === 'integer'): ?>
                  <input type="number" name="<?= $s['key'] ?>" class="form-control" value="<?= \App\Core\View::e($s['value']) ?>" min="0">

                <?php else: ?>
                  <input type="<?= in_array($s['key'], ['smtp_pass','razorpay_key_secret']) ? 'password' : 'text' ?>"
                         name="<?= $s['key'] ?>" class="form-control"
                         value="<?= \App\Core\View::e($s['value']) ?>"
                         placeholder="<?= \App\Core\View::e($s['description'] ?? '') ?>">
                <?php endif; ?>

                <?php if ($s['description'] && !in_array($s['type'], ['select'])): ?>
                <div class="form-hint"><?= \App\Core\View::e($s['description']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <?php endif; ?>
        </div>

        <?php if (!empty($settings)): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <div>
            <?php if ($activeGroup === 'email'): ?>
            <button type="button" class="btn btn-secondary btn-sm" onclick="testEmailSettings()">
              <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </div>
        <?php endif; ?>
      </form>

    </div>
  </div>
</div>

<script>
function testEmailSettings() {
  const email = prompt('Enter email address to send test to:');
  if (!email) return;
  cgFetch('/api/v1/settings/test-email', {
    method: 'POST',
    body: JSON.stringify({ email })
  }).then(d => {
    if (d.success) CGToast.success('Test email sent to ' + email);
    else CGToast.error(d.message);
  });
}

// Color picker sync
document.querySelectorAll('input[type="color"]').forEach(cp => {
  const text = cp.nextElementSibling;
  if (!text) return;
  cp.addEventListener('input',  () => { if (text) text.value = cp.value; });
  text.addEventListener('input', () => { if (text.value.match(/^#[0-9a-f]{6}$/i)) cp.value = text.value; });
});
</script>
