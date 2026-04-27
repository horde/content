<?php

declare(strict_types=1);

namespace Horde\Content;

use Horde\Content\Api\TaggerAdminProvider;
use Horde\Content\Api\TaggerProvider;
use Horde\Core\Api\ApiInterfaceListProvider;

class Api implements ApiInterfaceListProvider
{
    public function getApiInterfaceList(): array
    {
        return [
            'tagger' => TaggerProvider::class,
            'taggerAdmin' => TaggerAdminProvider::class,
        ];
    }
}
