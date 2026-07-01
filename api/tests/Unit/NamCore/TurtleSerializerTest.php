<?php

declare(strict_types=1);

namespace App\Tests\Unit\NamCore;

use App\Service\NamCore\RoCrateBuilder;
use App\Service\NamCore\TurtleSerializer;
use PHPUnit\Framework\TestCase;

final class TurtleSerializerTest extends TestCase
{
    public function testSerializesGraphNodesWithPrefixesAndTriples(): void
    {
        $doc = [
            '@graph' => [
                [
                    '@id'   => 'https://w3id.org/nam-core/project/p1/measurement/m1',
                    '@type' => 'EndpointMeasurement',
                    'label' => 'ATP viability',
                    'value' => 98.2,
                    'unit'  => 'http://purl.obolibrary.org/obo/UO_0000187',
                    'wasDerivedFrom' => 'https://w3id.org/nam-core/project/p1/rawfile/r1',
                ],
            ],
        ];

        $ttl = (new TurtleSerializer())->serialize($doc);

        self::assertStringContainsString('@prefix nam: <https://w3id.org/nam-core/0.1/> .', $ttl);
        self::assertStringContainsString('<https://w3id.org/nam-core/project/p1/measurement/m1>', $ttl);
        self::assertStringContainsString('a <https://w3id.org/nam-core/0.1/EndpointMeasurement>', $ttl);
        self::assertStringContainsString('<http://purl.org/dc/terms/title> "ATP viability"', $ttl);
        self::assertStringContainsString('<http://www.w3.org/ns/prov#wasDerivedFrom> <https://w3id.org/nam-core/project/p1/rawfile/r1>', $ttl);
        // numeric literal, not quoted
        self::assertStringContainsString('98.2', $ttl);
    }

    public function testRoCrateMetadataReferencesAllParts(): void
    {
        $parts = [
            ['path' => 'nam-core.json', 'type' => 'File', 'description' => 'graph'],
            ['path' => 'metadata.jsonld', 'type' => 'File', 'description' => 'jsonld'],
        ];
        $meta = (new RoCrateBuilder())->metadata('Demo', 'A demo package', $parts);

        self::assertSame('https://w3id.org/ro/crate/1.1/context', $meta['@context']);
        $root = array_values(array_filter($meta['@graph'], static fn($n) => ($n['@id'] ?? null) === './'))[0];
        $ids = array_map(static fn($p) => $p['@id'], $root['hasPart']);
        self::assertContains('nam-core.json', $ids);
        self::assertContains('metadata.jsonld', $ids);
    }
}
