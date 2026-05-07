<?php

declare(strict_types=1);

namespace App\Service\Validation;

/**
 * Validates Compact URIs (CURIEs) of the form `prefix:local_id` against the
 * subset of biomedical ontologies referenced by the NAMO brief.
 *
 * Recognised prefixes:
 *   UBERON  – anatomical structures
 *   CL      – cell types (Cell Ontology)
 *   CHEBI   – chemical entities of biological interest
 *   MONDO   – disease ontology (Monarch)
 *   NCBITaxon – organisms
 *   OBI     – ontology for biomedical investigations
 *   ECO     – evidence and conclusion ontology
 *   DOID    – Human Disease Ontology
 */
final class CurieValidator
{
    /**
     * Generic shape regex (ASCII-only). Matches `prefix:local_id` where:
     *   prefix    starts with a letter, then [A-Za-z0-9_]*
     *   local_id  contains [A-Za-z0-9_:.-]+
     */
    public const CURIE_REGEX = '/^[A-Za-z][A-Za-z0-9_]*:[A-Za-z0-9_:.\-]+$/';

    public const KNOWN_PREFIXES = [
        'UBERON', 'CL', 'CHEBI', 'MONDO', 'NCBITaxon', 'OBI', 'ECO', 'DOID',
    ];

    /** True if the value matches the generic CURIE shape (ignoring prefix vocabulary). */
    public static function looksLikeCurie(string $value): bool
    {
        return (bool) preg_match(self::CURIE_REGEX, $value);
    }

    /**
     * True if the value is a syntactically valid CURIE AND the prefix is one of the
     * NAMO-recognised biomedical ontologies. Returns false for unknown prefixes so
     * we can flag potential misspellings (e.g. "UBERN:0001" instead of "UBERON:…").
     */
    public static function isValid(string $value): bool
    {
        if (!self::looksLikeCurie($value)) {
            return false;
        }
        $prefix = self::prefixOf($value);
        return $prefix !== null && in_array($prefix, self::KNOWN_PREFIXES, true);
    }

    public static function prefixOf(string $value): ?string
    {
        $colon = strpos($value, ':');
        if ($colon === false) {
            return null;
        }
        return substr($value, 0, $colon);
    }
}
