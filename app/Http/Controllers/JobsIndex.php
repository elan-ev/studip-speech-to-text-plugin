<?php

namespace SpeechToTextPlugin\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SpeechToTextPlugin\Models\UserUpload;

/**
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(UnusedFormalParameter)
 */
class JobsIndex extends Controller
{
    public function __invoke(Request $request, Response $response): Response
    {
        \Navigation::activateItem('/contents/speech-to-text/index');

        $user = \User::findCurrent();

        $page = [
            'usage' => UserUpload::getUsage($user),
            'MAX_UPLOAD' => 1024 * 1024 * 1024 * 1,
            'QUOTA' => UserUpload::getQuota($user),
        ];
        $body = $this->view('app.php', compact('page'));

        return $this->ok($body);
    }
}