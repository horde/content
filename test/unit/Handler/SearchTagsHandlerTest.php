<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Handler;

use Content_Tagger;
use Horde\Content\Handler\SearchTagsHandler;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SearchTagsHandlerTest extends TestCase
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
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getTags')->willReturn([1 => 'work', 2 => 'play']);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.com/tags');

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(['1' => 'work', '2' => 'play'], $data);
    }

    public function testEmptyResultReturnsEmptyJson(): void
    {
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getTags')->willReturn([]);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.com/tags');

        $response = $handler->handle($request);

        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals([], $data);
    }

    public function testPassesQueryParamsToTagger(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with($this->callback(function (array $args) {
                return $args['q'] === 'wor'
                    && $args['typeId'] === 'event'
                    && $args['userId'] === 'alice';
            }))
            ->willReturn([2 => 'work']);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags')
            ->withQueryParams(['q' => 'wor', 'typeId' => 'event', 'userId' => 'alice']);

        $handler->handle($request);
    }

    public function testHtmlResponseWhenFormatParam(): void
    {
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getTags')->willReturn([1 => 'work']);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags')
            ->withQueryParams(['format' => 'html']);

        $response = $handler->handle($request);

        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<ul>', $body);
        $this->assertStringContainsString('work', $body);
    }

    public function testAtomResponseWhenFormatParam(): void
    {
        $tagger = $this->createStub(Content_Tagger::class);
        $tagger->method('getTags')->willReturn([1 => 'work']);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags')
            ->withQueryParams(['format' => 'atom']);

        $response = $handler->handle($request);

        $this->assertStringContainsString('application/atom+xml', $response->getHeaderLine('Content-Type'));
    }

    public function testIgnoresEmptyParams(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with([])
            ->willReturn([]);

        $handler = new SearchTagsHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('GET', 'https://example.com/tags')
            ->withQueryParams(['q' => '', 'typeId' => '', 'userId' => '']);

        $handler->handle($request);
    }
}
