<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Flashcard spaced repetition — docs/student-module/04h-apis-notebook-collab.md.
 * A deterministic SM-2-style algorithm, explicitly not an AI Gateway call
 * (the doc is explicit about this one endpoint being the exception in an
 * otherwise AI-assisted section). `ease_factor`/`interval_days` are gap-fill
 * columns (schema_student_portal.sql) — `flashcards` originally had nowhere
 * to remember either between reviews, which a *real* SM-2 needs (not just a
 * fixed multiplier).
 */
class FlashcardController extends Controller
{
    private const MIN_EASE = 1.3;

    public function due(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT id, deck_name, front_text, back_text, next_review_at FROM flashcards
             WHERE student_id = ? AND (next_review_at IS NULL OR next_review_at <= NOW())
             ORDER BY next_review_at LIMIT 50",
            [$studentId]
        );

        $this->success($rows);
    }

    public function review(Request $request, string $id): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $card = $this->db->fetchOne('SELECT * FROM flashcards WHERE id = ? AND student_id = ?', [$id, $studentId]);

        if (! $card) {
            $this->fail('No such flashcard.', ['reason' => ['not_found']], 404);
        }

        $outcome = (string) $request->input('outcome', '');
        if (! in_array($outcome, ['again', 'hard', 'good', 'easy'], true)) {
            $this->fail('outcome must be again|hard|good|easy.', ['outcome' => ['required|in:again,hard,good,easy']]);
        }

        [$intervalDays, $ease] = $this->nextInterval((float) $card['ease_factor'], (int) $card['interval_days'], $outcome);

        $this->db->updateTable('flashcards', [
            'review_count' => (int) $card['review_count'] + 1,
            'last_reviewed_at' => date('Y-m-d H:i:s'),
            'next_review_at' => date('Y-m-d H:i:s', strtotime("+{$intervalDays} days")),
            'ease_factor' => $ease,
            'interval_days' => $intervalDays,
        ], 'id = ?', [$id]);

        $this->success([
            'id' => (int) $id,
            'review_count' => (int) $card['review_count'] + 1,
            'next_review_at' => date('Y-m-d H:i:s', strtotime("+{$intervalDays} days")),
        ]);
    }

    /** SM-2 / Anki-style interval growth — a new card starts at interval=0, ease=2.5 (the flashcards table's column defaults). */
    private function nextInterval(float $ease, int $intervalDays, string $outcome): array
    {
        return match ($outcome) {
            'again' => [1, max(self::MIN_EASE, $ease - 0.20)],
            'hard' => [max(1, (int) round(max($intervalDays, 1) * 1.2)), max(self::MIN_EASE, $ease - 0.15)],
            'good' => [max(1, (int) round(max($intervalDays, 1) * $ease)), $ease],
            'easy' => [max(1, (int) round(max($intervalDays, 1) * $ease * 1.3)), $ease + 0.15],
        };
    }
}
