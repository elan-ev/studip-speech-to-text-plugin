<?php

namespace SpeechToTextPlugin\Services;

use GuzzleHttp\Psr7;
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
class WhisperxApiPredictionService implements PredictionServiceInterface
{
    use LogsErrors;

    /**
     * JSON encoding options used throughout the class.
     */
    private const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private string $whisperxApiUrl,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Initiates a speech-to-text prediction job with a `whisperx-api` instance.
     *
     * This method starts the prediction process by validating the job input,
     * retrieving the audio URL, setting up the webhook, and creating the prediction
     * via the `whisperx-api` instance. It then updates the job status and stores the prediction data.
     *
     * @param Job          $job        The job entity containing input file reference and metadata
     * @param UriInterface $webhookUri The base URI for webhook callbacks
     * @param string       $language   The code of the language, 'de' by default
     *
     * @throws InputValidationException  When job input validation fails
     * @throws ApiCommunicationException When communication with `whisperx-api` fails
     * @throws FileOperationException    When file operations fail
     */
    public function startPrediction(Job $job, UriInterface $webhookUri, string $language = 'de'): void
    {
        $this->logInfo('Started prediction for job %d', $job->id);

        try {
            $this->validateJobInput($job);
            $prediction = $this->createPrediction(
                $this->getAudioUrl($job),
                (string) $this->getWebhookUri($job, $webhookUri),
                $language,
            );

            $job->prediction = json_encode($prediction, self::JSON_OPTIONS);
            $job->status = 'started';
            $job->store();
        } catch (InputValidationException|ApiCommunicationException|FileOperationException $e) {
            // Handle specific exceptions
            $this->handleJobError($job, $e);
            throw $e;
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions
            $this->handleJobError($job, $e);
            throw new ApiCommunicationException('Unexpected error during prediction start: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Processes webhook callbacks from the `whisperx-api` instance.
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
        } catch (WebhookException|InputValidationException|FileOperationException $e) {
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
     * Creates a prediction using the `whisperx-api` instance.
     *
     * @param string $audioUrl URL to the audio file
     * @param string $webhookUrl  URL for webhook notifications
     * @param string $language code of the language, 'de' by default
     */
    private function createPrediction(string $audioUrl, string $webhookUrl, $language)
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->whisperxApiUrl,
            'timeout' => 2.0,
        ]);

        try {
            $response = $client->request('POST', '/jobs', [
                'form_params' => [
                    'lang' => $language,
                    'model' => 'small',
                    'file_url' => $audioUrl,
                    'webhook_url' => $webhookUrl,
                    // 'min_speakers' => 0,
                    // 'max_speakers' => 0,
                ]
            ]);
            $code = $response->getStatusCode();
            if ($code !== 200) {
                throw new \RuntimeException();
            }
            $body = json_decode((string) $response->getBody(), true);

            return [
                "id" => $body['task_id'],
                "input" => [
                    "audio" => $audioUrl,
                    "webhook" => $webhookUrl,
                ],
                "logs" => "",
                "output" => null,
                "error" => null,
                "status" => $body['status'],
                "created_at" => date("c", time()),
                "urls" => [
                    "get" => $this->whisperxApiUrl . '/jobs/' . $body['task_id'],
                ]
            ];

        } catch (\Exception $e) {
            throw new ApiCommunicationException('Failed to create prediction: '.$e->getMessage(), 0, $e);
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
        $params = ['from' => 'whisperx-api', 'job_id' => $job->id];
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
            throw new WebhookException('Invalid webhook parameters: '.$e->getMessage());
        }

        if ('whisperx-api' !== $queryParams['from']) {
            throw new WebhookException('Unknown webhook source: '.$queryParams['from']);
        }

        $jobId = (int) $queryParams['job_id'];
        $job = Job::find($jobId);

        if (!$job) {
            throw new WebhookException('Could not find job: '.$jobId);
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
        $validStatuses = JobStatus::names();
        if (!in_array($prediction['status'], $validStatuses)) {
            throw new WebhookException('Invalid prediction status: '.$prediction['status']);
        }

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
        if (!isset($prediction['output']) || empty($prediction['output'])) {
            throw new InputValidationException('Prediction output text is missing');
        }
        $output = $prediction['output'];

        if (isset($output['json_content'])) {
            $this->logInfo('Write json %s', json_encode($output['json_content']));
            $job->writePrediction('json', $output['json_content']);
        }
        if (isset($output['srt_content'])) {
            $this->logInfo('Write srt %s', json_encode($output['srt_content']));
            $job->writePrediction('srt', $output['srt_content']);
        }
        if (isset($output['txt_content'])) {
            $this->logInfo('Write txt %s', json_encode($output['txt_content']));
            $job->writePrediction('txt', $output['txt_content']);
        }
        if (isset($output['vtt_content'])) {
            $this->logInfo('Write vtt %s', json_encode($output['vtt_content']));
            $job->writePrediction('vtt', $output['vtt_content']);
        }

        unset($prediction['output']);

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
}
