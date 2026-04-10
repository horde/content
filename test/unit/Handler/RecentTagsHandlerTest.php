<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Handler;

use Content_Tagger;
use Horde\Content\Handler\RecentTagsHandler;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RecentTagsHandlerTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;
    private RequestFactory $requestFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
        $this->requestFactory = new RequestFactory();
    }

    public function testJsonResponseDefault(): void
    {
        $tags = [
            ['tag_id' => 4, 'tag_name' => 'personal', 'created' => '2009-01-01 00:06:00'],
            ['tag_id' => 1, 'tag_name' => 'play', 'created' => '2008-01-01 00:10:00'],
        ];
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getRecentTags')->willReturn($tags);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.com/tags/recent');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('personal', $data[0]['tag_name']);
    }

    public function testDefaultLimit(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getRecentTags')
            ->with($this->callback(function (array $args) {
                return $args['limit'] === 10;
            }))
            ->willReturn([]);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.com/tags/recent');

        $handler->handle($request);
    }

    public function testCustomLimit(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getRecentTags')
            ->with($this->callback(function (array $args) {
                return $args['limit'] === 5;
            }))
            ->willReturn([]);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags/recent')
            ->withQueryParams(['limit' => '5']);

        $handler->handle($request);
    }

    public function testPassesFilterParams(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getRecentTags')
            ->with($this->callback(function (array $args) {
                return $args['typeId'] === 'event' && $args['userId'] === 'alice';
            }))
            ->willReturn([]);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags/recent')
            ->withQueryParams(['typeId' => 'event', 'userId' => 'alice']);

        $handler->handle($request);
    }

    public function testHtmlResponse(): void
    {
        $tags = [
            ['tag_id' => 1, 'tag_name' => 'work', 'created' => '2009-01-01 00:00:00'],
        ];
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getRecentTags')->willReturn($tags);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags/recent')
            ->withQueryParams(['format' => 'html']);

        $response = $handler->handle($request);

        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<ul>', $body);
        $this->assertStringContainsString('work', $body);
        $this->assertStringContainsString('data-created', $body);
    }

    public function testRssResponse(): void
    {
        $tags = [
            ['tag_id' => 1, 'tag_name' => 'work', 'created' => '2009-01-01 00:00:00'],
        ];
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getRecentTags')->willReturn($tags);

        $handler = new RecentTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags/recent')
            ->withQueryParams(['format' => 'rss']);

        $response = $handler->handle($request);

        $this->assertStringContainsString('application/rss+xml', $response->getHeaderLine('Content-Type'));
    }
}
