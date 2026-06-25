<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class CertificateController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('certificates.view');

        $where  = ['c.is_revoked = 0'];
        $params = [];

        if ($s = $request->input('search')) {
            $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR c.certificate_number LIKE ?)";
            $params  = array_merge($params, ["%{$s}%", "%{$s}%"]);
        }
        if ($courseId = $request->input('course_id')) {
            $where[] = "c.course_id = ?"; $params[] = (int)$courseId;
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT c.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email student_email,
             co.title course_title
             FROM certificates c
             JOIN users u ON u.id = c.user_id
             JOIN courses co ON co.id = c.course_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.issued_at DESC",
            $params, $page, 25
        );

        $stats = $this->db->selectOne(
            "SELECT COUNT(*) total,
             SUM(is_revoked=0) active,
             SUM(is_revoked=1) revoked,
             SUM(DATE(issued_at)=CURDATE()) today
             FROM certificates"
        ) ?: [];

        $courses = $this->db->select("SELECT id, title FROM courses WHERE status='published' AND deleted_at IS NULL ORDER BY title");

        $this->render('super-admin.certificates.index', [
            'title'        => 'Certificates',
            'certificates' => $result['data'],
            'meta'         => $result,
            'stats'        => $stats,
            'courses'      => $courses,
            'filters'      => $request->only(['search','course_id']),
        ]);
    }

    public function templates(Request $request): void
    {
        $this->authorize('certificates.view');

        $templates = $this->db->select("SELECT * FROM certificate_templates ORDER BY created_at DESC");
        $this->render('super-admin.certificates.templates', [
            'title'     => 'Certificate Templates',
            'templates' => $templates,
        ]);
    }

    public function createTemplate(Request $request): void
    {
        $this->authorize('certificates.create');
        $this->render('super-admin.certificates.create-template', ['title' => 'New Template']);
    }

    public function storeTemplate(Request $request): void
    {
        $this->authorize('certificates.create');

        $data = $this->validate($request, [
            'name'        => 'required|min:3|max:150',
            'orientation' => 'required|in:landscape,portrait',
            'paper_size'  => 'required|in:A4,A3,Letter',
        ]);

        $data['html_template'] = $request->input('template_html', '');
        $data['variables']     = json_encode($request->input('variables', []));
        $data['is_default']    = $request->has('is_default') ? 1 : 0;
        $data['created_by']    = $this->currentUser()['id'];

        if ($data['is_default']) {
            $this->db->query("UPDATE certificate_templates SET is_default=0");
        }

        $id = $this->db->insert(
            "INSERT INTO certificate_templates (name,orientation,paper_size,html_template,variables,is_default,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())",
            [$data['name'],$data['orientation'],$data['paper_size'],$data['html_template'],$data['variables'],$data['is_default'],$data['created_by']]
        );

        AuditLogger::log('cert_template_created', 'certificates', (string)$id);
        $this->withFlash('success', 'Template created.')->redirect('/super-admin/certificates/templates');
    }

    public function issue(Request $request, int $userId, int $courseId): never
    {
        $this->authorize('certificates.create');

        $enrollment = $this->db->selectOne(
            "SELECT * FROM enrollments WHERE user_id=? AND course_id=? AND completed_at IS NOT NULL",
            [$userId, $courseId]
        );
        if (!$enrollment) $this->error('Student has not completed this course.');

        $exists = $this->db->selectOne(
            "SELECT id FROM certificates WHERE user_id=? AND course_id=? AND is_revoked=0",
            [$userId, $courseId]
        );
        if ($exists) $this->error('Certificate already issued.');

        $template = $this->db->selectOne("SELECT * FROM certificate_templates WHERE is_default=1") ?:
                    $this->db->selectOne("SELECT * FROM certificate_templates LIMIT 1");

        if (!$template) $this->error('No certificate template configured. Create one first.');

        $certNumber = 'CG-' . strtoupper(substr(md5($userId . $courseId . time()), 0, 8));
        $verifyCode = bin2hex(random_bytes(16));

        $id = $this->db->insert(
            "INSERT INTO certificates (user_id,course_id,template_id,certificate_number,verification_code,issued_at,issued_by)
             VALUES (?,?,?,?,?,NOW(),?)",
            [$userId, $courseId, $template['id'], $certNumber, $verifyCode, $this->currentUser()['id']]
        );

        AuditLogger::log('certificate_issued', 'certificates', (string)$id, null, ['user_id'=>$userId,'course_id'=>$courseId]);

        $course = $this->db->selectOne("SELECT title FROM courses WHERE id = ?", [$courseId]);
        NotificationService::send(
            $userId,
            'certificate.issued',
            'Your Certificate is Ready',
            "Your certificate for \"{$course['title']}\" has been issued. Certificate #{$certNumber}.",
            ['certificate_id' => $id, 'cert_number' => $certNumber, 'verify_code' => $verifyCode]
        );

        $this->success(['id'=>$id,'cert_number'=>$certNumber,'verify_code'=>$verifyCode], 'Certificate issued.');
    }

    public function revoke(Request $request, int $id): never
    {
        $this->authorize('certificates.delete');

        $reason = $request->input('reason', 'Administrative action');
        $this->db->query(
            "UPDATE certificates SET is_revoked=1, revoke_reason=?, revoked_at=NOW() WHERE id=?",
            [$reason, $id]
        );
        AuditLogger::log('certificate_revoked', 'certificates', (string)$id, null, ['reason'=>$reason]);
        $this->success(null, 'Certificate revoked.');
    }
}
