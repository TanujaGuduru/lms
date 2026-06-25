<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Account / Profile — docs/student-module/04a-apis-conventions-enrollment-billing.md.
 */
class AccountController extends Controller
{
    // Controlled vocabulary, not free text — per 04a, this is what makes the
    // Phase 5 recommendation engine able to actually use these fields
    // reliably. Small and hand-maintained on purpose; extend deliberately.
    private const INTERESTS = ['robotics', 'game_dev', 'web_dev', 'ai_ml', 'app_dev', 'data_science', 'competitive_programming'];
    private const GOALS = ['build_portfolio', 'crack_placement', 'school_exams', 'olympiad', 'startup_idea', 'just_curious'];
    private const CODING_EXPERIENCE = ['none', 'beginner', 'intermediate', 'advanced'];

    public function show(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $profile = $this->db->fetchOne(
            'SELECT sp.*, ap.learning_pace, ap.explanation_style, ap.weak_topics, ap.strong_topics, ap.persona_settings
             FROM student_profiles sp
             LEFT JOIN ai_profiles ap ON ap.user_id = sp.user_id
             WHERE sp.user_id = ?',
            [$userId]
        );

        if (! $profile) {
            $this->fail('No student profile exists for this account.', ['reason' => ['not_a_student']], 404);
        }

        foreach (['interests', 'goals', 'weak_topics', 'strong_topics', 'persona_settings'] as $jsonField) {
            if (isset($profile[$jsonField]) && $profile[$jsonField] !== null) {
                $profile[$jsonField] = json_decode($profile[$jsonField], true);
            }
        }

        $this->success($profile);
    }

    public function update(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $allowed = ['grade', 'school_name', 'timezone', 'coding_experience', 'preferred_language'];
        $data = array_intersect_key($request->all(), array_flip($allowed));

        if (isset($data['coding_experience']) && ! in_array($data['coding_experience'], self::CODING_EXPERIENCE, true)) {
            $this->fail('Invalid coding experience.', ['coding_experience' => ['in:' . implode(',', self::CODING_EXPERIENCE)]]);
        }

        if (empty($data)) {
            $this->fail('No updatable fields were provided.', ['reason' => ['empty_payload']]);
        }

        $this->db->updateTable('student_profiles', $data, 'user_id = ?', [$userId]);
        $this->show($request);
    }

    public function completeOnboarding(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $interests = (array) $request->input('interests', []);
        $goals = (array) $request->input('goals', []);
        $codingExperience = (string) $request->input('coding_experience', '');
        $preferredLanguage = $request->input('preferred_language');

        $errors = [];
        if (! in_array($codingExperience, self::CODING_EXPERIENCE, true)) {
            $errors['coding_experience'] = ['required|in:' . implode(',', self::CODING_EXPERIENCE)];
        }
        if ($invalid = array_diff($interests, self::INTERESTS)) {
            $errors['interests'] = ['unknown values: ' . implode(',', $invalid)];
        }
        if ($invalid = array_diff($goals, self::GOALS)) {
            $errors['goals'] = ['unknown values: ' . implode(',', $invalid)];
        }

        if ($errors) {
            $this->fail('Validation failed.', $errors);
        }

        $this->db->updateTable('student_profiles', [
            'interests' => json_encode($interests),
            'goals' => json_encode($goals),
            'coding_experience' => $codingExperience,
            'preferred_language' => $preferredLanguage,
        ], 'user_id = ?', [$userId]);

        $this->show($request);
    }
}
