<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/support">Support</a>
      <span class="sep">/</span>
      <span><?= \App\Core\View::e($ticket['ticket_number'] ?? '#' . $ticket['id']) ?></span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($ticket['subject']) ?></h1>
  </div>
  <div class="d-flex gap-2">
    <?php if ($ticket['status'] !== 'closed'): ?>
    <button onclick="closeTicket()" class="btn btn-danger btn-sm"><i class="fas fa-times-circle"></i> Close Ticket</button>
    <?php endif; ?>
    <button onclick="document.getElementById('assignModal').classList.remove('d-none')" class="btn btn-secondary btn-sm">
      <i class="fas fa-user-check"></i> Assign
    </button>
    <a href="/super-admin/support" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <!-- Thread -->
  <div class="col-xl-8">
    <!-- Original message -->
    <div class="card mb-3" style="border-left:4px solid #6366f1">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;background:#6366f118;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6366f1">
              <i class="fas fa-user"></i>
            </div>
            <div>
              <div style="font-weight:700;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($ticket['student_name']) ?></div>
              <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($ticket['created_at']) ?> · <?= \App\Core\View::formatDate($ticket['created_at'], 'd M Y, H:i') ?></div>
            </div>
          </div>
          <span class="badge" style="background:#6366f118;color:#6366f1">Original</span>
        </div>
        <div style="font-size:14px;line-height:1.75;color:#374151;white-space:pre-wrap"><?= nl2br(\App\Core\View::e($ticket['message'])) ?></div>

        <?php if ($ticket['attachment']): ?>
        <div class="mt-3" style="padding-top:12px;border-top:1px solid #f1f5f9">
          <a href="<?= \App\Core\View::e($ticket['attachment']) ?>" target="_blank" class="btn btn-ghost btn-sm">
            <i class="fas fa-paperclip"></i> View Attachment
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Replies -->
    <?php foreach ($replies as $r):
      $isAdmin = in_array($r['author_role'] ?? '', ['super_admin','admin','staff']);
      $bgColor = $isAdmin ? '#f0fdf4' : '#fafafa';
      $accentColor = $isAdmin ? '#10b981' : '#94a3b8';
    ?>
    <div class="card mb-3" style="border-left:4px solid <?= $accentColor ?>;background:<?= $bgColor ?>">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div style="width:32px;height:32px;background:<?= $accentColor ?>18;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;color:<?= $accentColor ?>">
            <i class="fas fa-<?= $isAdmin ? 'headset' : 'user' ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;color:#0f172a">
              <?= \App\Core\View::e($r['author_name']) ?>
              <?php if ($isAdmin): ?><span class="badge" style="background:<?= $accentColor ?>18;color:<?= $accentColor ?>;font-size:10.5px;margin-left:4px">Staff</span><?php endif; ?>
            </div>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::timeAgo($r['created_at']) ?></div>
          </div>
        </div>
        <div style="font-size:13.5px;line-height:1.75;color:#374151;white-space:pre-wrap"><?= nl2br(\App\Core\View::e($r['message'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Reply Form -->
    <?php if ($ticket['status'] !== 'closed'): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-reply" style="color:#6366f1"></i> Reply</h3></div>
      <div class="card-body">
        <form method="POST" action="/super-admin/support/<?= $ticket['id'] ?>/reply">
          <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
          <div class="form-group mb-3">
            <textarea name="message" class="form-control" rows="5" required placeholder="Write your reply…"></textarea>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
            <div class="form-check" style="margin:0">
              <input type="checkbox" name="resolve_after" id="resolveAfter" class="form-check-input" value="1">
              <label for="resolveAfter" class="form-check-label" style="font-size:13px;color:#64748b">Mark as resolved after reply</label>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="alert" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:16px;font-size:13px;color:#64748b">
      <i class="fas fa-lock"></i> This ticket is closed. No further replies can be added.
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar -->
  <div class="col-xl-4">
    <!-- Ticket Details -->
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title" style="font-size:13.5px">Ticket Details</h3></div>
      <div class="card-body" style="padding:12px 16px">
        <?php
        $prioColors = ['low'=>'#94a3b8','medium'=>'#f59e0b','high'=>'#ef4444','urgent'=>'#dc2626'];
        $pc = $prioColors[$ticket['priority']] ?? '#94a3b8';
        $details = [
          ['label'=>'Ticket #',   'value'=>$ticket['ticket_number'] ?? '#' . $ticket['id']],
          ['label'=>'Status',     'value'=>\App\Core\View::badge($ticket['status'] ?? 'open')],
          ['label'=>'Priority',   'value'=>"<span class='badge' style='background:{$pc}18;color:{$pc}'>".ucfirst($ticket['priority'])."</span>"],
          ['label'=>'Category',   'value'=>$ticket['category'] ?? '—'],
          ['label'=>'Student',    'value'=>\App\Core\View::e($ticket['student_name'])],
          ['label'=>'Assigned To','value'=>$ticket['assignee_name'] ? \App\Core\View::e($ticket['assignee_name']) : '<span style="color:#94a3b8">Unassigned</span>'],
          ['label'=>'Created',    'value'=>\App\Core\View::formatDate($ticket['created_at'], 'd M Y, H:i')],
          ['label'=>'Updated',    'value'=>\App\Core\View::timeAgo($ticket['updated_at'])],
        ];
        ?>
        <?php foreach ($details as $d): ?>
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid #f8fafc;font-size:13px">
          <span style="color:#94a3b8"><?= $d['label'] ?></span>
          <span style="font-weight:500;color:#374151"><?= $d['value'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card">
      <div class="card-header"><h3 class="card-title" style="font-size:13.5px">Timeline</h3></div>
      <div class="card-body" style="padding:12px 16px">
        <?php foreach ($timeline as $t): ?>
        <div class="d-flex gap-2 mb-3">
          <div style="width:24px;height:24px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94a3b8;flex-shrink:0">
            <i class="fas fa-circle"></i>
          </div>
          <div>
            <div style="font-size:12.5px;color:#374151"><?= \App\Core\View::e($t['description'] ?? '') ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::timeAgo($t['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($timeline)): ?>
        <p style="font-size:13px;color:#94a3b8;text-align:center">No timeline events yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModalWrapper" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/super-admin/support/<?= $ticket['id'] ?>/assign">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Assign To</label>
            <select name="assignee_id" class="form-select" required>
              <option value="">Select staff member…</option>
              <?php foreach ($staff as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $s['id'] == $ticket['assigned_to'] ? 'selected' : '' ?>>
                <?= \App\Core\View::e($s['first_name'] . ' ' . $s['last_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
async function closeTicket() {
  const r = await Swal.fire({
    title: 'Close Ticket?', text: 'This ticket will be marked as closed.',
    icon: 'question', showCancelButton: true, confirmButtonText: 'Close Ticket'
  });
  if (!r.isConfirmed) return;
  const d = await cgFetch('/super-admin/support/<?= $ticket['id'] ?>/close', { method: 'POST' });
  if (d.success) { CGToast.success('Ticket closed'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}
</script>
