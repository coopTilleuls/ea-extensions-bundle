<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit;

use CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle;
use PHPUnit\Framework\TestCase;

final class EAExtensionsBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EAExtensionsBundle::class, new EAExtensionsBundle());
    }
}
