<?php

declare(strict_types=1);

namespace App\Service\NamCore;

/**
 * Minimal, dependency-free RDF/Turtle serializer for the NAM-CORE JSON-LD graph
 * produced by {@see ProjectGraphBuilder::toJsonLd()}. It maps the compact
 * context keys to full predicate IRIs and emits one block per @graph node.
 *
 * This is a pragmatic POC serializer (not a full JSON-LD 1.1 processor); the
 * pyshacl sidecar consumes the canonical JSON-LD directly for validation.
 */
final class TurtleSerializer
{
    private const PREFIXES = [
        'nam'     => 'https://w3id.org/nam-core/0.1/',
        'prov'    => 'http://www.w3.org/ns/prov#',
        'dcterms' => 'http://purl.org/dc/terms/',
        'xsd'     => 'http://www.w3.org/2001/XMLSchema#',
    ];

    /** compact JSON-LD key => full predicate IRI */
    private const PREDICATES = [
        'label'                  => 'http://purl.org/dc/terms/title',
        'description'            => 'http://purl.org/dc/terms/description',
        'version'                => 'http://purl.org/dc/terms/hasVersion',
        'validationStatus'       => 'https://w3id.org/nam-core/0.1/validationStatus',
        'project'                => 'https://w3id.org/nam-core/0.1/project',
        'contextOfUse'           => 'https://w3id.org/nam-core/0.1/contextOfUse',
        'decisionQuestion'       => 'https://w3id.org/nam-core/0.1/decisionQuestion',
        'intendedUse'            => 'https://w3id.org/nam-core/0.1/intendedUse',
        'biologicalDomain'       => 'https://w3id.org/nam-core/0.1/biologicalDomain',
        'regulatorySupportLevel' => 'https://w3id.org/nam-core/0.1/regulatorySupportLevel',
        'modelSystemType'        => 'https://w3id.org/nam-core/0.1/modelSystemType',
        'species'                => 'https://w3id.org/nam-core/0.1/species',
        'anatomy'                => 'https://w3id.org/nam-core/0.1/anatomy',
        'cellType'               => 'https://w3id.org/nam-core/0.1/cellType',
        'cellSource'             => 'https://w3id.org/nam-core/0.1/cellSource',
        'testArticle'            => 'https://w3id.org/nam-core/0.1/testArticle',
        'endpoint'               => 'https://w3id.org/nam-core/0.1/endpoint',
        'unit'                   => 'https://w3id.org/nam-core/0.1/unit',
        'value'                  => 'https://w3id.org/nam-core/0.1/value',
        'timepointValue'         => 'https://w3id.org/nam-core/0.1/timepointValue',
        'timepointUnit'          => 'https://w3id.org/nam-core/0.1/timepointUnit',
        'reviewStatus'           => 'https://w3id.org/nam-core/0.1/reviewStatus',
        'supportedBy'            => 'https://w3id.org/nam-core/0.1/supportedBy',
        'wasDerivedFrom'         => 'http://www.w3.org/ns/prov#wasDerivedFrom',
        'wasGeneratedBy'         => 'http://www.w3.org/ns/prov#wasGeneratedBy',
        'generatedAtTime'        => 'http://www.w3.org/ns/prov#generatedAtTime',
    ];

    /** @param array<string,mixed> $jsonld a document with an @graph array */
    public function serialize(array $jsonld): string
    {
        $out = [];
        foreach (self::PREFIXES as $p => $iri) {
            $out[] = sprintf('@prefix %s: <%s> .', $p, $iri);
        }
        $out[] = '';

        $graph = $jsonld['@graph'] ?? [];
        if (!is_array($graph)) {
            return implode("\n", $out) . "\n";
        }

        foreach ($graph as $node) {
            if (!is_array($node) || !isset($node['@id'])) {
                continue;
            }
            $out[] = $this->serializeNode($node);
        }

        return implode("\n", $out) . "\n";
    }

    /** @param array<string,mixed> $node */
    private function serializeNode(array $node): string
    {
        $subject = '<' . $node['@id'] . '>';
        $lines = [];

        if (isset($node['@type'])) {
            $lines[] = "\ta " . $this->curieOrIri('nam:' . $node['@type']);
        }

        foreach ($node as $key => $value) {
            if (str_starts_with($key, '@')) {
                continue;
            }
            $predicate = $this->predicate($key);
            foreach ((array) (is_array($value) && array_is_list($value) ? $value : [$value]) as $v) {
                $lines[] = "\t" . $predicate . ' ' . $this->object($v);
            }
        }

        return $subject . "\n" . implode(" ;\n", $lines) . " .\n";
    }

    private function predicate(string $key): string
    {
        if (isset(self::PREDICATES[$key])) {
            return '<' . self::PREDICATES[$key] . '>';
        }
        if (str_contains($key, ':')) {
            // already a CURIE like nam:assayType — expand the local part safely
            [$prefix, $local] = explode(':', $key, 2);
            if (isset(self::PREFIXES[$prefix])) {
                return '<' . self::PREFIXES[$prefix] . $local . '>';
            }
        }
        return '<https://w3id.org/nam-core/0.1/' . $key . '>';
    }

    private function object(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $s = (string) $value;
        // treat http(s) IRIs and known CURIEs as resources
        if (preg_match('#^https?://#', $s)) {
            return '<' . $s . '>';
        }
        if (preg_match('#^[A-Za-z][\w]*:[\w/.\-]+$#', $s) && isset(self::PREFIXES[explode(':', $s, 2)[0]])) {
            return $this->curieOrIri($s);
        }
        return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $s) . '"';
    }

    private function curieOrIri(string $curie): string
    {
        [$prefix, $local] = explode(':', $curie, 2);
        if (isset(self::PREFIXES[$prefix])) {
            return '<' . self::PREFIXES[$prefix] . $local . '>';
        }
        return '<' . $curie . '>';
    }
}
