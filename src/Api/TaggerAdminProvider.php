<?php

declare(strict_types=1);

namespace Horde\Content\Api;

use Content_Tagger;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\Dispatch\Result;
use RuntimeException;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */
class TaggerAdminProvider implements ApiProviderInterface, MethodInvokerInterface
{
    /** @var array<string, MethodDescriptor> */
    private readonly array $descriptors;

    public function __construct(
        private readonly Content_Tagger $tagger,
    ) {
        $this->descriptors = $this->buildDescriptors();
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        if (!$this->isAdmin($context)) {
            return false;
        }

        return isset($this->descriptors[$method]);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        if (!$this->isAdmin($context)) {
            return null;
        }

        return $this->descriptors[$method] ?? null;
    }

    /** @return list<MethodDescriptor> */
    public function listMethods(?ApiCallContext $context = null): array
    {
        if (!$this->isAdmin($context)) {
            return [];
        }

        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        if (!$this->isAdmin($context)) {
            throw new RuntimeException('Unauthorized: admin permission required');
        }

        if (!isset($this->descriptors[$method])) {
            throw new RuntimeException(sprintf('Unknown method "%s"', $method));
        }

        return match ($method) {
            'listTagsByUser' => $this->listTagsByUser($params[0]),
            'listTaggedObjectsByUser' => $this->listTaggedObjectsByUser($params[0]),
            default => throw new RuntimeException(sprintf('Unknown method "%s"', $method)),
        };
    }

    private function isAdmin(?ApiCallContext $context): bool
    {
        $perms = $context?->getAttribute('permissions', []);

        return is_array($perms) && in_array('admin', $perms, true);
    }

    private function listTagsByUser(mixed $userId): Result
    {
        return new Result($this->tagger->getTags(['userId' => $userId]));
    }

    private function listTaggedObjectsByUser(mixed $userId): Result
    {
        $tags = $this->tagger->getTags(['userId' => $userId]);
        if (empty($tags)) {
            return new Result([]);
        }

        return new Result(
            $this->tagger->getObjects(['tagId' => array_keys($tags), 'userId' => $userId])
        );
    }

    /** @return array<string, MethodDescriptor> */
    private function buildDescriptors(): array
    {
        return [
            'listTagsByUser' => new MethodDescriptor(
                name: 'listTagsByUser',
                description: 'List all tags applied by a specific user',
                parameters: [
                    ['name' => 'userId', 'type' => 'mixed', 'required' => true, 'description' => 'User identifier'],
                ],
                returnType: 'array',
                permissions: ['admin'],
            ),
            'listTaggedObjectsByUser' => new MethodDescriptor(
                name: 'listTaggedObjectsByUser',
                description: 'List all objects tagged by a specific user',
                parameters: [
                    ['name' => 'userId', 'type' => 'mixed', 'required' => true, 'description' => 'User identifier'],
                ],
                returnType: 'array',
                permissions: ['admin'],
            ),
        ];
    }
}
