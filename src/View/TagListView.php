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

namespace Horde\Content\View;

use Horde_View;

/**
 * Renders tag lists as HTML using Horde_View.
 */
class TagListView
{
    private Horde_View $view;

    public function __construct()
    {
        $this->view = new Horde_View();
        $this->view->addHelper('Tag');
    }

    /**
     * Render a tag id => name mapping as an HTML list.
     *
     * @param array $tags  Hash of tag_id => tag_name.
     *
     * @return string  HTML string.
     */
    public function renderSearchResults(array $tags): string
    {
        $items = '';
        foreach ($tags as $tagId => $tagName) {
            $items .= $this->view->contentTag(
                'li',
                $this->view->escape($tagName),
                ['value' => (string) $tagId]
            ) . "\n";
        }

        return $this->view->contentTag('ul', "\n" . $items);
    }

    /**
     * Render recent tags (array of row hashes) as an HTML list.
     *
     * @param array $tags  Array of hashes with tag_id, tag_name, created.
     *
     * @return string  HTML string.
     */
    public function renderRecentTags(array $tags): string
    {
        $items = '';
        foreach ($tags as $tag) {
            $items .= $this->view->contentTag(
                'li',
                $this->view->escape($tag['tag_name']),
                [
                    'value' => (string) $tag['tag_id'],
                    'data-created' => $this->view->escape($tag['created']),
                ]
            ) . "\n";
        }

        return $this->view->contentTag('ul', "\n" . $items);
    }
}
