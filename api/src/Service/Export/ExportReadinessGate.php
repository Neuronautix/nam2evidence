<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ClaimNode;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\Project;
use App\Service\NamCore\ProjectGraphBuilder;
use App\Service\NamCore\SemanticValidator;

/**
 * Aggregated review gate for NAM-CORE exports.
 *
 * Draft artifacts (JSON, Markdown, validation report) are always allowed.
 * Formal package artifacts (RO-Crate "complete", eCTD mapping package, final
 * dossier) are blocked while any of the following hold:
 *   - a claim is still human_review_required
 *   - mandatory Context of Use fields are missing
 *   - a decision-informing claim lacks validation evidence
 *   - a mandatory ontology mapping is unresolved
 *   - semantic validation has blocking errors
 *   - endpoint data has unresolved structural errors
 *   - processed endpoint data is missing provenance
 *
 * The gate never asserts regulatory adequacy — only that the standardization
 * package is internally complete and human-reviewed enough to package.
 */
final class ExportReadinessGate
{
    /** Draft formats always permitted regardless of gate state. */
    public const DRAFT_FORMATS = ['json', 'markdown', 'md', 'validation-report'];

    /** Formal formats blocked while the gate is closed. */
    public const FORMAL_FORMATS = ['ro-crate', 'ectd-package', 'dossier'];

    public function __construct(
        private readonly SemanticValidator $validator,
        private readonly ProjectGraphBuilder $graph,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(Project $project): array
    {
        $data = $this->graph->collect($project);
        $validation = $this->validator->validate($project);

        $blockers = [];

        // 1. Semantic validation blocking issues (COU fields, endpoint structure,
        //    provenance gaps, unsupported decision-informing claims, unresolved
        //    mandatory ontology mappings, human-review-required claims).
        foreach ($validation['issues'] as $issue) {
            if ($issue['blocking'] === true) {
                $blockers[] = [
                    'category' => 'semantic_validation',
                    'rule'     => $issue['rule'],
                    'entity'   => $issue['entity'],
                    'message'  => $issue['message'],
                    'fix'      => $issue['recommended_fix'],
                ];
            }
        }

        // 2. Legacy claim review gate (pending / human_review_required) — belt and braces.
        /** @var ClaimNode[] $claims */
        $claims = $data['claims'];
        foreach ($claims as $claim) {
            if (in_array($claim->getReviewStatus(), ['human_review_required', 'pending'], true)) {
                $blockers[] = [
                    'category' => 'human_review',
                    'rule'     => 'ClaimReviewGate',
                    'entity'   => 'Claim:' . $claim->getClaimId(),
                    'message'  => sprintf('Claim %s is %s.', $claim->getClaimId(), $claim->getReviewStatus()),
                    'fix'      => 'Complete human review of the claim before formal export.',
                ];
            }
        }

        // 3. Mandatory ontology mappings unresolved (explicit surfacing).
        /** @var OntologyMapping[] $mappings */
        $mappings = $data['ontologyMappings'];
        $mandatoryUnresolved = count(array_filter(
            $mappings,
            static fn(OntologyMapping $m) => $m->isMandatory() && $m->getMappingStatus() !== OntologyMapping::STATUS_APPROVED,
        ));

        $blocked = count($blockers) > 0;

        return [
            'blocked'                => $blocked,
            'blocker_count'          => count($blockers),
            'blockers'               => $blockers,
            'mandatory_unmapped'     => $mandatoryUnresolved,
            'allowed_draft_formats'  => self::DRAFT_FORMATS,
            'blocked_formal_formats' => $blocked ? self::FORMAL_FORMATS : [],
            'export_status'          => $blocked ? 'draft' : 'internally_reviewed',
        ];
    }

    public function isFormalFormat(string $format): bool
    {
        return in_array(strtolower($format), self::FORMAL_FORMATS, true);
    }
}
