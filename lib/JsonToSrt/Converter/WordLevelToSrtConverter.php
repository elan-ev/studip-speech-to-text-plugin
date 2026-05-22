<?php

namespace JsonToSrt\Converter;

use JsonToSrt\Model\ProcessingStats;
use JsonToSrt\Service\DurationAnalyzer;
use JsonToSrt\Service\TimingCorrector;
use JsonToSrt\Service\TextWrapper;
use JsonToSrt\Service\SegmentCreator;
use JsonToSrt\Service\SegmentProcessor;
use JsonToSrt\Service\SrtFormatter;
use JsonToSrt\Service\JsonLoader;

class WordLevelToSrtConverter
{
    private DurationAnalyzer $durationAnalyzer;
    private TimingCorrector $timingCorrector;
    private TextWrapper $textWrapper;
    private SegmentCreator $segmentCreator;
    private SegmentProcessor $segmentProcessor;
    private SrtFormatter $srtFormatter;
    private JsonLoader $jsonLoader;

    public function __construct(
        float $pauseThreshold = 3.0,
        int $maxCharsPerLine = 40,
        int $maxLines = 2,
        int $overflowTolerance = 4,
        float $maxSubtitleDuration = 15.0,
        bool $applyTimingCorrections = true,
        float $timingCorrectionThreshold = 3.0,
        bool $preventOrphans = true,
        int $orphanMoveThreshold = 15,
        bool $breakOnSpeakerChange = true,
        float $speakerChangeThreshold = 0.150,
        int $safetyGapMs = 10,
    ) {
        $this->durationAnalyzer = new DurationAnalyzer();
        $this->timingCorrector = new TimingCorrector(
            $this->durationAnalyzer,
            $timingCorrectionThreshold
        );
        $this->textWrapper = new TextWrapper(
            $maxCharsPerLine,
            $maxLines,
            $overflowTolerance
        );
        $this->segmentCreator = new SegmentCreator(
            $this->textWrapper,
            $pauseThreshold,
            $breakOnSpeakerChange,
            $speakerChangeThreshold,
            $preventOrphans,
            $orphanMoveThreshold,
            $maxLines,
        );
        $this->segmentProcessor = new SegmentProcessor(
            $maxSubtitleDuration,
            $safetyGapMs / 1000.0
        );
        $this->srtFormatter = new SrtFormatter($this->textWrapper);
        $this->jsonLoader = new JsonLoader(
            $this->durationAnalyzer,
            $this->timingCorrector,
            $applyTimingCorrections,
        );
    }

    /**
     * Process JSON content and generate SRT content
     *
     * @return array{srtWithSpeakers: string, srtClean: string, stats: ProcessingStats}
     */
    public function processJson(string $jsonContent): array
    {
        $result = $this->jsonLoader->loadFromString($jsonContent);
        $words = $result['words'];
        $stats = $result['stats'];
        
        $segments = $this->segmentCreator->createSegments($words, $stats);
        $segments = $this->segmentProcessor->applyIntelligentBuffersAndCaps($segments, $stats);
        $segments = $this->segmentProcessor->mergeShortSegments($segments, $stats);
        $srtWithSpeakers = $this->srtFormatter->generateSrtContent($segments, true);
        $srtClean = $this->srtFormatter->generateSrtContent($segments, false);

        return [
            'srtWithSpeakers' => $srtWithSpeakers,
            'srtClean' => $srtClean,
            'stats' => $stats,
        ];
    }
}
