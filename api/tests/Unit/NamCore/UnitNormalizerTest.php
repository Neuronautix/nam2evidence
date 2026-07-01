<?php

declare(strict_types=1);

namespace App\Tests\Unit\NamCore;

use App\Service\NamCore\UnitNormalizer;
use PHPUnit\Framework\TestCase;

final class UnitNormalizerTest extends TestCase
{
    private UnitNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UnitNormalizer();
    }

    public function testKnownUnitIsNormalizedWithIri(): void
    {
        $r = $this->normalizer->normalize('uM');
        self::assertSame('µM', $r['normalized']);
        self::assertTrue($r['known']);
        self::assertTrue($r['changed']);
        self::assertStringContainsString('UO_0000064', (string) $r['iri']);
    }

    public function testUnknownUnitIsPreservedWithoutIri(): void
    {
        $r = $this->normalizer->normalize('widgets');
        self::assertSame('widgets', $r['normalized']);
        self::assertFalse($r['known']);
        self::assertNull($r['iri']);
    }

    public function testEmptyUnitYieldsNull(): void
    {
        $r = $this->normalizer->normalize('');
        self::assertNull($r['normalized']);
        self::assertFalse($r['known']);
    }

    public function testPercentAndHourVariants(): void
    {
        self::assertSame('%', $this->normalizer->normalize('percent')['normalized']);
        self::assertSame('h', $this->normalizer->normalize('hours')['normalized']);
    }
}
