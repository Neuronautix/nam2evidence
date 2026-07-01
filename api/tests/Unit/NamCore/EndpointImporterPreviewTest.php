<?php

declare(strict_types=1);

namespace App\Tests\Unit\NamCore;

use App\Service\NamCore\EndpointMeasurementImporter;
use App\Service\NamCore\UnitNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * preview() is pure (no persistence), so it can be unit-tested with a mocked EM.
 */
final class EndpointImporterPreviewTest extends TestCase
{
    private EndpointMeasurementImporter $importer;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->importer = new EndpointMeasurementImporter($em, new UnitNormalizer());
    }

    public function testPreviewInfersColumnsAndSuggestsMapping(): void
    {
        $csv = "endpoint,value,unit,timepoint\natp_viability,98.2,percent,24\nldh_release,12.1,percent,24\n";
        $preview = $this->importer->preview($csv);

        self::assertSame(['endpoint', 'value', 'unit', 'timepoint'], $preview['columns']);
        self::assertSame(2, $preview['row_count']);
        self::assertSame('endpoint_id', $preview['suggested_mapping']['endpoint']);
        self::assertSame('value', $preview['suggested_mapping']['value']);
        self::assertSame('unit', $preview['suggested_mapping']['unit']);
        self::assertSame('timepoint_value', $preview['suggested_mapping']['timepoint']);
        self::assertCount(2, $preview['sample_rows']);
        self::assertSame('atp_viability', $preview['sample_rows'][0]['endpoint']);
    }

    public function testPreviewHandlesBomAndBlankLines(): void
    {
        $csv = "\xEF\xBB\xBFendpoint,value\n\natp,1\n";
        $preview = $this->importer->preview($csv);
        self::assertSame(['endpoint', 'value'], $preview['columns']);
        self::assertSame(1, $preview['row_count']);
    }

    public function testTargetFieldsExposeCanonicalSchema(): void
    {
        self::assertContains('endpoint_id', EndpointMeasurementImporter::TARGET_FIELDS);
        self::assertContains('unit_ontology_iri', EndpointMeasurementImporter::TARGET_FIELDS);
        self::assertContains('exclusion_reason', EndpointMeasurementImporter::TARGET_FIELDS);
    }
}
