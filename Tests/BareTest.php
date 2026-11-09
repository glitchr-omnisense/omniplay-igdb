<?php

namespace Omniplay\Igdb\Tests;

use Omniplay\Igdb\IgdbProvider;
use Omniplay\Testing\BareTestCase;

/** omniplay/igdb's classes load with omnisense/omniplay and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return IgdbProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }
}
