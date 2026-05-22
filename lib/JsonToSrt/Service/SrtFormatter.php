<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\SubtitleSegment;

class SrtFormatter
{
    public function __construct(
        private TextWrapper $textWrapper,
    ) {
    }

    /**
     * Generate SRT format content from segments
     *
     * @param SubtitleSegment[] $segments
     */
    public function generateSrtContent(array $segments, bool $includeSpeakers = false): string
    {
        $srtLines = [];

        foreach ($segments as $i => $segment) {
            $startTime = $this->formatSrtTime($segment->startTime);
            $endTime = $this->formatSrtTime($segment->endTime);

            $wrappedLines = $this->textWrapper->wrapText($segment->words);
            $text = implode("\n", $wrappedLines);

            if ($includeSpeakers && $segment->speaker !== 'UNKNOWN') {
                $speakerPrefix = "[{$segment->speaker}]: ";
                $lines = explode("\n", $text);
                if (!empty($lines)) {
                    $lines[0] = $speakerPrefix . $lines[0];
                    $text = implode("\n", $lines);
                }
            }

            $srtLines[] = (string)($i + 1);
            $srtLines[] = "$startTime --> $endTime";
            $srtLines[] = $text;
            $srtLines[] = '';
        }

        return implode("\n", $srtLines);
    }

    private function formatSrtTime(float $seconds): string
    {
        $hours = intdiv((int)$seconds, 3600);
        $minutes = intdiv((int)$seconds % 3600, 60);
        $secs = (int)$seconds % 60;
        $milliseconds = (int)(($seconds - floor($seconds)) * 1000);
        return sprintf("%02d:%02d:%02d,%03d", $hours, $minutes, $secs, $milliseconds);
    }
}
