<?php

declare(strict_types=1);

namespace App\Service\NamCore;

/**
 * Builds the ro-crate-metadata.json descriptor for a NAM-CORE evidence package.
 *
 * The crate root references every packaged component (NAM-CORE JSON, JSON-LD,
 * endpoint measurements CSV, validation report, readiness report, Markdown
 * dossier, eCTD mapping TXT, provenance metadata). Conforms to the RO-Crate 1.1
 * profile shape. This is a POC packaging descriptor, not a certified deposit.
 */
final class RoCrateBuilder
{
    /**
     * @param array<int,array{path:string, type:string, description:string, encodingFormat?:string}> $parts
     * @return array<string,mixed>
     */
    public function metadata(string $projectName, string $description, array $parts): array
    {
        $hasPart = [];
        $partEntities = [];
        foreach ($parts as $p) {
            $hasPart[] = ['@id' => $p['path']];
            $partEntities[] = array_filter([
                '@id'            => $p['path'],
                '@type'          => $p['type'],
                'name'           => $p['path'],
                'description'    => $p['description'],
                'encodingFormat' => $p['encodingFormat'] ?? null,
            ], static fn($v) => $v !== null);
        }

        $graph = [
            [
                '@type'      => 'CreativeWork',
                '@id'        => 'ro-crate-metadata.json',
                'conformsTo' => ['@id' => 'https://w3id.org/ro/crate/1.1'],
                'about'      => ['@id' => './'],
            ],
            [
                '@id'         => './',
                '@type'       => 'Dataset',
                'name'        => $projectName . ' — NAM-CORE evidence package',
                'description' => $description,
                'hasPart'     => $hasPart,
                'creator'     => ['@id' => '#nam-core-toolkit'],
                'license'     => 'https://spdx.org/licenses/CC-BY-4.0.html',
                'conformsTo'  => 'https://w3id.org/nam-core/0.1/',
                'disclaimer'  => 'POC standardization package — not an official regulatory submission or SEND/CDISC deliverable.',
            ],
            [
                '@id'   => '#nam-core-toolkit',
                '@type' => 'SoftwareApplication',
                'name'  => 'NAMO-to-IND Mapper (NAM-CORE standardization toolkit)',
                'softwareVersion' => ProjectGraphBuilder::SCHEMA_VERSION,
            ],
            ...$partEntities,
        ];

        return [
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph'   => $graph,
        ];
    }
}
