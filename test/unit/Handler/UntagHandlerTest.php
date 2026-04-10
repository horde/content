<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Handler;

use Content_Tagger;
use Horde\Content\Handler\UntagHandler;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UntagHandlerTest extends TestCase
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

    public function testSuccessfulUntag(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('untag')
            ->with(
                'alice',
                ['type' => 'event', 'object' => 'party'],
                'fun'
            );

        $handler = new UntagHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('POST', 'https://example.com/untag')
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
        $tagger->expects($this->never())->method('untag');

        $handler = new UntagHandler($tagger, $this->responseFactory, $this->streamFactory);
        $request = $this->requestFactory
            ->createServerRequest('POST', 'https://example.com/untag')
            ->withParsedBody(['userId' => 'alice', 'objectId' => 'party']);

        $response = $handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }
}
