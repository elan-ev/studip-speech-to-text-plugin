<?php

namespace SpeechToTextPlugin\Models;

/**
 * UserUpload.php
 * model class for table speech_to_text_user_uploads.
 */
class UserUpload extends \SimpleORMap
{
    public const QUOTA = 10 * 1024 * 1024 * 1024;

    protected static function configure($config = [])
    {
        $config['db_table'] = 'speech_to_text_user_uploads';

        $config['belongs_to']['user'] = [
            'class_name' => \User::class,
            'foreign_key' => 'user_id',
        ];

        parent::configure($config);
    }

    /**
     * Creates a new UserUpload record for the given file.
     *
     * @param \FileType $file The file for which the UserUpload record is being created.
     *
     * @return bool Returns true if the UserUpload record is successfully created, false otherwise.
     */
    public static function createForFile(\FileType $file): bool
    {
        return self::create([
            'user_id' => $file->user_id,
            'file_size' => $file->size,
        ]) !== null;
    }

    /**
     * Checks if the user's upload quota is within the allowed limit.
     *
     * @param \User $user The user for whom the quota check is being performed.
     * @param int $size The size of the file that will be uploaded.
     *
     * @return bool Returns true if the user's current usage plus the size of the file to be uploaded
     *              does not exceed the quota limit, false otherwise.
     */
    public static function isWithinQuota(\User $user, int $size): bool
    {
        return $size + self::getUsage($user) <= self::QUOTA;
    }

    /**
     * Retrieves the total file size uploaded by the user in the current month.
     *
     * @param \User $user The user for whom the upload usage is being calculated.
     *
     * @return int The total file size uploaded by the user in the current month, in bytes.
     *             If no files have been uploaded by the user in the current month, returns 0.
     */
    public static function getUsage(\User $user): int
    {
        $since = strtotime(date("Y-m-01 00:00:00"));
        $stmt = \DBManager::get()->prepare(
            "SELECT SUM(file_size) FROM speech_to_text_user_uploads
             WHERE user_id = :user_id AND mkdate >= :since"
        );
        $stmt->execute([
            ':user_id' => $user->id,
            ':since' => $since,
        ]);
        $total = $stmt->fetchColumn();

        return $total ? (int) $total : 0;
    }
}
