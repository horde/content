<?php

declare(strict_types=1);

namespace Horde\Content\Test\unit\View;

use Horde\Content\View\TagListView;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TagListViewTest extends TestCase
{
    public function testRenderSearchResultsStructure(): void
    {
        $view = new TagListView();
        $html = $view->renderSearchResults([1 => 'work', 2 => 'play']);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('</ul>', $html);
        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('work', $html);
        $this->assertStringContainsString('play', $html);
    }

    public function testRenderSearchResultsEscapesHtml(): void
    {
        $view = new TagListView();
        $html = $view->renderSearchResults([1 => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderSearchResultsEmpty(): void
    {
        $view = new TagListView();
        $html = $view->renderSearchResults([]);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringNotContainsString('<li', $html);
    }

    public function testRenderRecentTagsIncludesCreated(): void
    {
        $view = new TagListView();
        $html = $view->renderRecentTags([
            ['tag_id' => 1, 'tag_name' => 'work', 'created' => '2009-01-01 00:00:00'],
        ]);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('work', $html);
        $this->assertStringContainsString('data-created', $html);
        $this->assertStringContainsString('2009-01-01 00:00:00', $html);
    }

    public function testRenderRecentTagsEscapesHtml(): void
    {
        $view = new TagListView();
        $html = $view->renderRecentTags([
            ['tag_id' => 1, 'tag_name' => 'a&b', 'created' => '2009-01-01'],
        ]);

        $this->assertStringContainsString('a&amp;b', $html);
    }

    public function testRenderSearchResultsContainsValueAttribute(): void
    {
        $view = new TagListView();
        $html = $view->renderSearchResults([42 => 'test']);

        $this->assertStringContainsString('value="42"', $html);
    }
}
