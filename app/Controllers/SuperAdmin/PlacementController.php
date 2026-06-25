<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Services\NotificationService;

class PlacementController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('placement.view');

        $stats = $this->db->selectOne(
            "SELECT
             (SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL) companies,
             (SELECT COUNT(*) FROM job_openings WHERE status='active') open_jobs,
             (SELECT COUNT(*) FROM placement_applications) applications,
             (SELECT COUNT(*) FROM placement_applications WHERE status='accepted') placed,
             (SELECT AVG(ctc_offered) FROM placement_applications WHERE status='accepted' AND ctc_offered > 0) avg_salary"
        ) ?: [];

        $recentPlacements = $this->db->select(
            "SELECT pa.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.avatar,
             jo.title job_title, c.name company_name, c.logo company_logo
             FROM placement_applications pa
             JOIN users u ON u.id = pa.student_id
             JOIN job_openings jo ON jo.id = pa.job_id
             JOIN companies c ON c.id = jo.company_id
             WHERE pa.status = 'accepted'
             ORDER BY pa.last_updated_at DESC LIMIT 10"
        );

        $topCompanies = $this->db->select(
            "SELECT c.*, COUNT(DISTINCT jo.id) jobs, COUNT(DISTINCT pa.id) applications
             FROM companies c
             LEFT JOIN job_openings jo ON jo.company_id=c.id
             LEFT JOIN placement_applications pa ON pa.job_id=jo.id
             WHERE c.deleted_at IS NULL
             GROUP BY c.id ORDER BY applications DESC LIMIT 8"
        );

        $this->render('super-admin.placement.index', [
            'title'            => 'Placement Center',
            'stats'            => $stats,
            'recentPlacements' => $recentPlacements,
            'topCompanies'     => $topCompanies,
        ]);
    }

    public function companies(Request $request): void
    {
        $this->authorize('placement.view');

        $where  = ['c.deleted_at IS NULL'];
        $params = [];
        if ($s = $request->input('search')) {
            $where[] = "(c.name LIKE ? OR c.industry LIKE ?)";
            $params  = array_merge($params, ["%{$s}%", "%{$s}%"]);
        }

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT c.*,
             (SELECT COUNT(*) FROM job_openings jo WHERE jo.company_id=c.id AND jo.status='active') open_jobs,
             (SELECT COUNT(*) FROM placement_applications pa JOIN job_openings jo2 ON jo2.id=pa.job_id WHERE jo2.company_id=c.id AND pa.status='accepted') placed_count
             FROM companies c
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.name",
            $params, $page, 20
        );

        $this->render('super-admin.placement.companies', [
            'title'     => 'Companies',
            'companies' => $result['data'],
            'meta'      => $result,
            'filters'   => $request->only(['search']),
        ]);
    }

    public function storeCompany(Request $request): void
    {
        $this->authorize('placement.create');

        $data = $this->validate($request, [
            'name'     => 'required|min:2|max:150',
            'industry' => 'max:100',
            'website'  => 'url',
        ]);

        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','svg','webp'], true)) {
                $filename = 'company_' . time() . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/companies/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $data['logo'] = '/uploads/companies/' . $filename;
                }
            }
        }

        $data['created_by'] = $this->currentUser()['id'];
        $id = $this->db->insert(
            "INSERT INTO companies (name,industry,website,logo,description,created_by,created_at)
             VALUES (?,?,?,?,?,?,NOW())",
            [$data['name'],$data['industry']??'',$data['website']??'',$data['logo']??'',$request->input('description',''),$data['created_by']]
        );

        AuditLogger::log('company_created', 'placement', (string)$id, null, $data);
        $this->withFlash('success', "Company \"{$data['name']}\" added.")->redirect('/super-admin/placement/companies');
    }

    public function jobs(Request $request): void
    {
        $this->authorize('placement.view');

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT jo.*, c.name company_name, c.logo company_logo,
             COUNT(DISTINCT pa.id) application_count
             FROM job_openings jo
             JOIN companies c ON c.id = jo.company_id
             LEFT JOIN placement_applications pa ON pa.job_id = jo.id
             WHERE jo.deleted_at IS NULL
             GROUP BY jo.id ORDER BY jo.created_at DESC",
            [], $page, 20
        );

        $companies = $this->db->select("SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name");

        $this->render('super-admin.placement.jobs', [
            'title'     => 'Job Openings',
            'jobs'      => $result['data'],
            'meta'      => $result,
            'companies' => $companies,
            'filters'   => $request->only(['search','company_id','status']),
        ]);
    }

    public function storeJob(Request $request): void
    {
        $this->authorize('placement.create');

        $data = $this->validate($request, [
            'title'      => 'required|min:3|max:200',
            'company_id' => 'required|integer',
            'type'       => 'required|in:full_time,part_time,internship,contract',
            'location'   => 'max:150',
        ]);

        $data['created_by']          = $this->currentUser()['id'];
        $data['status']              = 'active';
        $data['application_deadline'] = $request->input('deadline');
        $data['salary_min']          = $request->input('min_salary', 0);
        $data['salary_max']          = $request->input('max_salary', 0);
        $data['description']         = $request->input('description', '');
        $data['requirements']        = $request->input('requirements', '');

        $id = $this->db->insert(
            "INSERT INTO job_openings (title,company_id,type,location,description,requirements,salary_min,salary_max,application_deadline,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [$data['title'],$data['company_id'],$data['type'],$data['location']??'',$data['description'],$data['requirements'],$data['salary_min'],$data['salary_max'],$data['application_deadline']??null,$data['status'],$data['created_by']]
        );

        AuditLogger::log('job_created', 'placement', (string)$id, null, $data);
        $this->withFlash('success', 'Job opening created.')->redirect('/super-admin/placement/jobs');
    }

    public function applications(Request $request): void
    {
        $this->authorize('placement.view');

        $page   = max(1, (int)$request->input('page', 1));
        $result = $this->db->paginate(
            "SELECT pa.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email,
             jo.title job_title, c.name company_name
             FROM placement_applications pa
             JOIN users u ON u.id = pa.student_id
             JOIN job_openings jo ON jo.id = pa.job_id
             JOIN companies c ON c.id = jo.company_id
             ORDER BY pa.applied_at DESC",
            [], $page, 25
        );

        $companies = $this->db->select("SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name");

        $this->render('super-admin.placement.applications', [
            'title'        => 'Applications',
            'applications' => $result['data'],
            'meta'         => $result,
            'companies'    => $companies,
            'filters'      => $request->only(['search','status','company_id']),
        ]);
    }

    public function updateCompany(Request $request, int $id): void
    {
        $this->authorize('placement.create');
        $data = $this->validate($request, ['name' => 'required|min:2|max:150']);

        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','svg','webp'], true)) {
                $filename = 'company_' . time() . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/companies/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $data['logo'] = '/uploads/companies/' . $filename;
                }
            }
        }

        $this->db->query(
            "UPDATE companies SET name=?,industry=?,website=?,description=?,contact_email=?,is_active=?,updated_at=NOW()" .
            (isset($data['logo']) ? ',logo=?' : '') . " WHERE id=?",
            array_filter([
                $data['name'], $request->input('industry',''), $request->input('website',''),
                $request->input('about',''), $request->input('hr_email',''),
                $request->input('is_active',0) ? 1 : 0,
                $data['logo'] ?? null, $id,
            ], fn($v) => $v !== null)
        );

        AuditLogger::log('company_updated', 'placement', (string)$id);
        $this->withFlash('success', 'Company updated.')->redirect('/super-admin/placement/companies');
    }

    public function updateJob(Request $request, int $id): void
    {
        $this->authorize('placement.create');
        $data = $this->validate($request, ['title' => 'required|min:3|max:200']);

        $this->db->query(
            "UPDATE job_openings SET title=?,company_id=?,location=?,type=?,salary_min=?,salary_max=?,application_deadline=?,description=?,updated_at=NOW() WHERE id=?",
            [
                $data['title'], $request->input('company_id'), $request->input('location',''),
                $request->input('job_type','full_time'), $request->input('salary_min',0),
                $request->input('salary_max',0), $request->input('deadline') ?: null,
                $request->input('description',''), $id,
            ]
        );

        AuditLogger::log('job_updated', 'placement', (string)$id);
        $this->withFlash('success', 'Job updated.')->redirect('/super-admin/placement/jobs');
    }

    public function updateApplicationStatus(Request $request, int $id): never
    {
        $this->authorize('placement.create');
        $status  = $request->input('status', '');
        $allowed = ['applied','shortlisted','interview_scheduled','offer_made','accepted','rejected','withdrawn'];
        if (!in_array($status, $allowed, true)) {
            $this->error('Invalid status value.');
        }

        $app = $this->db->selectOne(
            "SELECT pa.student_id user_id, jo.title job_title, c.name company_name
             FROM placement_applications pa
             JOIN job_openings jo ON jo.id = pa.job_id
             JOIN companies c ON c.id = jo.company_id
             WHERE pa.id = ?",
            [$id]
        );

        $this->db->query(
            "UPDATE placement_applications SET status=?,last_updated_at=NOW() WHERE id=?",
            [$status, $id]
        );

        AuditLogger::log('application_status_updated', 'placement', (string)$id, null, ['status' => $status]);

        if ($app) {
            NotificationService::send(
                (int)$app['user_id'],
                'placement.status',
                'Application Status Updated',
                "Your application for \"{$app['job_title']}\" at {$app['company_name']} is now: {$status}.",
                ['application_id' => $id, 'status' => $status]
            );
        }

        $this->success(null, 'Status updated.');
    }

    public function reports(Request $request): void
    {
        $this->authorize('placement.view');
        $this->render('super-admin.placement.reports', ['title' => 'Placement Reports']);
    }
}
