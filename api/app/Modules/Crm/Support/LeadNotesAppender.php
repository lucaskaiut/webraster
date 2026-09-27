<?php

namespace App\Modules\Crm\Support;

final class LeadNotesAppender
{
    private const SIMILARITY_THRESHOLD = 82.0;

    /**
     * @return array{notes: string, appended: bool, skipped_reason: ?string}
     */
    public function append(?string $existingNotes, string $incomingNote): array
    {
        $incomingNote = trim($incomingNote);
        $existing = trim($existingNotes ?? '');

        if ($incomingNote === '') {
            return [
                'notes' => $existing,
                'appended' => false,
                'skipped_reason' => 'empty',
            ];
        }

        if ($existing !== '' && str_starts_with($incomingNote, $existing)) {
            $incomingNote = trim(substr($incomingNote, strlen($existing)), "\n");
        }

        if ($incomingNote === '') {
            return [
                'notes' => $existing,
                'appended' => false,
                'skipped_reason' => 'duplicate_prefix',
            ];
        }

        $incomingLines = $this->splitLines($incomingNote);

        if ($existing === '') {
            $merged = implode("\n", $incomingLines);

            return [
                'notes' => $merged,
                'appended' => true,
                'skipped_reason' => null,
            ];
        }

        $known = $this->normalizedLineSet($existing);
        $newLines = [];

        foreach ($incomingLines as $line) {
            if ($this->isDuplicateLine($line, $known, $existing)) {
                continue;
            }

            $newLines[] = $line;
            $known[$this->normalize($line)] = true;
        }

        if ($newLines === []) {
            return [
                'notes' => $existing,
                'appended' => false,
                'skipped_reason' => 'duplicate',
            ];
        }

        return [
            'notes' => $existing."\n".implode("\n", $newLines),
            'appended' => true,
            'skipped_reason' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function normalizedLineSet(string $existing): array
    {
        $set = [];

        foreach ($this->splitLines($existing) as $line) {
            $set[$this->normalize($line)] = true;
        }

        return $set;
    }

    /**
     * @param  array<string, true>  $knownNormalized
     */
    private function isDuplicateLine(string $line, array $knownNormalized, string $existing): bool
    {
        $normalized = $this->normalize($line);

        if ($normalized === '') {
            return true;
        }

        if (isset($knownNormalized[$normalized])) {
            return true;
        }

        foreach ($this->splitLines($existing) as $existingLine) {
            if ($this->linesAreSimilar($line, $existingLine)) {
                return true;
            }
        }

        return false;
    }

    private function linesAreSimilar(string $a, string $b): bool
    {
        $na = $this->normalize($a);
        $nb = $this->normalize($b);

        if ($na === $nb) {
            return true;
        }

        if ($na === '' || $nb === '') {
            return false;
        }

        $minLength = min(mb_strlen($na), mb_strlen($nb));

        if ($minLength < 28) {
            return false;
        }

        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            $shorter = min(mb_strlen($na), mb_strlen($nb));
            $longer = max(mb_strlen($na), mb_strlen($nb));

            if ($shorter > 0 && ($longer / $shorter) < 1.35) {
                return true;
            }
        }

        similar_text($na, $nb, $percent);

        return $percent >= self::SIMILARITY_THRESHOLD;
    }

    private function normalize(string $line): string
    {
        $line = mb_strtolower(trim($line));
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return preg_replace('/[^\p{L}\p{N}\s|:\-]/u', '', $line) ?? $line;
    }
}
