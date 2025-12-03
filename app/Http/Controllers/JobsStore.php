<?php

namespace SpeechToTextPlugin\Http\Controllers;

use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpUnauthorizedException;
use SpeechToTextPlugin\Contracts\Services\PredictionServiceInterface;
use SpeechToTextPlugin\Exceptions\BadRequestException;
use SpeechToTextPlugin\Exceptions\InternalServerErrorException;
use SpeechToTextPlugin\Files\PublicFolder;
use SpeechToTextPlugin\Models\Job;
use SpeechToTextPlugin\Models\UserUpload;
use SpeechToTextPlugin\Traits\Authorizing;
use SpeechToTextPlugin\Exceptions\ApiCommunicationException;

/**
 * This controller handles the creation of new speech-to-text jobs. It validates the uploaded audio file, moves it to a
 * temporary location, creates a new job record, and starts the prediction process using the PredictionService.
 *
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(UnusedFormalParameter)
 */
class JobsStore extends JobsController
{
    use Authorizing;

    #[Inject]
    protected PredictionServiceInterface $predictionService;

    /**
     * Handle the incoming request to create a new speech-to-text job.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The HTTP response
     *
     * @return Response The redirect response after job creation
     *
     * @throws HttpUnauthorizedException        If the user is not authorized
     * @throws HttpBadRequestException          If the file upload is invalid
     * @throws HttpInternalServerErrorException If there's an error creating the job
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $user = \User::findCurrent();
        if ($this->cannot($user, 'create', Job::class)) {
            throw new HttpUnauthorizedException($request, 'User is not authorized to create a job.');
        }

        $job = Job::create(['user_id' => $user->id]);

        v::key('language', v::notEmpty())->assert($_POST);
        $language = \Request::get('language');

        $uploadedFile = $this->validateAndGetUploadedFile($request);
        $this->validateFileQuota($request, $job, $uploadedFile);

        $fileRef = $this->handleUpload($request, $job, $uploadedFile);
        $job->input_file_ref_id = $fileRef->id;
        $job->input_file_ref_name = $fileRef->name;
        $job->input_file_ref_size = $fileRef->file->size;

        $webhookUri = $this->getNamedRoute('jobs.webhook', absolute: true);

        try {
            $this->predictionService->startPrediction(
                $job,
                $webhookUri,
                language: $language,
            );
        } catch (ApiCommunicationException) {
            $flash = \Trails\Flash::instance();
            $flash->set('errors', ['api' => 'Fehler bei der Kommunikation mit dem Audiotranskriptionsdienst.']);
        }

        return $this->redirectToNamedRoute('jobs.index');
    }

    /**
     * Validate and retrieve the uploaded audio file from the request.
     *
     * @param Request $request The incoming HTTP request
     *
     * @return UploadedFileInterface The validated uploaded file
     *
     * @throws HttpBadRequestException If the file is invalid or missing
     */
    protected function validateAndGetUploadedFile(Request $request): UploadedFileInterface
    {
        $uploadedFiles = $request->getUploadedFiles();

        v::key('audio', v::notEmpty())->assert($uploadedFiles);

        return $uploadedFiles['audio'];
    }

    /**
     * Validate file size and user quota.
     *
     * @param Request               $request      The incoming HTTP request
     * @param Job                   $job          The job associated with the upload
     * @param UploadedFileInterface $uploadedFile The uploaded file
     *
     * @throws HttpBadRequestException   If the file would exceed the user's quota
     */
    protected function validateFileQuota(Request $request, Job $job, UploadedFileInterface $uploadedFile): void
    {
        $filesize = $uploadedFile->getSize();
        if (!$filesize) {
            throw new \RuntimeException("TODO: uploaded file with a size of 0");
        }
        if (!UserUpload::isWithinQuota($job->user, $filesize)) {
            throw new HttpBadRequestException($request, 'The file cannot be uploaded because it would exceed the quota.');
        }
    }

    /**
     * Handle the file upload process.
     *
     * @param Request               $request      The incoming HTTP request
     * @param Job                   $job          This file's job
     * @param UploadedFileInterface $uploadedFile The uploaded file
     *
     * @return \FileRef The created file reference
     *
     * @throws HttpBadRequestException          If there's an error validating the upload
     * @throws HttpInternalServerErrorException If there's an error adding the file to the folder
     */
    protected function handleUpload(Request $request, Job $job, UploadedFileInterface $uploadedFile): \FileRef
    {
        try {
            $file = $this->createStandardFileFromUpload($uploadedFile, $job->user);
            $addedFile = $job->addInputFile($file);
        } catch (InternalServerErrorException $internalServerException) {
            throw new HttpInternalServerErrorException($request, $internalServerException->getMessage());
        } catch (HttpBadRequestException $badRequestException) {
            throw new HttpBadRequestException($request, $badRequestException->getMessage());
        }

        return $addedFile->getFileRef();
    }

    /**
     * Move the uploaded file to a temporary location.
     *
     * @param UploadedFileInterface $uploadedFile The uploaded file
     *
     * @return string The path to the moved file
     */
    protected function moveUploadedFile(UploadedFileInterface $uploadedFile): string
    {
        $directory = $GLOBALS['TMP_PATH'];
        $extension = pathinfo((string) $uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Create a StandardFile from an uploaded file.
     *
     * @param UploadedFileInterface $uploadedFile The uploaded file
     * @param \User $user The user who uploaded the file
     *
     * @return \StandardFile The created standard file or an array of errors.
     * @throws InternalServerErrorException If there's an error creating the file from the uploaded file
     */
    protected function createStandardFileFromUpload(UploadedFileInterface $uploadedFile, \User $user): \StandardFile
    {
        $tmpFilename = $this->moveUploadedFile($uploadedFile);

        $file = \StandardFile::create([
            'name' => $uploadedFile->getClientFilename(),
            'type' => $uploadedFile->getClientMediaType(),
            'size' => $uploadedFile->getSize(),
            'user_id' => $user->id,
            'tmp_name' => $tmpFilename,
            'description' => '',
            'content_terms_of_use_id' => 0,
        ]);

        if (is_array($file)) {
            throw new InternalServerErrorException();
        }

        return $file;
    }
}