<?php

namespace JsonToSrt\Service;

use JsonToSrt\Model\Word;

class TextWrapper
{
    public function __construct(
        private int $maxCharsPerLine = 40,
        private int $maxLines = 2,
        private int $overflowTolerance = 4
    ) {
    }

    /**
     * Wrap text into lines with balanced line lengths
     *
     * @param Word[] $words
     * @return string[]
     */
    public function wrapText(array $words): array
    {
        if (empty($words)) {
            return [];
        }

        $effectiveLimit = $this->maxCharsPerLine + $this->overflowTolerance;

        $wordTexts = array_map(fn($w) => trim($w->text), $words);
        $fullText = implode(' ', $wordTexts);

        // If text fits on one line
        if (strlen($fullText) <= $effectiveLimit) {
            return [$fullText];
        }

        // If limited to 1 line
        if ($this->maxLines === 1) {
            $wordsSoFar = [];
            foreach ($wordTexts as $wordText) {
                $testLine = implode(' ', array_merge($wordsSoFar, [$wordText]));
                if (strlen($testLine) <= $effectiveLimit) {
                    $wordsSoFar[] = $wordText;
                } else {
                    break;
                }
            }
            return empty($wordsSoFar) ? [$wordTexts[0]] : [implode(' ', $wordsSoFar)];
        }

        // For 2+ lines, try to balance
        return $this->balanceLines($wordTexts, $effectiveLimit);
    }

    /**
     * @param string[] $wordTexts
     * @return string[]
     */
    private function balanceLines(array $wordTexts, int $effectiveLimit): array
    {
        if ($this->maxLines === 2) {
            return $this->balanceTwoLines($wordTexts, $effectiveLimit);
        }

        // For 3+ lines, use greedy approach
        $lines = [];
        $currentLine = '';

        foreach ($wordTexts as $i => $wordText) {
            if ($currentLine === '') {
                $currentLine = $wordText;
            } else {
                $testLine = $currentLine . ' ' . $wordText;
                if (strlen($testLine) <= $effectiveLimit) {
                    $currentLine = $testLine;
                } else {
                    $lines[] = $currentLine;
                    $currentLine = $wordText;

                    if (count($lines) >= $this->maxLines - 1) {
                        $lines[] = $currentLine;
                        return $lines;
                    }
                }
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return array_map('trim', $lines);
    }

    /**
     * @param string[] $wordTexts
     * @return string[]
     */
    private function balanceTwoLines(array $wordTexts, int $effectiveLimit): array
    {
        if (empty($wordTexts)) {
            return [];
        }

        $fullText = implode(' ', $wordTexts);
        $totalLength = strlen($fullText);
        $targetFirstLine = intdiv($totalLength, 2);

        $bestSplit = 0;
        $bestBalance = PHP_FLOAT_MAX;

        for ($i = 1; $i < count($wordTexts); $i++) {
            $firstLineWords = array_slice($wordTexts, 0, $i);
            $secondLineWords = array_slice($wordTexts, $i);

            $firstLine = implode(' ', $firstLineWords);
            $secondLine = implode(' ', $secondLineWords);

            if (strlen($firstLine) > $effectiveLimit || strlen($secondLine) > $effectiveLimit) {
                continue;
            }

            $balance = abs(strlen($firstLine) - $targetFirstLine);

            if ($balance < $bestBalance) {
                $bestBalance = $balance;
                $bestSplit = $i;
            }
        }

        if ($bestSplit === 0) {
            $currentLine = '';
            foreach ($wordTexts as $wordText) {
                $testLine = ($currentLine === '' ? '' : $currentLine . ' ') . $wordText;
                if (strlen($testLine) <= $effectiveLimit) {
                    $currentLine = $testLine;
                } else {
                    break;
                }
            }
            return $currentLine !== '' ? [$currentLine] : [$wordTexts[0]];
        }

        $firstLine = implode(' ', array_slice($wordTexts, 0, $bestSplit));
        $secondLine = implode(' ', array_slice($wordTexts, $bestSplit));

        return [trim($firstLine), trim($secondLine)];
    }

    public function calculateTextLength(array $words): int
    {
        if (empty($words)) {
            return 0;
        }
        return strlen(implode(' ', array_map(fn($w) => $w->text, $words)));
    }
}
