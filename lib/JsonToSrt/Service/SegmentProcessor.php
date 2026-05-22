<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\SubtitleSegment;
use JsonToSrt\Model\ProcessingStats;

class SegmentProcessor
{
    public function __construct(
        private float $maxSubtitleDuration = 15.0,
        private float $safetyGap = 0.010
    ) {
    }

    /**
     * Apply intelligent buffers and duration caps to segments
     *
     * @param SubtitleSegment[] $segments
     * @return SubtitleSegment[]
     */
    public function applyIntelligentBuffersAndCaps(array $segments, ProcessingStats $stats): array
    {
        // Apply intelligent buffers based on gaps
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            $actualEndCurrent = $currentSegment->words[count($currentSegment->words) - 1]->end;
            $actualStartNext = $nextSegment->words[0]->start;

            $gap = $actualStartNext - $actualEndCurrent;

            if ($gap > $this->safetyGap) {
                $totalBuffer = $gap - $this->safetyGap;
                $trailingBuffer = $totalBuffer / 2;
                $leadingBuffer = $totalBuffer / 2;

                $currentSegment->endTime += $trailingBuffer;
                $nextSegment->startTime -= $leadingBuffer;
                $stats->intelligentBuffersApplied++;
            }
        }

        // Apply max duration cap
        foreach ($segments as $seg) {
            $duration = $seg->getDuration();
            if ($duration > $this->maxSubtitleDuration) {
                $seg->endTime = $seg->startTime + $this->maxSubtitleDuration;
                $stats->segmentsCapped++;
            }
        }

        return $segments;
    }

    /**
     * Merge very short segments with adjacent segments
     *
     * @param SubtitleSegment[] $segments
     * @return SubtitleSegment[]
     */
    public function mergeShortSegments(array $segments, ProcessingStats $stats): array
    {
        if (empty($segments)) {
            return [];
        }

        $minDuration = 0.5;
        $mergedSegments = [];
        $i = 0;

        while ($i < count($segments)) {
            $currentSegment = $segments[$i];
            $currentDuration = $currentSegment->getDuration();

            if ($currentDuration < $minDuration) {
                // Try to merge with next segment
                if ($i + 1 < count($segments) && $segments[$i + 1]->speaker === $currentSegment->speaker) {
                    $nextSegment = $segments[$i + 1];

                    $mergedWords = array_merge($currentSegment->words, $nextSegment->words);
                    $mergedSegment = new SubtitleSegment(
                        words: $mergedWords,
                        startTime: $currentSegment->startTime,
                        endTime: $nextSegment->endTime,
                        speaker: $currentSegment->speaker
                    );

                    $mergedSegments[] = $mergedSegment;
                    $i += 2;
                    $stats->segmentsMerged++;
                    continue;
                } elseif (
                    !empty($mergedSegments) &&
                          $mergedSegments[count($mergedSegments) - 1]->speaker === $currentSegment->speaker
                ) {
                    $prevSegment = array_pop($mergedSegments);

                    $mergedWords = array_merge($prevSegment->words, $currentSegment->words);
                    $mergedSegment = new SubtitleSegment(
                        words: $mergedWords,
                        startTime: $prevSegment->startTime,
                        endTime: $currentSegment->endTime,
                        speaker: $prevSegment->speaker
                    );

                    $mergedSegments[] = $mergedSegment;
                    $i++;
                    $stats->segmentsMerged++;
                    continue;
                }
            }

            $mergedSegments[] = $currentSegment;
            $i++;
        }

        return $mergedSegments;
    }
}
