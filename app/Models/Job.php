<?php

namespace SpeechToTextPlugin\Models;

use Psr\Log\LoggerInterface;
use SpeechToTextPlugin\Traits\Policable;
use SpeechToTextPlugin\Exceptions\FileOperationException;
use SpeechToTextPlugin\Exceptions\BadRequestException;
use SpeechToTextPlugin\Exceptions\InternalServerErrorException;
use SpeechToTextPlugin\Files\PublicFolder;
use SpeechToTextPlugin\Models\UserUpload;
use SpeechToTextPlugin\Traits\LogsErrors;

/**
 * Job.php
 * model class for table speech_to_text_jobs.
 *
 * @property string $id primary key
 * @property mixed $user_id
 * @property mixed $user
 * @property mixed $input_file_ref_id
 * @property mixed $input_file_ref
 * @property mixed $input_file_ref_name
 * @property mixed $input_file_ref_size
 * @property mixed $prediction
 * @property mixed $status
 * @property mixed $mkdate.
 * @property mixed $chdate.
 */
class Job extends \SimpleORMap
{
    use LogsErrors;
    use Policable;

    protected LoggerInterface $logger;

    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->logger = app()->get(LoggerInterface::class);
    }

    protected static function configure($config = [])
    {
        $config['db_table'] = 'speech_to_text_jobs';

        $config['belongs_to']['user'] = [
            'class_name' => \User::class,
            'foreign_key' => 'user_id',
        ];

        $config['has_one']['input_file_ref'] = [
            'class_name' => \FileRef::class,
            'foreign_key' => 'input_file_ref_id',
            'assoc_foreign_key' => 'id',
            'on_delete' => 'delete',
        ];

        parent::configure($config);
    }

    /**
     * Retrieves all jobs associated with a given user.
     *
     * @param \User $user The user for whom to retrieve jobs.
     *
     * @return array An array of Job objects associated with the given user,
     *               sorted by creation date in ascending order.
     *
     * @throws \Exception If an error occurs while executing the database query.
     */
    public static function findByUser(\User $user): array
    {
        return self::findBySql('user_id = ? ORDER BY mkdate', [$user->id]);
    }

    /**
     * Retrieves the latest job for a given user.
     *
     * @param \User $user The user for whom to retrieve the latest job.
     *
     * @return Job|null The latest job for the given user, or null if no job exists.
     *
     * @throws \Exception If an error occurs while executing the database query.
     */
    public static function latest(\User $user): ?Job
    {
        return self::findOneBySql('user_id = ? ORDER BY chdate DESC', [$user->id]);
    }

    /**
     * Writes the prediction to a temporary file and adds it to the job's public folder.
     *
     * @param string $extension The file extension for the prediction file.
     * @param string $prediction The prediction text to be written to the file.
     *
     * @return \FileType The FileType object representing the added prediction file.
     *
     * @throws FileOperationException If there's an error creating the temporary file,
     *                                writing to it, or adding the file to the folder.
     */
    public function writePrediction(string $extension, string $prediction): \FileType
    {
        $tmpName = null;

        try {
            $tmpName = $this->writeTemporaryFile($prediction);

            $name = $this->input_file_ref_name . '.' . $extension;
            $result = \StandardFile::create(
                [
                    'name' => $name,
                    'tmp_name' => $tmpName,
                    'type' => 'text/plain',
                    'user_id' => $this->user_id,
                ],
                $this->user_id
            );

            if (is_array($result)) {
                throw new FileOperationException('Could not create file: ' . ($result['error'] ?? 'Unknown error'));
            }

            $folder = PublicFolder::findOrCreateTopFolder($this);
            if (!$folder) {
                throw new FileOperationException('Could not find or create public folder');
            }

            $outputFileRef = $folder->addFile($result, $this->user_id);
            if (!$outputFileRef) {
                throw new FileOperationException('Failed to add file to folder');
            }

            return $outputFileRef;
        } finally {
            $this->cleanupTemporaryFile($tmpName);
        }
    }

    /**
     * Cleans up a temporary file if it exists.
     */
    private function cleanupTemporaryFile(?string $tmpName): void
    {
        if ($tmpName && file_exists($tmpName)) {
            try {
                if (!unlink($tmpName)) {
                    $this->logWarning('Failed to delete temporary file: %s', $tmpName);
                }
            } catch (\Exception $e) {
                $this->logWarning('Error deleting temporary file %s: %s', $tmpName, $e->getMessage());
            }
        }
    }

    /**
     * @SuppressWarnings(Superglobals)
     */
    private function writeTemporaryFile(string $string): string
    {
        if (!isset($GLOBALS['TMP_PATH']) || !is_dir($GLOBALS['TMP_PATH']) || !is_writable($GLOBALS['TMP_PATH'])) {
            throw new FileOperationException('Temporary directory is not available or not writable');
        }

        $tempFilePath = tempnam($GLOBALS['TMP_PATH'], 'speech_to_text_output_');
        if (false === $tempFilePath) {
            throw new FileOperationException('Failed to create a temporary file');
        }

        try {
            $result = file_put_contents($tempFilePath, $string);
            if (false === $result) {
                throw new FileOperationException('Failed to write to the temporary file');
            }

            return $tempFilePath;
        } catch (\Exception $e) {
            // Clean up the temp file if writing fails
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            throw new FileOperationException('Error writing to temporary file: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Add the input file to this job's folder and create a UserUpload record.
     *
     * @param \StandardFile $file The input file to add to the job folder
     *
     * @return \StandardFile The added file
     *
     * @throws BadRequestException          If there's an error validating the upload
     * @throws InternalServerErrorException If there's an error adding the file to the folder
     */
    public function addInputFile(\StandardFile $file): \StandardFile
    {
        $folder = PublicFolder::findOrCreateTopFolder($this);
        if (!$folder) {
            throw new InternalServerErrorException('Could not find or create public folder.');
        }

        $error = $folder->validateUpload($file, $this->user->id);
        if ($error) {
            throw new BadRequestException($error);
        }

        $addedFile = $folder->addFile($file);
        if (!$addedFile) {
            throw new InternalServerErrorException('Could not add file to folder.');
        }

        UserUpload::createForFile($addedFile);

        return $addedFile;
    }

    /**
     * Retrieves all output files associated with this job, excluding the input file.
     *
     * @return array An array of FileRef objects representing the output files.
     *
     * @throws InternalServerErrorException If the public folder cannot be found or created.
     */
    public function getOutputFileRefs(): array
    {
        // Find or create the public folder associated with this job
        $folder = PublicFolder::findOrCreateTopFolder($this);
        if (!$folder) {
            throw new InternalServerErrorException('Could not find or create public folder.');
        }

        // Retrieve all files in the folder, filter out the input file, and return their FileRef objects
        return array_values(
            array_filter(
                array_map(
                    fn($file) => $file->getFileRef(),
                    $folder->getFiles()
                ),
                fn($fileRef) => $fileRef->id !== $this->input_file_ref_id
            )
        );
    }
}