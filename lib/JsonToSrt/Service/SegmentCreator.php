<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\Word;
use JsonToSrt\Model\SubtitleSegment;
use JsonToSrt\Model\ProcessingStats;

class SegmentCreator
{
    public function __construct(
        private TextWrapper $textWrapper,
        private float $pauseThreshold = 3.0,
        private bool $breakOnSpeakerChange = true,
        private float $speakerChangeThreshold = 0.150,
        private bool $preventOrphans = true,
        private int $orphanMoveThreshold = 15,
        private int $maxLines = 2,
    ) {
    }

    /**
     * Create subtitle segments from words
     *
     * @param Word[] $words
     * @return SubtitleSegment[]
     */
    public function createSegments(array $words, ProcessingStats $stats): array
    {
        if (empty($words)) {
            return [];
        }

        $breakIndices = $this->detectSegmentBreaks($words, $stats);
        $segments = [];

        $allBreaks = array_merge([0], $breakIndices, [count($words)]);

        for ($i = 0; $i < count($allBreaks) - 1; $i++) {
            $startIdx = $allBreaks[$i];
            $endIdx = $allBreaks[$i + 1];

            $segmentWords = array_slice($words, $startIdx, $endIdx - $startIdx);
            if (empty($segmentWords)) {
                continue;
            }

            $wordGroups = $this->splitOversizedSegment($segmentWords, $stats);

            foreach ($wordGroups as $wordGroup) {
                if (empty($wordGroup)) {
                    continue;
                }

                $segment = new SubtitleSegment(
                    words: $wordGroup,
                    startTime: $wordGroup[0]->start,
                    endTime: $wordGroup[count($wordGroup) - 1]->end,
                    speaker: $wordGroup[0]->speaker
                );

                $segments[] = $segment;
                $stats->segmentsCreated++;
            }
        }

        return $segments;
    }

    /**
     * @param Word[] $words
     * @return int[]
     */
    private function detectSegmentBreaks(array $words, ProcessingStats $stats): array
    {
        $breaks = [];

        for ($i = 1; $i < count($words); $i++) {
            $currentWord = $words[$i];
            $prevWord = $words[$i - 1];

            $pauseDuration = $currentWord->start - $prevWord->end;
            $isSpeakerChange = $prevWord->speaker !== $currentWord->speaker;
            $breakOnLongPause = $pauseDuration > $this->pauseThreshold;
            $breakOnSpeakerChange = $this->breakOnSpeakerChange && $isSpeakerChange
                && $pauseDuration > $this->speakerChangeThreshold;

            if ($breakOnLongPause || $breakOnSpeakerChange) {
                if ($breakOnLongPause) {
                    $stats->pauseBreaks++;
                }
                if ($isSpeakerChange) {
                    $stats->speakerChanges++;
                }
                $breaks[] = $i;
            }
        }

        return $breaks;
    }

    /**
     * @param Word[] $words
     * @return array<int, Word[]>
     */
    private function splitOversizedSegment(array $words, ProcessingStats $stats): array
    {
        if (empty($words)) {
            return [];
        }

        $segments = [];
        $currentSegment = [];

        foreach ($words as $i => $word) {
            $testSegment = array_merge($currentSegment, [$word]);

            $lines = $this->textWrapper->wrapText($testSegment);
            $wrappedWordCount = 0;
            foreach ($lines as $line) {
                $wrappedWordCount += count(explode(' ', $line));
            }
            $expectedWordCount = count($testSegment);

            $wouldTruncate = $wrappedWordCount < $expectedWordCount;
            $wouldExceedLines = count($lines) > $this->maxLines;

            if (($wouldTruncate || $wouldExceedLines) && !empty($currentSegment)) {
                // Orphan prevention
                if ($this->preventOrphans && count($currentSegment) >= 2) {
                    $sentenceStartIdx = null;
                    for ($j = count($currentSegment) - 1; $j > 0; $j--) {
                        if ($this->isSentenceStart($currentSegment[$j], $currentSegment[$j - 1])) {
                            $sentenceStartIdx = $j;
                            break;
                        }
                    }

                    if ($sentenceStartIdx !== null) {
                        $fragment = array_slice($currentSegment, $sentenceStartIdx);
                        $fragmentLength = $this->textWrapper->calculateTextLength($fragment);

                        if ($fragmentLength <= $this->orphanMoveThreshold) {
                            $segments[] = array_slice($currentSegment, 0, $sentenceStartIdx);
                            $currentSegment = array_merge($fragment, [$word]);
                            $stats->orphanBreaksPrevented++;
                            continue;
                        }
                    }
                }

                // Check for orphans in wrapped text
                $orphanSplitIdx = $this->checkForOrphansInWrappedText($currentSegment, $stats);
                if ($orphanSplitIdx !== null && $orphanSplitIdx > 0) {
                    $segments[] = array_slice($currentSegment, 0, $orphanSplitIdx);
                    $currentSegment = array_merge(array_slice($currentSegment, $orphanSplitIdx), [$word]);
                    continue;
                }

                $segments[] = $currentSegment;
                $currentSegment = [$word];
            } else {
                $currentSegment[] = $word;
            }
        }

        if (!empty($currentSegment)) {
            $orphanSplitIdx = $this->checkForOrphansInWrappedText($currentSegment, $stats);
            if ($orphanSplitIdx !== null && $orphanSplitIdx > 0) {
                $segments[] = array_slice($currentSegment, 0, $orphanSplitIdx);
                $segments[] = array_slice($currentSegment, $orphanSplitIdx);
            } else {
                $segments[] = $currentSegment;
            }
        }

        return $segments;
    }

    private function isSentenceStart(Word $word, ?Word $prevWord): bool
    {
        if ($prevWord === null) {
            return true;
        }

        $prevText = trim($prevWord->text);
        if (preg_match('/[.!?]\s*$/', $prevText)) {
            return true;
        }

        $currentText = trim($word->text);
        if (!empty($currentText) && ctype_upper($currentText[0])) {
            if (preg_match('/[.!?:;,]\s*$/', $prevText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Word[] $words
     */
    private function checkForOrphansInWrappedText(array $words, ProcessingStats $stats): ?int
    {
        if (!$this->preventOrphans || count($words) < 2) {
            return null;
        }

        $lines = $this->textWrapper->wrapText($words);
        if (count($lines) < 2) {
            return null;
        }

        $lastLine = trim($lines[count($lines) - 1]);
        $lastLineWords = explode(' ', $lastLine);

        if (strlen($lastLine) > $this->orphanMoveThreshold) {
            return null;
        }

        $lastLineWordCount = count($lastLineWords);
        $splitIndex = count($words) - $lastLineWordCount;

        if ($splitIndex > 0 && $splitIndex < count($words)) {
            if ($this->isSentenceStart($words[$splitIndex], $words[$splitIndex - 1])) {
                $stats->orphanBreaksPrevented++;
                return $splitIndex;
            }
        }

        return null;
    }
}
