<?php

declare(strict_types=1);

namespace App\Service\NamCore;

/**
 * Best-effort unit normalization for the POC. Maps common free-text unit
 * spellings to a canonical symbol plus a UCUM/UO ontology IRI. Unknown units
 * are returned unchanged with a null IRI so validation can flag them for
 * explicit human justification — the toolkit never silently invents a unit.
 */
final class UnitNormalizer
{
    /** @var array<string, array{symbol:string, iri:string, curie:string}> */
    private const MAP = [
        'um'          => ['symbol' => 'µM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000064', 'curie' => 'UO:0000064'],
        'µm'          => ['symbol' => 'µM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000064', 'curie' => 'UO:0000064'],
        'umol/l'      => ['symbol' => 'µM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000064', 'curie' => 'UO:0000064'],
        'micromolar'  => ['symbol' => 'µM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000064', 'curie' => 'UO:0000064'],
        'nm'          => ['symbol' => 'nM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000065', 'curie' => 'UO:0000065'],
        'nanomolar'   => ['symbol' => 'nM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000065', 'curie' => 'UO:0000065'],
        'mm'          => ['symbol' => 'mM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000063', 'curie' => 'UO:0000063'],
        'millimolar'  => ['symbol' => 'mM', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000063', 'curie' => 'UO:0000063'],
        'h'           => ['symbol' => 'h', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000032', 'curie' => 'UO:0000032'],
        'hr'          => ['symbol' => 'h', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000032', 'curie' => 'UO:0000032'],
        'hour'        => ['symbol' => 'h', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000032', 'curie' => 'UO:0000032'],
        'hours'       => ['symbol' => 'h', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000032', 'curie' => 'UO:0000032'],
        'min'         => ['symbol' => 'min', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000031', 'curie' => 'UO:0000031'],
        'minute'      => ['symbol' => 'min', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000031', 'curie' => 'UO:0000031'],
        'day'         => ['symbol' => 'd', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000033', 'curie' => 'UO:0000033'],
        'days'        => ['symbol' => 'd', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000033', 'curie' => 'UO:0000033'],
        '%'           => ['symbol' => '%', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000187', 'curie' => 'UO:0000187'],
        'percent'     => ['symbol' => '%', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000187', 'curie' => 'UO:0000187'],
        'pct'         => ['symbol' => '%', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000187', 'curie' => 'UO:0000187'],
        'fold'        => ['symbol' => 'fold', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000190', 'curie' => 'UO:0000190'],
        'ratio'       => ['symbol' => 'ratio', 'iri' => 'http://purl.obolibrary.org/obo/UO_0000190', 'curie' => 'UO:0000190'],
    ];

    /**
     * @return array{normalized:?string, iri:?string, curie:?string, changed:bool, known:bool}
     */
    public function normalize(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['normalized' => null, 'iri' => null, 'curie' => null, 'changed' => false, 'known' => false];
        }
        $key = strtolower(trim($raw));
        if (isset(self::MAP[$key])) {
            $entry = self::MAP[$key];
            return [
                'normalized' => $entry['symbol'],
                'iri'        => $entry['iri'],
                'curie'      => $entry['curie'],
                'changed'    => $entry['symbol'] !== trim($raw),
                'known'      => true,
            ];
        }

        return ['normalized' => trim($raw), 'iri' => null, 'curie' => null, 'changed' => false, 'known' => false];
    }
}
