<?php

namespace SpeechToTextPlugin\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use SpeechToTextPlugin\Contracts\Services\PredictionServiceInterface;
use SpeechToTextPlugin\Exceptions\ApiCommunicationException;
use SpeechToTextPlugin\Exceptions\FileOperationException;
use SpeechToTextPlugin\Exceptions\InputValidationException;
use SpeechToTextPlugin\Exceptions\WebhookException;
use SpeechToTextPlugin\Models\Job;
use SpeechToTextPlugin\Models\JobStatus;
use SpeechToTextPlugin\Traits\LogsErrors;

/**
 * @SuppressWarnings(StaticAccess)
 */
class MurmurAiPredictionService implements PredictionServiceInterface
{
    use LogsErrors;

    /**
     * JSON encoding options used throughout the class.
     */
    private const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private string $murmurAiUrl,
        private string $murmurAiApiKey,
        private LoggerInterface $logger,
    ) {
        $this->ensureHealth();
    }

    /**
     * Initiates a speech-to-text prediction job with a `murmurai` instance.
     *
     * This method starts the prediction process by validating the job input,
     * retrieving the audio URL, setting up the webhook, and creating the prediction
     * via the `murmurai` instance. It then updates the job status and stores the prediction data.
     *
     * @param Job          $job        The job entity containing input file reference and metadata
     * @param UriInterface $webhookUri The base URI for webhook callbacks
     * @param string       $language   The code of the language, 'de' by default
     * @param bool         $diarize    Should the transcription be diarized, false by default
     *
     * @throws InputValidationException  When job input validation fails
     * @throws ApiCommunicationException When communication with `murmurai` fails
     * @throws FileOperationException    When file operations fail
     */
    public function startPrediction(Job $job, UriInterface $webhookUri, string $language = 'de', bool $diarize = false): void
    {
        $this->logInfo('Started prediction for job %d', $job->id);

        try {
            $this->validateJobInput($job);
            $prediction = $this->createPrediction(
                $this->getAudioUrl($job),
                (string) $this->getWebhookUri($job, $webhookUri),
                $language,
                $diarize,
            );

            $job->prediction = json_encode($prediction, self::JSON_OPTIONS);
            $job->status = 'started';
            $job->store();
        } catch (InputValidationException | ApiCommunicationException | FileOperationException $e) {
            // Handle specific exceptions
            $this->handleJobError($job, $e);
            throw $e;
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions
            $this->handleJobError($job, $e);
            throw new ApiCommunicationException('Unexpected error during prediction start: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Processes webhook callbacks from the `murmurai` instance.
     *
     * This method handles incoming webhook notifications about prediction status changes,
     * validates the request data, updates the associated job, and processes the prediction
     * output when the job has successfully completed.
     *
     * @param Request  $request  The incoming HTTP request containing webhook data
     * @param Response $response The response object to be returned
     *
     * @return Response The HTTP response with appropriate status and message
     */
    public function processWebhook(Request $request, Response $response): Response
    {
        try {
            $job = $this->validateWebhookAndGetJob($request);
            $this->logInfo(
                "Received webhook for job %d with status: '%s'",
                $job->id,
                $job->status
            );

            $prediction = $this->validateAndExtractPrediction($request, $job);

            if ('succeeded' === $prediction['status']) {
                $prediction = $this->writeOutput($job, $prediction);
                $this->logInfo('Successfully wrote output for job %d', $job->id);
                \NotificationCenter::postNotification(Job::class . 'DidSucceed', $job);
            }

            $this->updateJobFromWebhook($job, $prediction);

            return $this->jsonResponse($response, ['status' => 'success']);
        } catch (WebhookException | InputValidationException | FileOperationException $e) {
            $this->logError('%s: %s', $e::class, $e->getMessage());

            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            $this->logError('Unhandled error in processWebhook: %s', $e->getMessage());

            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Validates that the job has the required input file reference.
     *
     * @param Job $job The job to validate
     *
     * @throws \RuntimeException If input file reference is missing
     */
    private function validateJobInput(Job $job): void
    {
        if (!$job->input_file_ref) {
            throw new InputValidationException('No input file reference found for job');
        }
    }

    /**
     * Gets the audio URL from the job's file reference.
     *
     * @param Job $job The job containing the file reference
     *
     * @return string The audio file URL
     *
     * @throws \RuntimeException If URL cannot be retrieved
     */
    private function getAudioUrl(Job $job): string
    {
        $audioUrl = $job->input_file_ref->getDownloadURL();
        if (empty($audioUrl)) {
            throw new InputValidationException('Could not get download URL for input file');
        }

        return $audioUrl;
    }

    /**
     * Creates a prediction using the `murmurai` instance.
     *
     * @param string $audioUrl URL to the audio file
     * @param string $webhookUrl  URL for webhook notifications
     * @param string $language code of the language, 'de' by default
     * @param bool $diarize Should the transcription be diarized, false by default
     */
    private function createPrediction(string $audioUrl, string $webhookUrl, $language, $diarize)
    {
        $client = $this->createHttpClient(['Authorization' => $this->murmurAiApiKey]);

        $formParams = [
            'audio_url' => $audioUrl,
            'language_code' => $language,
            'speaker_labels' => $diarize,
            'model' => 'small',
            'webhook_url' => $webhookUrl,
        ];

        try {
            $response = $client->request(
                'POST',
                '/v1/transcript',
                ['form_params' => $formParams,]
            );
            $code = $response->getStatusCode();
            if ($code !== 200) {
                throw new \RuntimeException();
            }
            $body = json_decode((string) $response->getBody(), true);

            return [
                "id" => $body['id'],
                "input" => [
                    "audio" => $body['audio_url'],
                    "webhook" => $webhookUrl,
                ],
                "logs" => "",
                "output" => null,
                "error" => $body['error'],
                "status" => $body['status'],
                "created_at" => date("c", time()),
                "urls" => [
                    "get" => $this->murmurAiUrl . '/v1/transcript/' . $body['id'],
                ]
            ];
        } catch (\Exception $e) {
            throw new ApiCommunicationException('Failed to create prediction: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Adds job ID and source to webhook URI.
     *
     * @param Job          $job The job to include in the webhook URI
     * @param UriInterface $uri The base webhook URI
     *
     * @return UriInterface The modified URI with job parameters
     */
    private function getWebhookUri(Job $job, UriInterface $uri): UriInterface
    {
        $params = ['from' => 'murmurai', 'job_id' => $job->id];
        parse_str($uri->getQuery(), $existingParams);
        $allParams = array_merge($existingParams, $params);
        $newQuery = http_build_query($allParams);

        return $uri->withQuery($newQuery);
    }

    /**
     * Validates webhook parameters and retrieves the associated job.
     *
     * @param Request $request The incoming HTTP request
     *
     * @return Job The job associated with the webhook
     *
     * @throws HttpBadRequestException If validation fails or job not found
     */
    private function validateWebhookAndGetJob(Request $request): Job
    {
        $queryParams = $request->getQueryParams();

        try {
            $validator = v::key('from', v::notEmpty())->key('job_id', v::intVal()->positive());
            $validator->assert($queryParams);
        } catch (\Exception $e) {
            throw new WebhookException('Invalid webhook parameters: ' . $e->getMessage());
        }

        if ('murmurai' !== $queryParams['from']) {
            throw new WebhookException('Unknown webhook source: ' . $queryParams['from']);
        }

        $jobId = (int) $queryParams['job_id'];
        $job = Job::find($jobId);

        if (!$job) {
            throw new WebhookException('Could not find job: ' . $jobId);
        }

        return $job;
    }

    /**
     * Validates and extracts prediction data from the webhook request.
     *
     * @param Request $request The incoming HTTP request
     * @param Job     $job     The job associated with the webhook
     *
     * @return array The validated prediction data
     *
     * @throws HttpBadRequestException If prediction data is invalid
     */
    private function validateAndExtractPrediction(Request $request, Job $job): array
    {
        $prediction = $request->getParsedBody();

        if (!is_array($prediction) || !isset($prediction['status'])) {
            throw new WebhookException('Invalid webhook payload');
        }

        // Validate that status is one of the expected values
        $status = match ($prediction['status']) {
            'queued' => 'starting',
            'completed' => 'succeeded',
            default => $prediction['status'],
        };
        if (!in_array($status, JobStatus::names())) {
            throw new WebhookException('Invalid prediction status: ' . $status);
        }
        $prediction['status'] = $status;

        return $prediction;
    }

    /**
     * Updates job with prediction data from webhook.
     *
     * @param Job   $job        The job to update
     * @param array $prediction The prediction data
     */
    private function updateJobFromWebhook(Job $job, array $prediction): void
    {
        $job->prediction = json_encode($prediction, self::JSON_OPTIONS);
        $job->status = $prediction['status'];
        $job->store();
    }

    /**
     * Writes the prediction output to a file.
     *
     * @param Job   $job        The job containing the prediction
     * @param array $prediction The prediction data
     *
     * @throws \Exception If there is an error writing the file
     */
    private function writeOutput(Job $job, array $prediction): array
    {
        if (!isset($prediction['text']) || empty($prediction['text'])) {
            throw new InputValidationException('Prediction text is missing');
        }

        $job->writePrediction('txt', $prediction['text']);
        unset($prediction['text']);

        if (isset($prediction['utterances'])) {
            $json = $prediction['utterances'];
            $job->writePrediction('json', json_encode($json));
            unset($prediction['utterances']);

            $subtitles = self::generateSubtitles($json);
            $job->writePrediction('srt', $subtitles['srt']);
            $job->writePrediction('vtt', $subtitles['vtt']);
        }

        return $prediction;
    }

    /**
     * Create a JSON response.
     *
     * @param Response $response The response object
     * @param array    $data     The data to encode as JSON
     * @param int      $status   The HTTP status code
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json');

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json);

        return $response;
    }

    /**
     * Handles a prediction error by updating job status and logging.
     *
     * @param Job        $job          The job that encountered an error
     * @param \Exception $exception    The exception that occurred
     * @param string     $customStatus Optional custom status to set instead of 'failed'
     */
    protected function handleJobError(Job $job, \Exception $exception, string $customStatus = 'failed'): void
    {
        $job->status = $customStatus;
        $job->prediction = json_encode([
            'error' => $exception->getMessage(),
            'timestamp' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $job->store();

        $this->logError('Job %d error (%s): %s', $job->id, $customStatus, $exception->getMessage());
    }

    protected function createHttpClient(array $headers = []): Client
    {
        return new Client([
            'base_uri' => $this->murmurAiUrl,
            'timeout' => 5.0,
            'headers' => $headers,
        ]);
    }

    // TODO better exception handling
    protected function ensureHealth(): void
    {
        $client = $this->createHttpClient();
        $request = new \GuzzleHttp\Psr7\Request(
            'GET',
            '/health',
        );
        $response = $client->send($request);
        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \RuntimeException();
        }
        $body = json_decode((string) $response->getBody(), true);
        if (!isset($body['status']) || $body['status'] !== 'ok') {
            throw new \RuntimeException();
        }
    }

    protected static function generateSubtitles(array $data): array
    {
        $srtContent = '';
        $vttContent = "WEBVTT\n\n";

        foreach ($data as $index => $item) {
            $start = self::formatTime($item['start']);
            $end   = self::formatTime($item['end']);
            $text  = trim($item['text']);
            $speaker = $item['speaker'] ?? null;

            $srtContent .= self::buildSrtBlock($index + 1, $start['srt'], $end['srt'], $text, $speaker);
            $vttContent .= self::buildVttBlock($start['vtt'], $end['vtt'], $text, $speaker);
        }

        return [
            'srt' => $srtContent,
            'vtt' => $vttContent,
        ];
    }

    private static function buildSrtBlock(int $number, string $start, string $end, string $text, ?string $speaker): string
    {
        $speakerPrefix = $speaker ? "[$speaker] " : '';
        return sprintf(
            "%d\n%s --> %s\n%s%s\n\n",
            $number,
            $start,
            $end,
            $speakerPrefix,
            $text
        );
    }

    private static function buildVttBlock(string $start, string $end, string $text, ?string $speaker): string
    {
        $speakerTag = $speaker ? sprintf('<v %s>', $speaker) : '';
        $speakerClose = $speaker ? '</v>' : '';
        return sprintf(
            "%s --> %s\n%s%s%s\n\n",
            $start,
            $end,
            $speakerTag,
            $text,
            $speakerClose
        );
    }

    /**
     * Format time for both SRT and VTT outputs.
     *
     * @param int $milliseconds
     * @return array{ srt: string, vtt: string }
     */
    private static function formatTime(int $milliseconds): array
    {
        $hours   = floor($milliseconds / 3600000);
        $minutes = floor(($milliseconds % 3600000) / 60000);
        $seconds = floor(($milliseconds % 60000) / 1000);
        $millis  = $milliseconds % 1000;

        return [
            'srt' => sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $seconds, $millis),
            'vtt' => sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $millis),
        ];
    }
}