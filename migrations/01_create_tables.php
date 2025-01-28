<?php

class CreateTables extends Migration
{
    public function description()
    {
        return 'Creates the jobs table in the database.';
    }

    public function up()
    {
        $dbm = DBManager::get();

        $dbm->exec('
            CREATE TABLE `speech_to_text_jobs` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `input_file_ref_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
            `input_file_ref_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `input_file_ref_size` int unsigned NOT NULL,
            `prediction` JSON,
            `status` VARCHAR(32) NOT NULL,
            `started_at` DATETIME(6),
            `completed_at` DATETIME(6),
            `mkdate` int(11) NOT NULL,
            `chdate` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_input_file_ref` (`user_id`,`input_file_ref_id`),
            INDEX `index_user_id` (`user_id`))');


        $dbm->exec('
            CREATE TABLE `speech_to_text_user_uploads` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `file_size` BIGINT NOT NULL,
            `mkdate` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `index_user_id` (`user_id`),
            INDEX `index_mkdate` (`mkdate`))');
    }

    public function down()
    {
        $dbm = DBManager::get();
        $dbm->exec('DROP TABLE IF EXISTS `speech_to_text_user_uploads`');
        $dbm->exec('DROP TABLE IF EXISTS `speech_to_text_jobs`');
    }
}
