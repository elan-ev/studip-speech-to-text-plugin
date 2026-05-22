<?php

class NormalizeJobs extends Migration
{
    public function description()
    {
        return 'Adds columns to jobs table.';
    }

    public function up()
    {
        $dbm = DBManager::get();

        $dbm->exec('ALTER TABLE `speech_to_text_jobs` ADD `diarize` BOOLEAN NOT NULL AFTER `input_file_ref_size`, ADD `language` VARCHAR(100) NOT NULL AFTER `diarize`');
    }

    public function down()
    {
        $dbm = DBManager::get();
        $dbm->exec('ALTER TABLE `speech_to_text_jobs` DROP `diarize`, DROP `language`');
    }
}