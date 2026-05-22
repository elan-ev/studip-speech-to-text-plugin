<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\Word;
use JsonToSrt\Model\ProcessingStats;

class TimingCorrector
{
    public function __construct(
        private DurationAnalyzer $durationAnalyzer,
        private float $timingCorrectionThreshold = 3.0
    ) {
    }

    /**
     * Apply intelligent timing corrections to words with anomalous durations
     *
     * @param Word[] $words
     * @param array<int, array<string, float|null>>|null $durationStats
     * @return Word[]
     */
    public function applyTimingCorrections(array $words, ?array $durationStats, ProcessingStats $stats): array
    {
        if (count($words) < 2) {
            return $words;
        }

        $correctedWords = [];

        foreach ($words as $i => $word) {
            $correctedWord = new Word(
                text: $word->text,
                start: $word->start,
                end: $word->end,
                speaker: $word->speaker,
            );

            $duration = $word->getDuration();
            $typicalDuration = $this->durationAnalyzer->estimateTypicalDuration($word->text, $durationStats);

            // Check if word has hard punctuation
            $hasHardPunct = (bool)preg_match('/[.!?]\s*$/', trim($word->text));

            // HARD PUNCTUATION CONSTRAINT: Never exceed average duration
            if ($hasHardPunct && $duration > $typicalDuration) {
                $proposedEnd = $correctedWord->start + $typicalDuration;

                // Make sure we don't overlap with next word
                if ($i < count($words) - 1) {
                    $nextWord = $words[$i + 1];
                    if ($proposedEnd > $nextWord->start - 0.1) {
                        $proposedEnd = $nextWord->start - 0.1;
                    }
                }

                $stats->timingCorrections++;
                $correctedWords[] = $correctedWord->withEnd(max($correctedWord->start + 0.05, $proposedEnd));
                continue;
            }

            // Only correct other words that exceed threshold
            if ($duration < $this->timingCorrectionThreshold) {
                $correctedWords[] = $correctedWord;
                continue;
            }

            $position = $i === 0 ? 'first' : ($i === count($words) - 1 ? 'last' : 'middle');

            // Apply position-based corrections
            if ($position === 'last' && $duration > $typicalDuration * 3) {
                $correctedWord->end = $correctedWord->start + $typicalDuration;
                $stats->timingCorrections++;
                $stats->correctionsLastWords++;
            } elseif ($position === 'first' && $duration > $typicalDuration * 3) {
                if ($i < count($words) - 1) {
                    $nextWord = $words[$i + 1];
                    $proposedStart = $correctedWord->end - $typicalDuration;
                    if ($proposedStart + $typicalDuration > $nextWord->start - 0.1) {
                        $proposedStart = max($correctedWord->start, $nextWord->start - $typicalDuration - 0.1);
                    }
                    $correctedWord->start = $proposedStart;
                } else {
                    $correctedWord->start = $correctedWord->end - $typicalDuration;
                }
                $stats->timingCorrections++;
                $stats->correctionsFirstWords++;
            } elseif ($position === 'middle' && $duration > $typicalDuration * 4) {
                $middleTime = $correctedWord->start + $duration / 2;
                $correctedWord->start = $middleTime - $typicalDuration / 2;
                $correctedWord->end = $middleTime + $typicalDuration / 2;
                $stats->timingCorrections++;
            }

            $correctedWords[] = $correctedWord;
        }

        return $correctedWords;
    }
}
