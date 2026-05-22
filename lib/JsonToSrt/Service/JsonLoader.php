<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\Word;
use JsonToSrt\Model\ProcessingStats;

class JsonLoader
{
    public function __construct(
        private DurationAnalyzer $durationAnalyzer,
        private TimingCorrector $timingCorrector,
        private bool $applyTimingCorrections,
    ) {
    }

    // break this method up into smaller methods with a max line count of 20 lines, AI!
    /**
     * Load and parse JSON from a string into Word objects
     *
     * @return array{words: Word[], stats: ProcessingStats}
     */
    public function loadFromString(string $jsonContent): array
    {
        $stats = new ProcessingStats();

        $data = json_decode($jsonContent, true);
        if ($data === null) {
            throw new \Exception("Invalid JSON format: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \Exception("JSON root must be an object");
        }

        $segments = $data['segments'] ?? [];
        if (empty($segments)) {
            throw new \Exception("No 'segments' found in JSON data");
        }

        $segmentWordsGroups = [];
        $segmentCount = 0;
        $validSegments = 0;

        foreach ($segments as $segment) {
            $segmentCount++;

            if (!is_array($segment)) {
                continue;
            }

            $segmentSpeaker = $segment['speaker'] ?? 'UNKNOWN';
            $segmentWordsList = $segment['words'] ?? [];

            if (empty($segmentWordsList)) {
                continue;
            }

            $validSegments++;
            $segmentWords = [];

            foreach ($segmentWordsList as $wordData) {
                if (!is_array($wordData)) {
                    continue;
                }

                $wordText = trim($wordData['word'] ?? '');
                if ($wordText === '') {
                    continue;
                }

                $startTime = (float)($wordData['start'] ?? 0.0);
                $endTime = (float)($wordData['end'] ?? 0.0);

                if ($endTime <= $startTime) {
                    continue;
                }

                $speaker = $wordData['speaker'] ?? $segmentSpeaker;

                $segmentWords[] = new Word(
                    text: $wordText,
                    start: $startTime,
                    end: $endTime,
                    speaker: $speaker,
                );
            }

            if (!empty($segmentWords)) {
                $segmentWordsGroups[] = $segmentWords;
            }
        }

        // Collect all words to build duration statistics
        $allWordsUncorrected = [];
        foreach ($segmentWordsGroups as $segmentWords) {
            $allWordsUncorrected = array_merge($allWordsUncorrected, $segmentWords);
        }

        if (empty($allWordsUncorrected)) {
            throw new \Exception("No valid words found in JSON. Processed $segmentCount segments, $validSegments were valid");
        }

        // Build duration statistics
        $durationStats = $this->durationAnalyzer->buildDurationStatistics($allWordsUncorrected);

        // Apply timing corrections
        $words = [];
        if ($this->applyTimingCorrections) {
            foreach ($segmentWordsGroups as $segmentWords) {
                $correctedSegmentWords = $this->timingCorrector->applyTimingCorrections($segmentWords, $durationStats, $stats);
                $words = array_merge($words, $correctedSegmentWords);
            }
        } else {
            $words = $allWordsUncorrected;
        }

        // Sort by start time
        usort($words, fn($a, $b) => $a->start <=> $b->start);

        $stats->wordsProcessed = count($words);

        return ['words' => $words, 'stats' => $stats];
    }
}
