<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\FirebaseTokenService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Endpoints del chat familiar (ADR-011): E-54 y E-55.
 */
final class ChatController extends BaseApiController
{
    public function participantes(): ResponseInterface
    {
        return $this->ok((new FirebaseTokenService())->participantes());
    }

    public function token(): ResponseInterface
    {
        return $this->ok((new FirebaseTokenService())->emitirToken());
    }
}
