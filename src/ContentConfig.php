<?php

declare(strict_types=1);
/**
 * Content configuration class
 *
 * Provides access to the Content configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(ContentConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Content;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: ContentConfigFactory::class, method: 'create')]
class ContentConfig extends State {}
