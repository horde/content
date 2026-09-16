<?php

declare(strict_types=1);
/**
 * Content configuration class factory
 *
 * Creates instances of the ContentConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(ContentConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Content;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class ContentConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): ContentConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('content');
        return new ContentConfig($state->toArray());
    }
}
