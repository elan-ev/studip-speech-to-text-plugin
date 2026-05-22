<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\Word;

class DurationAnalyzer
{
    public const VOWELS = '/[aeiouyäöü]+/ui';

    /**
     * Count vowel groups (syllable approximation) in a word
     */
    public function countVowelGroups(string $word): int
    {
        $cleanWord = preg_replace('/[^\w]/u', '', $word);
        preg_match_all(self::VOWELS, $cleanWord, $matches);
        return max(1, count($matches[0]));
    }

    /**
     * Build duration statistics from words for data-driven estimation
     *
     * @param Word[] $words
     * @return array<int, array<string, float>>
     */
    public function buildDurationStatistics(array $words): array
    {
        $durationGroups = [];

        foreach ($words as $word) {
            $vowelCount = $this->countVowelGroups($word->text);

            // Classify punctuation
            $punctType = $this->classifyPunctuation($word->text);
            $duration = $word->getDuration();

            // Only include reasonable durations for statistics (filter outliers)
            if ($duration > 0.05 && $duration < 3.0) {
                if (!isset($durationGroups[$vowelCount])) {
                    $durationGroups[$vowelCount] = ['none' => [], 'soft' => [], 'hard' => []];
                }
                $durationGroups[$vowelCount][$punctType][] = $duration;
            }
        }

        // Calculate medians
        $stats = [];
        foreach ($durationGroups as $vowelCount => $punctGroups) {
            $stats[$vowelCount] = [];
            foreach ($punctGroups as $punctType => $durations) {
                if (!empty($durations)) {
                    $stats[$vowelCount][$punctType] = $this->median($durations);
                } else {
                    $stats[$vowelCount][$punctType] = null;
                }
            }
        }

        return $stats;
    }

    /**
     * Estimate typical duration for a word based on vowel count and statistics
     *
     * @param array<int, array<string, float|null>>|null $durationStats
     */
    public function estimateTypicalDuration(string $word, ?array $durationStats = null): float
    {
        $vowelCount = $this->countVowelGroups($word);
        $punctType = $this->classifyPunctuation($word);

        // Try to use statistics first
        if ($durationStats !== null && isset($durationStats[$vowelCount])) {
            $avgDuration = $durationStats[$vowelCount][$punctType] ?? null;
            if ($avgDuration !== null) {
                return $avgDuration;
            }
            // Fallback to 'none' if specific punctuation not available
            if ($punctType !== 'none') {
                $avgDuration = $durationStats[$vowelCount]['none'] ?? null;
                if ($avgDuration !== null) {
                    // Add small adjustment for punctuation
                    if ($punctType === 'soft') {
                        return $avgDuration + 0.05;
                    } elseif ($punctType === 'hard') {
                        return $avgDuration + 0.1;
                    }
                    return $avgDuration;
                }
            }
        }

        // Fallback to vowel-based heuristic
        $baseDuration = $vowelCount * 0.2 + 0.1;

        // Punctuation adjustments
        if ($punctType === 'soft') {
            $baseDuration += 0.05;
        } elseif ($punctType === 'hard') {
            $baseDuration += 0.1;
        }

        return max(0.1, $baseDuration);
    }

    private function classifyPunctuation(string $text): string
    {
        $text = trim($text);
        if (preg_match('/[.!?]$/', $text)) {
            return 'hard';
        } elseif (preg_match('/[,;:]$/', $text)) {
            return 'soft';
        }
        return 'none';
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
