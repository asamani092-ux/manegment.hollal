<?php

namespace App\Services;

use App\Models\DiagnosisAnswer;
use App\Models\DiagnosisQuestion;
use App\Models\Partnership;
use Illuminate\Support\Collection;

/**
 * Manage diagnosis questions and append-only answers.
 * Time: O(q) per load/submit | Space: O(q) + O(q·submissions) history.
 */
class DiagnosisQuestionService
{
    /** @return Collection<int, DiagnosisQuestion> */
    public function activeQuestions(): Collection
    {
        return DiagnosisQuestion::query()->active()->get();
    }

    /**
     * @param  array{label: string, type?: string, required?: bool, sort_order?: int, is_active?: bool}  $payload
     */
    public function create(array $payload): DiagnosisQuestion
    {
        return DiagnosisQuestion::query()->create([
            'key' => null,
            'label' => $payload['label'],
            'type' => in_array($payload['type'] ?? 'text', DiagnosisQuestion::TYPES, true)
                ? $payload['type']
                : 'text',
            'required' => (bool) ($payload['required'] ?? true),
            'sort_order' => (int) ($payload['sort_order'] ?? ((int) DiagnosisQuestion::query()->max('sort_order') + 1)),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array{label?: string, type?: string, required?: bool, sort_order?: int, is_active?: bool}  $payload
     */
    public function update(DiagnosisQuestion $question, array $payload): DiagnosisQuestion
    {
        $question->fill([
            'label' => $payload['label'] ?? $question->label,
            'type' => isset($payload['type']) && in_array($payload['type'], DiagnosisQuestion::TYPES, true)
                ? $payload['type']
                : $question->type,
            'required' => array_key_exists('required', $payload) ? (bool) $payload['required'] : $question->required,
            'sort_order' => array_key_exists('sort_order', $payload) ? (int) $payload['sort_order'] : $question->sort_order,
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : $question->is_active,
        ])->save();

        return $question;
    }

    /**
     * @param  array<int|string, string>  $answers
     */
    public function recordAnswers(Partnership $partnership, array $answers): void
    {
        $now = now();
        foreach ($answers as $questionId => $value) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            DiagnosisAnswer::query()->create([
                'partnership_id' => $partnership->id,
                'question_id' => (int) $questionId,
                'value' => mb_substr($text, 0, 4000),
                'created_at' => $now,
            ]);
        }
    }

    /** @return array<int, string> */
    public function latestAnswers(Partnership $partnership): array
    {
        $rows = DiagnosisAnswer::query()
            ->where('partnership_id', $partnership->id)
            ->orderBy('id')
            ->get(['question_id', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->question_id] = (string) $row->value;
        }

        return $out;
    }

    /** @return list<array{label: string, value: string}> */
    public function latestLabeledAnswers(Partnership $partnership): array
    {
        $latest = $this->latestAnswers($partnership);
        if ($latest === []) {
            return [];
        }

        $questions = DiagnosisQuestion::query()
            ->whereIn('id', array_keys($latest))
            ->get(['id', 'label'])
            ->keyBy('id');

        $out = [];
        foreach ($latest as $id => $value) {
            $out[] = [
                'label' => (string) ($questions[$id]->label ?? '#'.$id),
                'value' => $value,
            ];
        }

        return $out;
    }
}
