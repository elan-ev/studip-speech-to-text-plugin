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

    public static function createForFile(\FileType $file): bool
    {
        return self::create([
            'user_id' => $file->user_id,
            'file_size' => $file->size,
        ]) !== null;
    }

    public static function isWithinQuota(\User $user, int $size): bool
    {
        return $size + self::getUsage($user) <= self::QUOTA;
    }

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
