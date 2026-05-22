<?php

namespace JsonToSrt\Model;

final class ProcessingStats
{
    public int $wordsProcessed = 0;
    public int $segmentsCreated = 0;
    public int $speakerChanges = 0;
    public int $pauseBreaks = 0;
    public int $segmentsMerged = 0;
    public int $timingCorrections = 0;
    public int $correctionsFirstWords = 0;
    public int $correctionsLastWords = 0;
    public int $orphanBreaksPrevented = 0;
    public int $intelligentBuffersApplied = 0;
    public int $segmentsCapped = 0;

    public function format(): string
    {
        $output = "\nProcessing Statistics:\n";
        $output .= str_repeat('=', 50) . "\n";
        $output .= "Total words processed: {$this->wordsProcessed}\n";
        $output .= "Subtitle segments created: {$this->segmentsCreated}\n";
        $output .= "Speaker changes detected: {$this->speakerChanges}\n";
        $output .= "Pause breaks detected: {$this->pauseBreaks}\n";
        $output .= "Short segments merged: {$this->segmentsMerged}\n";
        $output .= "Intelligent buffers applied: {$this->intelligentBuffersApplied}\n";
        $output .= "Segments capped/split by duration: {$this->segmentsCapped}\n";
        $output .= "Orphan word breaks prevented: {$this->orphanBreaksPrevented}\n";
        $output .= "Timing corrections applied: {$this->timingCorrections}\n";
        $output .= "  - First word corrections: {$this->correctionsFirstWords}\n";
        $output .= "  - Last word corrections: {$this->correctionsLastWords}\n";
        return $output;
    }
}
