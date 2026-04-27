<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\Api;

use Content_Tagger;
use Horde\Content\Api\TaggerAdminProvider;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\MethodDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TaggerAdminProvider::class)]
class TaggerAdminProviderTest extends TestCase
{
    private function makeProvider(?Content_Tagger $tagger = null): TaggerAdminProvider
    {
        return new TaggerAdminProvider($tagger ?? $this->createMock(Content_Tagger::class));
    }

    private function adminContext(): ApiCallContext
    {
        return new ApiCallContext(['permissions' => ['admin']]);
    }

    private function userContext(): ApiCallContext
    {
        return new ApiCallContext(['permissions' => ['user']]);
    }

    // --- Visibility gating: no context ---

    public function testHasMethodReturnsFalseWithoutContext(): void
    {
        $provider = $this->makeProvider();

        $this->assertFalse($provider->hasMethod('listTagsByUser'));
        $this->assertFalse($provider->hasMethod('listTaggedObjectsByUser'));
    }

    public function testHasMethodReturnsFalseWithNonAdminContext(): void
    {
        $provider = $this->makeProvider();

        $this->assertFalse($provider->hasMethod('listTagsByUser', $this->userContext()));
    }

    public function testHasMethodReturnsTrueWithAdminContext(): void
    {
        $provider = $this->makeProvider();

        $this->assertTrue($provider->hasMethod('listTagsByUser', $this->adminContext()));
        $this->assertTrue($provider->hasMethod('listTaggedObjectsByUser', $this->adminContext()));
    }

    public function testHasMethodReturnsFalseForUnknownEvenWithAdmin(): void
    {
        $provider = $this->makeProvider();

        $this->assertFalse($provider->hasMethod('nonexistent', $this->adminContext()));
    }

    // --- listMethods visibility ---

    public function testListMethodsEmptyWithoutAdmin(): void
    {
        $provider = $this->makeProvider();

        $this->assertSame([], $provider->listMethods());
        $this->assertSame([], $provider->listMethods($this->userContext()));
    }

    public function testListMethodsReturnsMethodsWithAdmin(): void
    {
        $provider = $this->makeProvider();
        $methods = $provider->listMethods($this->adminContext());

        $this->assertCount(2, $methods);
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);
        $this->assertContains('listTagsByUser', $names);
        $this->assertContains('listTaggedObjectsByUser', $names);
    }

    // --- getMethodDescriptor visibility ---

    public function testGetMethodDescriptorNullWithoutAdmin(): void
    {
        $provider = $this->makeProvider();

        $this->assertNull($provider->getMethodDescriptor('listTagsByUser'));
        $this->assertNull($provider->getMethodDescriptor('listTagsByUser', $this->userContext()));
    }

    public function testGetMethodDescriptorReturnsWithAdmin(): void
    {
        $provider = $this->makeProvider();
        $desc = $provider->getMethodDescriptor('listTagsByUser', $this->adminContext());

        $this->assertNotNull($desc);
        $this->assertSame('listTagsByUser', $desc->name);
    }

    // --- invoke gating ---

    public function testInvokeThrowsWithoutAdmin(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');
        $provider->invoke('listTagsByUser', ['user1']);
    }

    public function testInvokeThrowsWithNonAdminContext(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(RuntimeException::class);
        $provider->invoke('listTagsByUser', ['user1'], $this->userContext());
    }

    // --- Delegation ---

    public function testInvokeListTagsByUserDelegates(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with(['userId' => 'user1'])
            ->willReturn([1 => 'php', 2 => 'horde']);

        $provider = new TaggerAdminProvider($tagger);
        $result = $provider->invoke('listTagsByUser', ['user1'], $this->adminContext());

        $this->assertSame([1 => 'php', 2 => 'horde'], $result->value);
    }

    public function testInvokeListTaggedObjectsByUserDelegates(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with(['userId' => 'user1'])
            ->willReturn([1 => 'php', 2 => 'horde']);
        $tagger->expects($this->once())
            ->method('getObjects')
            ->with(['tagId' => [1, 2], 'userId' => 'user1'])
            ->willReturn([10 => 'obj-a', 20 => 'obj-b']);

        $provider = new TaggerAdminProvider($tagger);
        $result = $provider->invoke('listTaggedObjectsByUser', ['user1'], $this->adminContext());

        $this->assertSame([10 => 'obj-a', 20 => 'obj-b'], $result->value);
    }

    public function testInvokeListTaggedObjectsByUserReturnsEmptyWhenNoTags(): void
    {
        $tagger = $this->createMock(Content_Tagger::class);
        $tagger->expects($this->once())
            ->method('getTags')
            ->with(['userId' => 'user1'])
            ->willReturn([]);
        $tagger->expects($this->never())
            ->method('getObjects');

        $provider = new TaggerAdminProvider($tagger);
        $result = $provider->invoke('listTaggedObjectsByUser', ['user1'], $this->adminContext());

        $this->assertSame([], $result->value);
    }

    // --- Descriptors carry admin permission ---

    public function testDescriptorsHaveAdminPermission(): void
    {
        $provider = $this->makeProvider();
        $methods = $provider->listMethods($this->adminContext());

        foreach ($methods as $desc) {
            $this->assertSame(['admin'], $desc->permissions, "Method {$desc->name} should require admin");
        }
    }

    public function testInvokeUnknownMethodThrowsWithAdmin(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(RuntimeException::class);
        $provider->invoke('nonexistent', [], $this->adminContext());
    }
}
