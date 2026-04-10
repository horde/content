<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Handler;

use Content_Tagger;
use Horde\Content\Handler\TagHandler;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TagHandlerTest extends TestCase
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

    public function testSuccessfulTag(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('tag')
            ->with(
                'alice',
                ['type' => 'event', 'object' => 'party'],
                'fun'
            );

        $handler = new TagHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('POST', 'https://example.com/tag')
            ->withParsedBody([
                'userId' => 'alice',
                'objectId' => 'party',
                'tags' => 'fun',
                'typeId' => 'event',
            ]);

        $response = $handler->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMissingParamsReturns400(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->never())->method('tag');

        $handler = new TagHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('POST', 'https://example.com/tag')
            ->withParsedBody(['userId' => 'alice']);

        $response = $handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testEmptyBodyReturns400(): void
    {
        $tagger = $this->createStub(Content_Tagger::class);

        $handler = new TagHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('POST', 'https://example.com/tag');

        $response = $handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }
}
