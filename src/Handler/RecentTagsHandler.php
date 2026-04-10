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
use Horde\Content\View\TagListView;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Most recently used tags.
 *
 * GET /tags/recent?limit=10&typeId=...&userId=...
 * Supports JSON (default), HTML, Atom and RSS output formats.
 */
class RecentTagsHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Content_Tagger $tagger,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $args = [];
        if (isset($params['limit']) && $params['limit'] !== '') {
            $args['limit'] = (int) $params['limit'];
        } else {
            $args['limit'] = 10;
        }
        if (isset($params['offset']) && $params['offset'] !== '') {
            $args['offset'] = (int) $params['offset'];
        }
        if (isset($params['typeId']) && $params['typeId'] !== '') {
            $args['typeId'] = $params['typeId'];
        }
        if (isset($params['userId']) && $params['userId'] !== '') {
            $args['userId'] = $params['userId'];
        }

        try {
            $tags = $this->tagger->getRecentTags($args);
        } catch (Content_Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }

        $format = $this->detectFormat($request);

        return match ($format) {
            'html' => $this->htmlResponse($tags),
            'atom' => $this->feedResponse($tags, 'Atom'),
            'rss' => $this->feedResponse($tags, 'Rss'),
            default => $this->jsonResponse($tags),
        };
    }

    private function detectFormat(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        if (isset($params['format'])) {
            return $params['format'];
        }
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/atom+xml')) {
            return 'atom';
        }
        if (str_contains($accept, 'application/rss+xml')) {
            return 'rss';
        }
        if (str_contains($accept, 'text/html')) {
            return 'html';
        }

        return 'json';
    }

    private function jsonResponse(mixed $data, int $status = 200): ResponseInterface
    {
        $body = $this->streamFactory->createStream(json_encode($data));

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    private function htmlResponse(array $tags): ResponseInterface
    {
        $view = new TagListView();
        $html = $view->renderRecentTags($tags);
        $body = $this->streamFactory->createStream($html);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($body);
    }

    private function feedResponse(array $tags, string $type): ResponseInterface
    {
        $contentType = ($type === 'Atom')
            ? 'application/atom+xml'
            : 'application/rss+xml';

        $xml = ($type === 'Atom')
            ? $this->buildAtomFeed('tags/recent', 'Recent tags', $tags)
            : $this->buildRssFeed('Recent tags', $tags);

        $body = $this->streamFactory->createStream($xml);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($body);
    }

    private function buildAtomFeed(string $id, string $title, array $tags): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<feed xmlns="http://www.w3.org/2005/Atom">'
            . '<id>' . htmlspecialchars($id) . '</id>'
            . '<title>' . htmlspecialchars($title) . '</title>';

        foreach ($tags as $tag) {
            $xml .= '<entry>'
                . '<id>tag/' . htmlspecialchars((string) $tag['tag_id']) . '</id>'
                . '<title>' . htmlspecialchars($tag['tag_name']) . '</title>'
                . '<updated>' . htmlspecialchars($tag['created']) . '</updated>'
                . '</entry>';
        }

        return $xml . '</feed>';
    }

    private function buildRssFeed(string $title, array $tags): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel>'
            . '<title>' . htmlspecialchars($title) . '</title>';

        foreach ($tags as $tag) {
            $xml .= '<item>'
                . '<title>' . htmlspecialchars($tag['tag_name']) . '</title>'
                . '<guid>tag/' . htmlspecialchars((string) $tag['tag_id']) . '</guid>'
                . '<pubDate>' . htmlspecialchars($tag['created']) . '</pubDate>'
                . '</item>';
        }

        return $xml . '</channel></rss>';
    }
}
