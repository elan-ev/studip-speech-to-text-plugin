<?php

namespace SpeechToTextPlugin\Http\Controllers;

use SpeechToTextPlugin\Models\Job;
use SpeechToTextPlugin\Traits\Authorizing;

/**
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(UnusedFormalParameter)
 */
class JobsController extends Controller
{
    use Authorizing;

    protected function transformJobToResource(Job $job): array
    {
        $outputs = array_reduce(
            $job->getOutputFileRefs(),
            function($memo, $fileRef) {
                if (preg_match('/(?:\.([^.]+))?$/', (string) $fileRef->name, $matches)) {
                    if (isset($matches[1])) {
                        $memo[$matches[1]] = [
                            'name' => $fileRef->name,
                            'filesize' => (int) $fileRef->file->size,
                            'url' => $fileRef->getDownloadURL(),
                        ];
                    }
                }
                return $memo;
            },
            []
        );

        return [
            'id' => $job->id,
            'input_file_ref' => [
                'name' => $job->input_file_ref_name,
                'filesize' => (int) $job->input_file_ref_size,
            ],
            'output_file_refs' => $outputs,
            'prediction' => json_decode($job->prediction, true, JSON_THROW_ON_ERROR),
            'status' => $job->status,
            'mkdate' => date('c', $job->mkdate),
            'chdate' => date('c', $job->chdate),
        ];
    }
}
