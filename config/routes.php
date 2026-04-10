<?php

/**
 * Content application routes.
 *
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Content
 *
 * References:
 *   http://code.google.com/apis/gdata/docs/2.0/reference.html#Queries
 */

use Horde\Content\Handler\SearchTagsHandler;
use Horde\Content\Handler\RecentTagsHandler;
use Horde\Content\Handler\TagHandler;
use Horde\Content\Handler\UntagHandler;

// List tags. With no parameters, lists all tags.
// Available query parameters:
//   q:         starts-with search on tag text
//   typeId:    restrict to tags applied to objects with type $typeId
//   userId:    restrict to tags applied by $userId
//   objectId:  restrict to tags applied to $objectId
//   format:    response format (json, html, atom, rss)
$mapper->buildRoute(uri: '/tags', name: 'SearchTags')
    ->withController(SearchTagsHandler::class)
    ->get()
    ->add();

// Most recent tags.
// Available query parameters:
//   limit:     maximum number of tags (default 10)
//   offset:    result offset for pagination
//   typeId:    restrict to tags applied to objects with type $typeId
//   userId:    restrict to tags applied by $userId
//   format:    response format (json, html, atom, rss)
$mapper->buildRoute(uri: '/tags/recent', name: 'RecentTags')
    ->withController(RecentTagsHandler::class)
    ->get()
    ->add();

// Tag an object. Required POST parameters: userId, objectId, tags, typeId.
$mapper->buildRoute(uri: '/tag', name: 'Tag')
    ->withController(TagHandler::class)
    ->withMethods(['POST', 'PUT'])
    ->add();

// Untag an object. Required POST parameters: userId, objectId, tags, typeId.
$mapper->buildRoute(uri: '/untag', name: 'Untag')
    ->withController(UntagHandler::class)
    ->withMethods(['POST', 'DELETE'])
    ->add();
