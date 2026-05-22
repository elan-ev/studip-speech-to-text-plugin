<?php

namespace SpeechToTextPlugin\JsonApi\Schemas;

use JsonApi\Schemas\SchemaProvider;
use SpeechToTextPlugin\Models\Job as JobModel;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;

class Job extends SchemaProvider
{
    public const TYPE = 'speechtotext-jobs';

    /**
     * {@inheritdoc}
     * @param JobModel $resource
     */
    public function getId($resource): ?string
    {
        return (string) $resource->id;
    }

    /**
     * {@inheritdoc}
     * @param JobModel $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $outputs = array_reduce(
            $resource->getOutputFileRefs(),
            function ($memo, $fileRef) {
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
            'input-file-ref' => [
                'name' => $resource->input_file_ref_name,
                'filesize' => (int) $resource->input_file_ref_size,
            ],
            'output-file-refs' => $outputs,
            'diarize' => (bool) $resource->diarize,
            'language' => (string) $resource->language,
            'prediction' => json_decode($resource->prediction, true, JSON_THROW_ON_ERROR),
            'status' => $resource->status,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate),
         ];
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        return $relationships;
    }
}