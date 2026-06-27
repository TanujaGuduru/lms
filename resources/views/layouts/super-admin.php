<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrfToken ?>">
  <meta name="description" content="CodeGurukul LMS — Super Admin">
  <title><?= \App\Core\View::e($title ?? 'Super Admin') ?> — CodeGurukul</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

  <!-- Select2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

  <!-- Flatpickr -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

  <!-- App CSS -->
  <link rel="stylesheet" href="<?= \App\Core\View::asset('css/super-admin.css') ?>">

  <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<div class="app-wrapper">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="app-sidebar" id="appSidebar">

    <a href="/super-admin/dashboard" class="sidebar-logo">
      <div class="logo-icon">
        <i class="fas fa-graduation-cap" style="color:#fff"></i>
      </div>
      <div class="logo-text">
        <div class="logo-title">CodeGurukul</div>
        <div class="logo-subtitle">LMS Platform</div>
      </div>
    </a>

    <nav class="sidebar-nav" id="sidebarNav">

      <!-- Dashboard -->
      <div class="nav-section">
        <div class="nav-section-label">Overview</div>
        <a href="/super-admin/dashboard" class="nav-link <?= \App\Core\View::active('/super-admin/dashboard') ?>">
          <span class="nav-icon"><i class="fas fa-chart-pie"></i></span>
          <span class="nav-label">Dashboard</span>
        </a>
      </div>

      <!-- User Management -->
      <div class="nav-section">
        <div class="nav-section-label">People</div>

        <div class="nav-item">
          <a href="#nav-users" class="nav-link <?= \App\Core\View::active('/super-admin/users', 'active') ?> <?= \App\Core\View::active('/super-admin/roles', 'active') ?>"
             data-bs-toggle="collapse" aria-expanded="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/users') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/roles') ? 'true' : 'false' ?>">
            <span class="nav-icon"><i class="fas fa-users"></i></span>
            <span class="nav-label">User Management</span>
            <i class="fas fa-chevron-right nav-arrow"></i>
          </a>
          <div class="collapse nav-submenu <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/users') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/roles') ? 'show' : '' ?>" id="nav-users">
            <a href="/super-admin/users?role_id=2" class="nav-link <?= \App\Core\View::active('/super-admin/users') ?>"><span class="nav-label">Admins</span></a>
            <a href="/super-admin/users?role_id=3" class="nav-link"><span class="nav-label">Teachers</span></a>
            <a href="/super-admin/users?role_id=4" class="nav-link"><span class="nav-label">Students</span></a>
            <a href="/super-admin/users?role_id=5" class="nav-link"><span class="nav-label">Parents</span></a>
            <a href="/super-admin/roles" class="nav-link <?= \App\Core\View::active('/super-admin/roles') ?>"><span class="nav-label">Roles & Permissions</span></a>
          </div>
        </div>
      </div>

      <!-- Academic -->
      <div class="nav-section">
        <div class="nav-section-label">Academics</div>

        <div class="nav-item">
          <a href="#nav-academic" class="nav-link" data-bs-toggle="collapse"
             aria-expanded="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/course') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/batch') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/live-class') ? 'true' : 'false' ?>">
            <span class="nav-icon"><i class="fas fa-book-open"></i></span>
            <span class="nav-label">Academic Mgmt</span>
            <i class="fas fa-chevron-right nav-arrow"></i>
          </a>
          <div class="collapse nav-submenu <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/course') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/batch') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/live-class') ? 'show' : '' ?>" id="nav-academic">
            <a href="/super-admin/courses"      class="nav-link <?= \App\Core\View::active('/super-admin/courses') ?>"><span class="nav-label">Courses</span></a>
            <a href="/super-admin/batches"      class="nav-link <?= \App\Core\View::active('/super-admin/batches') ?>"><span class="nav-label">Batches</span></a>
            <a href="/super-admin/live-classes"  class="nav-link <?= \App\Core\View::active('/super-admin/live-classes') ?>"><span class="nav-label">Live Classes</span></a>
            <a href="/super-admin/departments"  class="nav-link <?= \App\Core\View::active('/super-admin/departments') ?>"><span class="nav-label">Departments</span></a>
          </div>
        </div>

        <div class="nav-item">
          <a href="#nav-assessment" class="nav-link" data-bs-toggle="collapse"
             aria-expanded="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/exam') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/assignment') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/question') ? 'true' : 'false' ?>">
            <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
            <span class="nav-label">Assessments</span>
            <i class="fas fa-chevron-right nav-arrow"></i>
          </a>
          <div class="collapse nav-submenu <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/exam') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/question') ? 'show' : '' ?>" id="nav-assessment">
            <a href="/super-admin/exams"         class="nav-link"><span class="nav-label">Exams</span></a>
            <a href="/super-admin/assignments"   class="nav-link"><span class="nav-label">Assignments</span></a>
            <a href="/super-admin/question-bank" class="nav-link"><span class="nav-label">Question Bank</span></a>
            <a href="/super-admin/certificates"  class="nav-link"><span class="nav-label">Certificates</span></a>
          </div>
        </div>
      </div>

      <!-- Communication -->
      <div class="nav-section">
        <div class="nav-section-label">Communication</div>
        <a href="/super-admin/announcements" class="nav-link <?= \App\Core\View::active('/super-admin/announcements') ?>">
          <span class="nav-icon"><i class="fas fa-bullhorn"></i></span>
          <span class="nav-label">Announcements</span>
        </a>
        <a href="/super-admin/events" class="nav-link <?= \App\Core\View::active('/super-admin/events') ?>">
          <span class="nav-icon"><i class="fas fa-calendar-alt"></i></span>
          <span class="nav-label">Events</span>
        </a>
        <a href="/super-admin/support" class="nav-link <?= \App\Core\View::active('/super-admin/support') ?>">
          <span class="nav-icon"><i class="fas fa-headset"></i></span>
          <span class="nav-label">Support Center</span>
          <?php
            $openTickets = \App\Core\Database::getInstance()->selectOne("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'");
            if (($openTickets['c'] ?? 0) > 0):
          ?>
          <span class="nav-badge"><?= $openTickets['c'] ?></span>
          <?php endif; ?>
        </a>
      </div>

      <!-- Finance -->
      <div class="nav-section">
        <div class="nav-section-label">Finance & Growth</div>
        <div class="nav-item">
          <a href="#nav-finance" class="nav-link" data-bs-toggle="collapse"
             aria-expanded="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/finance') ? 'true' : 'false' ?>">
            <span class="nav-icon"><i class="fas fa-rupee-sign"></i></span>
            <span class="nav-label">Finance</span>
            <i class="fas fa-chevron-right nav-arrow"></i>
          </a>
          <div class="collapse nav-submenu <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super-admin/finance') ? 'show' : '' ?>" id="nav-finance">
            <a href="/super-admin/finance"             class="nav-link"><span class="nav-label">Overview</span></a>
            <a href="/super-admin/finance/payments"    class="nav-link"><span class="nav-label">Payments</span></a>
            <a href="/super-admin/finance/fee-structures" class="nav-link"><span class="nav-label">Fee Structures</span></a>
            <a href="/super-admin/finance/discounts"   class="nav-link"><span class="nav-label">Discounts</span></a>
            <a href="/super-admin/finance/scholarships" class="nav-link"><span class="nav-label">Scholarships</span></a>
          </div>
        </div>

        <a href="/super-admin/placement" class="nav-link <?= \App\Core\View::active('/super-admin/placement') ?>">
          <span class="nav-icon"><i class="fas fa-briefcase"></i></span>
          <span class="nav-label">Placement</span>
        </a>
      </div>

      <!-- Analytics -->
      <div class="nav-section">
        <div class="nav-section-label">Analytics</div>
        <a href="/super-admin/reports" class="nav-link <?= \App\Core\View::active('/super-admin/reports') ?>">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span class="nav-label">Reports</span>
        </a>
        <a href="/super-admin/ai-center" class="nav-link <?= \App\Core\View::active('/super-admin/ai-center') ?>">
          <span class="nav-icon"><i class="fas fa-robot"></i></span>
          <span class="nav-label">AI Center</span>
          <span class="nav-badge" style="background:linear-gradient(135deg,#f59e0b,#ef4444)">AI</span>
        </a>
      </div>

      <!-- System -->
      <div class="nav-section">
        <div class="nav-section-label">System</div>
        <a href="/super-admin/security" class="nav-link <?= \App\Core\View::active('/super-admin/security') ?>">
          <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
          <span class="nav-label">Security</span>
        </a>
        <a href="/super-admin/audit-logs" class="nav-link <?= \App\Core\View::active('/super-admin/audit-logs') ?>">
          <span class="nav-icon"><i class="fas fa-history"></i></span>
          <span class="nav-label">Audit Logs</span>
        </a>
        <a href="/super-admin/backup" class="nav-link <?= \App\Core\View::active('/super-admin/backup') ?>">
          <span class="nav-icon"><i class="fas fa-database"></i></span>
          <span class="nav-label">Backup</span>
        </a>
        <a href="/super-admin/integrations" class="nav-link <?= \App\Core\View::active('/super-admin/integrations') ?>">
          <span class="nav-icon"><i class="fas fa-plug"></i></span>
          <span class="nav-label">Integrations</span>
        </a>
        <a href="/super-admin/settings" class="nav-link <?= \App\Core\View::active('/super-admin/settings') ?>">
          <span class="nav-icon"><i class="fas fa-cog"></i></span>
          <span class="nav-label">Settings</span>
        </a>
      </div>

    </nav>

    <!-- Sidebar User -->
    <div class="sidebar-footer">
      <a href="/super-admin/profile" class="sidebar-user">
        <img src="<?= \App\Core\View::e((new \App\Models\User())->avatarUrl($currentUser ?? [])) ?>"
             alt="Avatar" class="sidebar-user-avatar">
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= \App\Core\View::e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?></div>
          <div class="sidebar-user-role"><?= \App\Core\View::e(ucwords(str_replace('_', ' ', $currentUser['role_slug'] ?? ''))) ?></div>
        </div>
      </a>
    </div>

  </aside><!-- /sidebar -->

  <!-- ═══════════ MAIN CONTENT ═══════════ -->
  <div class="main-content" id="mainContent">

    <!-- Topbar -->
    <header class="app-topbar" id="appTopbar">
      <button class="topbar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
      </button>

      <div class="topbar-search position-relative">
        <span class="search-icon position-absolute" style="top:50%;left:11px;transform:translateY(-50%);">
          <i class="fas fa-search" style="color:#94a3b8;font-size:12px"></i>
        </span>
        <input type="text" class="search-input" id="globalSearch" placeholder="Search users, courses, batches…"
               autocomplete="off" spellcheck="false">
        <div class="search-results-dropdown" id="searchResults"
             style="display:none;position:absolute;top:calc(100%+6px);left:0;right:0;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.12);border:1px solid #e2e8f0;z-index:1200;max-height:400px;overflow-y:auto;"></div>
      </div>

      <div class="topbar-spacer"></div>

      <div class="topbar-actions">

        <!-- Notifications -->
        <div class="position-relative">
          <button class="topbar-btn" id="notifToggle" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="badge-dot" id="notifDot" style="display:none"></span>
          </button>
          <div class="dropdown-menu-custom notification-panel" id="notifDropdown" style="display:none;top:calc(100%+8px)">
            <div class="dropdown-header d-flex justify-content-between align-items-center py-3 px-4">
              <span style="font-size:14px;font-weight:700;color:#0f172a">Notifications</span>
              <button class="btn btn-ghost btn-sm" id="markAllReadBtn" style="font-size:12px">Mark all read</button>
            </div>
            <div id="notifList" style="max-height:360px;overflow-y:auto;">
              <div class="empty-state" style="padding:30px 20px">
                <i class="fas fa-bell-slash empty-state-icon" style="font-size:32px"></i>
                <p style="font-size:13px;color:#64748b;margin-top:8px">No notifications</p>
              </div>
            </div>
            <div class="card-footer text-center">
              <a href="/super-admin/notifications" style="font-size:13px;color:#6366f1;font-weight:600;text-decoration:none">View all notifications →</a>
            </div>
          </div>
        </div>

        <!-- Quick Add -->
        <div class="position-relative">
          <button class="topbar-btn" id="quickAddBtn" title="Quick Add">
            <i class="fas fa-plus"></i>
          </button>
          <div class="dropdown-menu-custom" id="quickAddDropdown" style="display:none;min-width:180px">
            <div class="dropdown-header">Quick Add</div>
            <a href="/super-admin/users/create"       class="dropdown-item-custom"><i class="fas fa-user-plus" style="color:#6366f1;width:16px"></i> Add User</a>
            <a href="/super-admin/courses/create"     class="dropdown-item-custom"><i class="fas fa-book"     style="color:#06b6d4;width:16px"></i> Create Course</a>
            <a href="/super-admin/batches/create"     class="dropdown-item-custom"><i class="fas fa-users"    style="color:#10b981;width:16px"></i> Create Batch</a>
            <a href="/super-admin/announcements/create" class="dropdown-item-custom"><i class="fas fa-bullhorn" style="color:#f59e0b;width:16px"></i> Announcement</a>
          </div>
        </div>

        <a href="/super-admin/settings" class="topbar-btn" title="Settings"><i class="fas fa-cog"></i></a>

        <!-- User menu -->
        <div class="position-relative">
          <button class="topbar-user" id="userMenuToggle">
            <img src="<?= \App\Core\View::e((new \App\Models\User())->avatarUrl($currentUser ?? [])) ?>" alt="Avatar">
            <div class="topbar-user-info d-none d-md-block">
              <div class="topbar-user-name"><?= \App\Core\View::e($currentUser['first_name'] ?? 'Admin') ?></div>
              <div class="topbar-user-role"><?= \App\Core\View::e(ucwords(str_replace('_', ' ', $currentUser['role_slug'] ?? ''))) ?></div>
            </div>
            <i class="fas fa-chevron-down ms-1" style="font-size:10px;color:#94a3b8"></i>
          </button>
          <div class="dropdown-menu-custom" id="userMenuDropdown" style="display:none;min-width:200px">
            <div class="dropdown-header">
              <?= \App\Core\View::e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>
            </div>
            <a href="/super-admin/profile" class="dropdown-item-custom"><i class="fas fa-user" style="width:16px;color:#6366f1"></i> My Profile</a>
            <a href="/super-admin/settings" class="dropdown-item-custom"><i class="fas fa-cog" style="width:16px;color:#64748b"></i> Settings</a>
            <div class="dropdown-divider"></div>
            <a href="/logout" class="dropdown-item-custom danger"><i class="fas fa-sign-out-alt" style="width:16px"></i> Sign Out</a>
          </div>
        </div>

      </div>
    </header><!-- /topbar -->

    <!-- Flash Messages -->
    <div id="flashContainer" style="position:fixed;top:74px;right:20px;z-index:1500;width:360px;pointer-events:none">
      <?php if ($flashSuccess): ?>
      <div class="alert alert-success" role="alert" style="pointer-events:all">
        <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
        <div class="alert-content"><?= \App\Core\View::e($flashSuccess) ?></div>
        <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
      </div>
      <?php endif; ?>
      <?php if ($flashError): ?>
      <div class="alert alert-danger" role="alert" style="pointer-events:all">
        <span class="alert-icon"><i class="fas fa-exclamation-circle"></i></span>
        <div class="alert-content"><?= \App\Core\View::e($flashError) ?></div>
        <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
      </div>
      <?php endif; ?>
      <?php if ($flashWarning): ?>
      <div class="alert alert-warning" role="alert" style="pointer-events:all">
        <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
        <div class="alert-content"><?= \App\Core\View::e($flashWarning) ?></div>
        <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
      </div>
      <?php endif; ?>
    </div>

    <!-- Page Content -->
    <main class="content-area">
      <?php include $__contentFile ?? ''; ?>
    </main>

  </div><!-- /main-content -->

</div><!-- /app-wrapper -->

<!-- Mobile sidebar overlay -->
<div class="d-none" id="sidebarOverlay"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;backdrop-filter:blur(2px)"
     onclick="closeMobileSidebar()"></div>

<!-- ═══════════ SCRIPTS ═══════════ -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script src="<?= \App\Core\View::asset('js/super-admin.js') ?>"></script>

<script>
const CG = {
  csrf: '<?= $csrfToken ?>',
  baseUrl: '<?= BASE_URL ?>',
  user: {
    id:   <?= (int)($currentUser['id'] ?? 0) ?>,
    name: '<?= \App\Core\View::e(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>',
    role: '<?= \App\Core\View::e($currentUser['role_slug'] ?? '') ?>'
  }
};
</script>

<?php if (isset($extraJs)) echo $extraJs; ?>

</body>
</html>
