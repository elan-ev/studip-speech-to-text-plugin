<?php

namespace JsonToSrt\Model;

final class Word
{
    public function __construct(
        public readonly string $text,
        public readonly float $start,
        public readonly float $end,
        public readonly string $speaker,
    ) {
    }

    public function getDuration(): float
    {
        return $this->end - $this->start;
    }

    public function withEnd(float $end): static
    {
        return new static(
            $this->text,
            $this->start,
            $end,
            $this->speaker,
        );
    }
}
