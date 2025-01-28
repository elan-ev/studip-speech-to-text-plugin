<?php

namespace SpeechToTextPlugin\Models;

use SpeechToTextPlugin\Traits\Policable;
use SpeechToTextPlugin\Exceptions\ApiCommunicationException;
use SpeechToTextPlugin\Exceptions\FileOperationException;
use SpeechToTextPlugin\Exceptions\InputValidationException;
use SpeechToTextPlugin\Exceptions\WebhookException;
use SpeechToTextPlugin\Exceptions\BadRequestException;
use SpeechToTextPlugin\Exceptions\InternalServerErrorException;
use SpeechToTextPlugin\Files\PublicFolder;
use SpeechToTextPlugin\Models\UserUpload;

/**
 * Job.php
 * model class for table speech_to_text_jobs.
 */
class Job extends \SimpleORMap
{
    use Policable;

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

    public static function findByUser(\User $user): array
    {
        return self::findBySql('user_id = ? ORDER BY mkdate', [$user->id]);
    }

    public static function latest(\User $user): ?Job
    {
        return self::findOneBySql('user_id = ? ORDER BY chdate DESC', [$user->id]);
    }

    public function writePrediction(string $extension, string $prediction): \FileType
    {
        $tmpName = null;

        try {
            $tmpName = $this->writeTemporaryFile($prediction);

            $name = $this->input_file_ref_name.'.'.$extension;
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
                throw new FileOperationException('Could not create file: '.($result['error'] ?? 'Unknown error'));
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
            throw new FileOperationException('Error writing to temporary file: '.$e->getMessage(), 0, $e);
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

    public function getOutputFileRefs(): array
    {
        $folder = PublicFolder::findOrCreateTopFolder($this);
        if (!$folder) {
            throw new InternalServerErrorException('Could not find or create public folder.');
        }

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
