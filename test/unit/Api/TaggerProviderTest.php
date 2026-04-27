<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Api;

use Content_Tagger;
use Horde\Content\Api\TaggerProvider;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\MethodDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaggerProvider::class)]
class TaggerProviderTest extends TestCase
{
    private function makeProvider(?Content_Tagger $tagger = null): TaggerProvider
    {
        return new TaggerProvider($tagger ?? $this->createMock(Content_Tagger::class));
    }

    public function testHasMethodForKnownMethods(): void
    {
        $provider = $this->makeProvider();
        $known = [
            'tag', 'untag', 'removeTagFromObject', 'getTags', 'getTagsByObjects',
            'getTagCloud', 'getRecentTags', 'getObjects', 'getSimilarObjects',
            'getRecentObjects', 'ensureTags', 'getTagIds', 'splitTags', 'browseTags',
        ];

        foreach ($known as $method) {
            $this->assertTrue($provider->hasMethod($method), "Expected hasMethod('$method') to be true");
        }
    }

    public function testHasMethodReturnsFalseForUnknown(): void
    {
        $provider = $this->makeProvider();

        $this->assertFalse($provider->hasMethod('nonexistent'));
    }

    public function testInvokeTagDelegatesToTagger(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('tag')
            ->with('user1', ['type' => 'event', 'object' => 'evt1'], ['work']);

        $provider = new TaggerProvider($tagger);
        $provider->invoke('tag', ['user1', ['type' => 'event', 'object' => 'evt1'], ['work']]);
    }

    public function testInvokeGetTagsDelegatesToTagger(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with(['q' => 'per', 'limit' => 10])
            ->willReturn([1 => 'personal', 2 => 'performance']);

        $provider = new TaggerProvider($tagger);
        $result = $provider->invoke('getTags', [['q' => 'per', 'limit' => 10]]);

        $this->assertSame([1 => 'personal', 2 => 'performance'], $result->value);
    }

    public function testInvokeGetTagCloudDelegatesToTagger(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTagCloud')
            ->with(['limit' => 5])
            ->willReturn([['tag_id' => 1, 'tag_name' => 'php', 'count' => 42]]);

        $provider = new TaggerProvider($tagger);
        $result = $provider->invoke('getTagCloud', [['limit' => 5]]);

        $this->assertSame([['tag_id' => 1, 'tag_name' => 'php', 'count' => 42]], $result->value);
    }

    public function testInvokeSplitTagsDelegatesToTagger(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('splitTags')
            ->with('php, horde, "web dev"')
            ->willReturn(['php', 'horde', 'web dev']);

        $provider = new TaggerProvider($tagger);
        $result = $provider->invoke('splitTags', ['php, horde, "web dev"']);

        $this->assertSame(['php', 'horde', 'web dev'], $result->value);
    }

    public function testInvokeUnknownMethodThrows(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(\RuntimeException::class);
        $provider->invoke('nonexistent', []);
    }

    public function testListMethodsReturnsAllDescriptors(): void
    {
        $provider = $this->makeProvider();
        $methods = $provider->listMethods();

        $this->assertCount(14, $methods);
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);
        $this->assertContains('tag', $names);
        $this->assertContains('getTags', $names);
        $this->assertContains('browseTags', $names);
    }

    public function testGetMethodDescriptorReturnsMetadata(): void
    {
        $provider = $this->makeProvider();
        $desc = $provider->getMethodDescriptor('tag');

        $this->assertNotNull($desc);
        $this->assertSame('tag', $desc->name);
        $this->assertSame('Add tags to an object', $desc->description);
        $this->assertSame('void', $desc->returnType);
        $this->assertCount(4, $desc->parameters);
        $this->assertSame('userId', $desc->parameters[0]['name']);
    }

    public function testGetMethodDescriptorReturnsNullForUnknown(): void
    {
        $provider = $this->makeProvider();

        $this->assertNull($provider->getMethodDescriptor('nope'));
    }

    public function testContextIsIgnored(): void
    {
        $provider = $this->makeProvider();
        $context = new ApiCallContext(['permissions' => ['admin']]);

        $this->assertTrue($provider->hasMethod('tag', $context));
        $this->assertTrue($provider->hasMethod('tag', null));
        $this->assertTrue($provider->hasMethod('tag'));
    }
}
