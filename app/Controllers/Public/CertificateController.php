<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Request;

class CertificateController extends Controller
{
    public function verify(Request $request, string $code): void
    {
        $certificate = $this->db->selectOne(
            "SELECT c.*, CONCAT(u.first_name,' ',u.last_name) student_name,
             co.title course_title
             FROM certificates c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN courses co ON co.id = c.course_id
             WHERE c.verification_code = ?",
            [$code]
        );

        $this->render('public.certificate-verify', [
            'title'       => 'Certificate Verification — CodeGurukul LMS',
            'certificate' => $certificate ?: null,
        ]);
    }
}
