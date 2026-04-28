<?php

declare(strict_types=1);

namespace Horde\Content\Api;

use Content_Tagger;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;
use RuntimeException;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */
class TaggerProvider implements ApiProvider, MethodInvoker
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
        return isset($this->descriptors[$method]);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        return $this->descriptors[$method] ?? null;
    }

    /** @return list<MethodDescriptor> */
    public function listMethods(?ApiCallContext $context = null): array
    {
        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        if (!isset($this->descriptors[$method])) {
            throw new RuntimeException(sprintf('Unknown method "%s"', $method));
        }

        return match ($method) {
            'tag' => new Result($this->tagger->tag($params[0], $params[1], $params[2], $params[3] ?? null)),
            'untag' => new Result($this->tagger->untag($params[0], $params[1], $params[2])),
            'removeTagFromObject' => new Result($this->tagger->removeTagFromObject($params[0], $params[1])),
            'getTags' => new Result($this->tagger->getTags($params[0])),
            'getTagsByObjects' => new Result($this->tagger->getTagsByObjects($params[0], $params[1])),
            'getTagCloud' => new Result($this->tagger->getTagCloud($params[0] ?? [])),
            'getRecentTags' => new Result($this->tagger->getRecentTags($params[0] ?? [])),
            'getObjects' => new Result($this->tagger->getObjects($params[0])),
            'getSimilarObjects' => new Result($this->tagger->getSimilarObjects($params[0], $params[1] ?? [])),
            'getRecentObjects' => new Result($this->tagger->getRecentObjects($params[0] ?? [])),
            'ensureTags' => new Result($this->tagger->ensureTags($params[0])),
            'getTagIds' => new Result($this->tagger->getTagIds($params[0])),
            'splitTags' => new Result($this->tagger->splitTags($params[0])),
            'browseTags' => new Result($this->tagger->browseTags($params[0], $params[1], $params[2])),
            default => throw new RuntimeException(sprintf('Unknown method "%s"', $method)),
        };
    }

    /** @return array<string, MethodDescriptor> */
    private function buildDescriptors(): array
    {
        return [
            'tag' => new MethodDescriptor(
                name: 'tag',
                description: 'Add tags to an object',
                parameters: [
                    ['name' => 'userId', 'type' => 'mixed', 'required' => true, 'description' => 'User applying the tags'],
                    ['name' => 'objectId', 'type' => 'mixed', 'required' => true, 'description' => 'Object ID or {object, type} array'],
                    ['name' => 'tags', 'type' => 'array', 'required' => true, 'description' => 'Tag names or IDs'],
                    ['name' => 'created', 'type' => 'string', 'required' => false, 'description' => 'Timestamp of tagging operation'],
                ],
                returnType: 'void',
            ),
            'untag' => new MethodDescriptor(
                name: 'untag',
                description: 'Remove a user\'s tags from an object',
                parameters: [
                    ['name' => 'userId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'objectId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'tags', 'type' => 'array', 'required' => true],
                ],
                returnType: 'void',
            ),
            'removeTagFromObject' => new MethodDescriptor(
                name: 'removeTagFromObject',
                description: 'Remove all user tags for a specific tag on an object',
                parameters: [
                    ['name' => 'objectId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'tags', 'type' => 'array', 'required' => true],
                ],
                returnType: 'void',
            ),
            'getTags' => new MethodDescriptor(
                name: 'getTags',
                description: 'Search and list tags with optional filters',
                parameters: [
                    ['name' => 'args', 'type' => 'array', 'required' => true, 'description' => 'Search criteria: q, limit, offset, userId, typeId, objectId'],
                ],
                returnType: 'array',
            ),
            'getTagsByObjects' => new MethodDescriptor(
                name: 'getTagsByObjects',
                description: 'Get tags for a set of objects',
                parameters: [
                    ['name' => 'objects', 'type' => 'array', 'required' => true, 'description' => 'Object identifiers'],
                    ['name' => 'type', 'type' => 'mixed', 'required' => true, 'description' => 'Object type name or ID'],
                ],
                returnType: 'array',
            ),
            'getTagCloud' => new MethodDescriptor(
                name: 'getTagCloud',
                description: 'Get tag cloud with usage counts',
                parameters: [
                    ['name' => 'args', 'type' => 'array', 'required' => false, 'description' => 'Filters: typeId, limit, userId'],
                ],
                returnType: 'array',
            ),
            'getRecentTags' => new MethodDescriptor(
                name: 'getRecentTags',
                description: 'Get most recently used tags',
                parameters: [
                    ['name' => 'args', 'type' => 'array', 'required' => false, 'description' => 'Filters: limit, offset, typeId, userId'],
                ],
                returnType: 'array',
            ),
            'getObjects' => new MethodDescriptor(
                name: 'getObjects',
                description: 'Find objects matching tag criteria',
                parameters: [
                    ['name' => 'args', 'type' => 'array', 'required' => true, 'description' => 'Criteria: tagId, notTagId, typeId, objectId, userId, limit, offset'],
                ],
                returnType: 'array',
            ),
            'getSimilarObjects' => new MethodDescriptor(
                name: 'getSimilarObjects',
                description: 'Find objects related to a given object via shared tags',
                parameters: [
                    ['name' => 'objectId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'args', 'type' => 'array', 'required' => false, 'description' => 'Filters: typeId, limit'],
                ],
                returnType: 'array',
            ),
            'getRecentObjects' => new MethodDescriptor(
                name: 'getRecentObjects',
                description: 'Get most recently tagged objects',
                parameters: [
                    ['name' => 'args', 'type' => 'array', 'required' => false, 'description' => 'Filters: typeId, limit'],
                ],
                returnType: 'array',
            ),
            'ensureTags' => new MethodDescriptor(
                name: 'ensureTags',
                description: 'Ensure tags exist, creating them if needed',
                parameters: [
                    ['name' => 'tags', 'type' => 'array', 'required' => true, 'description' => 'Tag names or IDs'],
                ],
                returnType: 'array',
            ),
            'getTagIds' => new MethodDescriptor(
                name: 'getTagIds',
                description: 'Get tag IDs for tag names without creating',
                parameters: [
                    ['name' => 'tags', 'type' => 'array', 'required' => true],
                ],
                returnType: 'array',
            ),
            'splitTags' => new MethodDescriptor(
                name: 'splitTags',
                description: 'Parse a CSV-like tag string into individual tags',
                parameters: [
                    ['name' => 'text', 'type' => 'string', 'required' => true],
                ],
                returnType: 'array',
            ),
            'browseTags' => new MethodDescriptor(
                name: 'browseTags',
                description: 'Get related tags for given tag IDs',
                parameters: [
                    ['name' => 'ids', 'type' => 'array', 'required' => true, 'description' => 'Tag IDs to find related tags for'],
                    ['name' => 'objectType', 'type' => 'mixed', 'required' => true, 'description' => 'Object type filter'],
                    ['name' => 'user', 'type' => 'mixed', 'required' => true, 'description' => 'User filter'],
                ],
                returnType: 'array',
            ),
        ];
    }
}
