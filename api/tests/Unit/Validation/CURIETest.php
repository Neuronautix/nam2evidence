<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validation;

use App\Service\Validation\CurieValidator;
use PHPUnit\Framework\TestCase;

final class CURIETest extends TestCase
{
    /**
     * @dataProvider validCuries
     */
    public function testValidCuriesAreAccepted(string $curie): void
    {
        self::assertTrue(
            CurieValidator::looksLikeCurie($curie),
            sprintf('"%s" should match the CURIE shape regex', $curie),
        );
        self::assertTrue(
            CurieValidator::isValid($curie),
            sprintf('"%s" should be a valid known-prefix CURIE', $curie),
        );
    }

    /**
     * @dataProvider invalidCuries
     */
    public function testInvalidCuriesAreRejected(string $value, string $why): void
    {
        self::assertFalse(
            CurieValidator::isValid($value),
            sprintf('"%s" should be rejected: %s', $value, $why),
        );
    }

    public function testUnknownPrefixLooksLikeCurieButIsNotValid(): void
    {
        // "FOO:0000182" matches the shape but isn't in the NAMO-recognised prefix set.
        self::assertTrue(CurieValidator::looksLikeCurie('FOO:0000182'));
        self::assertFalse(CurieValidator::isValid('FOO:0000182'));
        self::assertSame('FOO', CurieValidator::prefixOf('FOO:0000182'));
    }

    public static function validCuries(): array
    {
        return [
            'CL hepatocyte'  => ['CL:0000182'],
            'UBERON liver'   => ['UBERON:0002107'],
            'CHEBI water'    => ['CHEBI:15377'],
            'MONDO disease'  => ['MONDO:0005148'],
            'NCBITaxon human'=> ['NCBITaxon:9606'],
            'OBI assay'      => ['OBI:0000070'],
            'ECO evidence'   => ['ECO:0000006'],
            'DOID disease'   => ['DOID:1612'],
        ];
    }

    public static function invalidCuries(): array
    {
        return [
            'no colon'              => ['CL0000182',          'missing the prefix:local separator'],
            'leading digit prefix'  => ['1CL:0000182',         'prefix must start with a letter'],
            'empty local id'        => ['CL:',                 'local id cannot be empty'],
            'whitespace'            => ['CL: 0000182',         'no whitespace allowed'],
            'unknown prefix UBERN'  => ['UBERN:0001234',       'misspelled prefix not in known set'],
            'pure URL'              => ['http://example/x',    'CURIE shape but unknown prefix http'],
            'plain text'            => ['hepatocyte',          'no colon at all'],
        ];
    }
}
