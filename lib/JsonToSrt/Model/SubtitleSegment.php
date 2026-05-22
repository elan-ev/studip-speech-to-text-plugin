<?php

namespace JsonToSrt\Model;

final class SubtitleSegment
{
    /**
     * @param Word[] $words
     */
    public function __construct(
        public readonly array $words,
        public float $startTime,
        public float $endTime,
        public readonly string $speaker
    ) {
    }

    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }
}
