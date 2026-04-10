<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Content
 */

namespace Horde\Content\Handler;

use Content_Tagger;
use Content_Exception;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tag an object.
 *
 * POST /tag  (body: userId, objectId, tags, typeId)
 */
class TagHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Content_Tagger $tagger,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        $userId = $params['userId'] ?? null;
        $objectId = $params['objectId'] ?? null;
        $tags = $params['tags'] ?? null;
        $typeId = $params['typeId'] ?? null;

        if ($userId === null || $objectId === null || $tags === null || $typeId === null) {
            return $this->jsonResponse(
                ['error' => 'Missing required parameters: userId, objectId, tags, typeId'],
                400
            );
        }

        $object = ['type' => $typeId, 'object' => $objectId];

        try {
            $this->tagger->tag($userId, $object, $tags);
        } catch (Content_Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }

        return $this->responseFactory->createResponse(204);
    }

    private function jsonResponse(mixed $data, int $status): ResponseInterface
    {
        $body = $this->streamFactory->createStream(json_encode($data));

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
