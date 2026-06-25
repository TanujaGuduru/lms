<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Core\Setting;

class AiCenterController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('ai.view');
        $this->render('super-admin.ai-center.index', ['title' => 'AI Center']);
    }

    public function generateQuiz(Request $request): never
    {
        $this->authorize('ai.use');

        $data = $this->validate($request, [
            'topic'       => 'required|min:3|max:200',
            'count'       => 'required|integer|min_val:1|max_val:20',
            'difficulty'  => 'required|in:easy,medium,hard',
            'type'        => 'required|in:mcq,true_false,mixed',
        ]);

        $apiKey = Setting::get('ai.openai_api_key', '');
        if (empty($apiKey)) {
            $this->error('OpenAI API key not configured. Go to Settings → AI Settings.');
        }

        $prompt = $this->buildQuizPrompt($data);

        try {
            $result = $this->callOpenAI($apiKey, $prompt, Setting::get('ai.openai_model', 'gpt-4o-mini'));
            $questions = $this->parseQuizResponse($result);

            AuditLogger::log('ai_quiz_generated', 'ai', null, null, [
                'topic' => $data['topic'], 'count' => count($questions)
            ]);

            $this->success(['questions' => $questions], count($questions) . ' questions generated.');
        } catch (\Throwable $e) {
            $this->error('AI generation failed: ' . $e->getMessage());
        }
    }

    public function generateContent(Request $request): never
    {
        $this->authorize('ai.use');

        $data = $this->validate($request, [
            'topic'    => 'required|min:3|max:300',
            'type'     => 'required|in:lesson,summary,outline,explanation',
            'length'   => 'required|in:short,medium,long',
        ]);

        $apiKey = Setting::get('ai.openai_api_key', '');
        if (empty($apiKey)) $this->error('OpenAI API key not configured.');

        $lengths = ['short' => 200, 'medium' => 500, 'long' => 1000];
        $prompt = "Write a {$data['type']} about '{$data['topic']}' for students. " .
                  "Length: approximately {$lengths[$data['length']]} words. " .
                  "Format with headings, bullet points where appropriate. Be educational and clear.";

        try {
            $content = $this->callOpenAI($apiKey, $prompt, Setting::get('ai.openai_model', 'gpt-4o-mini'));
            AuditLogger::log('ai_content_generated', 'ai', null, null, ['topic' => $data['topic'], 'type' => $data['type']]);
            $this->success(['content' => $content], 'Content generated.');
        } catch (\Throwable $e) {
            $this->error('AI generation failed: ' . $e->getMessage());
        }
    }

    public function generateAssignment(Request $request): never
    {
        $this->authorize('ai.use');

        $data = $this->validate($request, [
            'topic'     => 'required|min:3|max:200',
            'level'     => 'required|in:beginner,intermediate,advanced',
            'type'      => 'required|in:essay,project,practical,research',
        ]);

        $apiKey = Setting::get('ai.openai_api_key', '');
        if (empty($apiKey)) $this->error('OpenAI API key not configured.');

        $prompt = "Create a {$data['type']} assignment on '{$data['topic']}' for {$data['level']} students. " .
                  "Include: clear objective, detailed instructions, evaluation criteria (rubric), and estimated time to complete. " .
                  "Format as structured assignment document.";

        try {
            $content = $this->callOpenAI($apiKey, $prompt, Setting::get('ai.openai_model', 'gpt-4o-mini'));
            AuditLogger::log('ai_assignment_generated', 'ai', null, null, $data);
            $this->success(['assignment' => $content], 'Assignment generated.');
        } catch (\Throwable $e) {
            $this->error('AI generation failed: ' . $e->getMessage());
        }
    }

    private function buildQuizPrompt(array $data): string
    {
        $types = ['mcq' => 'multiple choice', 'true_false' => 'true/false', 'mixed' => 'mixed'];
        $typeStr = $types[$data['type']] ?? 'multiple choice';

        return "Generate {$data['count']} {$data['difficulty']} {$typeStr} questions about '{$data['topic']}'. " .
               "Return ONLY a valid JSON array. Each object must have: " .
               "\"text\" (question), \"type\" (\"mcq\" or \"true_false\"), " .
               "\"options\" (array of 4 strings for mcq, [\"True\",\"False\"] for tf), " .
               "\"correct_answer\" (correct option string), \"explanation\" (brief explanation). " .
               "No markdown, no extra text — pure JSON array only.";
    }

    private function callOpenAI(string $apiKey, string $prompt, string $model = 'gpt-4o-mini'): string
    {
        $payload = json_encode([
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => (int)Setting::get('ai.ai_max_tokens', '2048'),
            'temperature' => 0.7,
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException("OpenAI API error (HTTP {$httpCode})");
        }

        $decoded = json_decode($response, true);
        return $decoded['choices'][0]['message']['content'] ?? throw new \RuntimeException('Empty AI response');
    }

    private function parseQuizResponse(string $json): array
    {
        // Strip markdown code fences if present
        $json = preg_replace('/^```(?:json)?\n?/m', '', trim($json));
        $json = preg_replace('/```$/m', '', $json);

        $data = json_decode(trim($json), true);
        if (!is_array($data)) throw new \RuntimeException('AI returned invalid JSON.');
        return $data;
    }
}
