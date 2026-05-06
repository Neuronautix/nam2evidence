<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClaimEdge;
use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NAMStudy;
use App\Entity\Project;
use App\Service\Validation\CurieValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Three-tier validation report for a project:
 *   - structural   : COU has all 13 required fields populated
 *   - ontology     : every CURIE-shaped value uses a known prefix and matches the regex
 *   - business     : 8-domain evidence coverage + every approved claim is linked
 *
 * Returns a single JSON document `{structural, ontology, business, passed}` so the
 * frontend can render the validation panel in one round-trip.
 */
#[Route('/api/projects/{id}/validate', name: 'api_project_validate')]
class ValidationController extends AbstractController
{
    /** Required fields on a Context-of-Use card per the NAMO brief (13 total). */
    private const COU_REQUIRED_FIELDS = [
        'couId',
        'namType',
        'regulatoryQuestion',
        'drugDevelopmentStage',
        'intendedUse',
        'decisionSupported',
        'biologicalDomain',
        'endpointClass',
        'populationRelevance',
        'limitations',
        'acceptanceCriteria',
        'regulatoryConfidenceLevel',
        'version',
    ];

    private const EVIDENCE_DOMAINS = [
        'analytical_validity',
        'technical_reproducibility',
        'biological_relevance',
        'reference_compound_performance',
        'exposure_relevance',
        'data_integrity',
        'limitation_analysis',
        'regulatory_alignment',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', methods: ['POST'])]
    public function validate(string $id): JsonResponse
    {
        $project = $this->em->find(Project::class, Ulid::fromString($id));
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $cous     = $this->em->getRepository(ContextOfUseCard::class)->findBy(['project' => $project]);
        $studies  = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project]);
        $claims   = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);

        $evidence = [];
        foreach ($studies as $study) {
            foreach ($study->getEvidenceItems() as $item) {
                $evidence[] = $item;
            }
        }

        $structural = $this->checkStructural($cous);
        $ontology   = $this->checkOntology($studies, $evidence);
        $business   = $this->checkBusiness($evidence, $claims);

        $hasError = static function (array $issues): bool {
            foreach ($issues as $i) {
                if (($i['severity'] ?? 'error') === 'error') {
                    return true;
                }
            }
            return false;
        };

        $passed = !$hasError($structural) && !$hasError($ontology) && !$hasError($business);

        return $this->json([
            'structural' => $structural,
            'ontology'   => $ontology,
            'business'   => $business,
            'passed'     => $passed,
        ]);
    }

    /**
     * @param ContextOfUseCard[] $cous
     * @return array<int, array{field?:string,message:string,severity:string,cou?:string}>
     */
    private function checkStructural(array $cous): array
    {
        $issues = [];

        if (count($cous) === 0) {
            $issues[] = [
                'message'  => 'Project has no Context-of-Use card.',
                'severity' => 'error',
            ];
            return $issues;
        }

        foreach ($cous as $cou) {
            $couRef = $cou->getCouId() !== '' ? $cou->getCouId() : (string) $cou->getId();

            // Use Symfony validator for declared constraints (NotBlank, Choice, …)
            foreach ($this->validator->validate($cou) as $violation) {
                $issues[] = [
                    'cou'      => $couRef,
                    'field'    => $violation->getPropertyPath(),
                    'message'  => (string) $violation->getMessage(),
                    'severity' => 'error',
                ];
            }

            // Hand-rolled completeness check on the 13 required fields
            foreach (self::COU_REQUIRED_FIELDS as $field) {
                $value = $this->readField($cou, $field);
                if ($this->isEmpty($value)) {
                    $issues[] = [
                        'cou'      => $couRef,
                        'field'    => $field,
                        'message'  => sprintf('COU field "%s" is required but empty.', $field),
                        'severity' => 'error',
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @param NAMStudy[]     $studies
     * @param EvidenceItem[] $evidence
     * @return array<int, array{value:string,prefix?:string,message:string,severity:string,location:string}>
     */
    private function checkOntology(array $studies, array $evidence): array
    {
        $issues = [];

        foreach ($studies as $study) {
            $this->scanForCuries(
                $study->getModelSystem(),
                'study:' . $study->getStudyId() . '.model_system',
                $issues,
            );
            $this->scanForCuries(
                $study->getAssayMetadata(),
                'study:' . $study->getStudyId() . '.assay_metadata',
                $issues,
            );
        }

        foreach ($evidence as $item) {
            if ($item->getSupportingData() !== null && $item->getSupportingData() !== '') {
                $this->scanForCuries(
                    $item->getSupportingData(),
                    'evidence:' . $item->getEvidenceId() . '.supporting_data',
                    $issues,
                );
            }
        }

        return $issues;
    }

    private function scanForCuries(mixed $value, string $location, array &$issues): void
    {
        if (is_string($value)) {
            if (CurieValidator::looksLikeCurie($value) && !CurieValidator::isValid($value)) {
                $issues[] = [
                    'value'    => $value,
                    'prefix'   => CurieValidator::prefixOf($value),
                    'message'  => sprintf(
                        'Value "%s" looks like a CURIE but does not match a known/valid prefix.',
                        $value,
                    ),
                    'severity' => 'error',
                    'location' => $location,
                ];
            }
            return;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $this->scanForCuries($v, $location . '.' . $k, $issues);
            }
        }
    }

    /**
     * @param EvidenceItem[] $evidence
     * @param ClaimNode[]    $claims
     */
    private function checkBusiness(array $evidence, array $claims): array
    {
        $issues = [];

        // 8-domain coverage
        $byDomain = [];
        foreach (self::EVIDENCE_DOMAINS as $d) {
            $byDomain[$d] = ['met' => 0, 'partial' => 0, 'not_met' => 0, 'not_applicable' => 0];
        }
        foreach ($evidence as $item) {
            $d = $item->getDomain();
            $s = $item->getStatus();
            if (isset($byDomain[$d][$s])) {
                ++$byDomain[$d][$s];
            }
        }
        foreach ($byDomain as $d => $counts) {
            if (($counts['met'] + $counts['partial']) === 0) {
                $issues[] = [
                    'domain'   => $d,
                    'message'  => sprintf('Evidence domain "%s" has no items in status met/partial.', $d),
                    'severity' => 'error',
                ];
            }
            if ($counts['not_met'] > 0) {
                $issues[] = [
                    'domain'   => $d,
                    'message'  => sprintf(
                        'Evidence domain "%s" has %d item(s) in status not_met.',
                        $d, $counts['not_met'],
                    ),
                    'severity' => 'warning',
                ];
            }
        }

        // Approved claims must have at least one supporting edge
        $edgeRepo = $this->em->getRepository(ClaimEdge::class);
        foreach ($claims as $claim) {
            if ($claim->getReviewStatus() !== 'approved') {
                continue;
            }
            $edges = $edgeRepo->findBy(['fromClaim' => $claim]);
            $hasSupport = false;
            foreach ($edges as $edge) {
                if (in_array($edge->getRelationship(), ['supports', 'derived_from', 'requires'], true)) {
                    $hasSupport = true;
                    break;
                }
            }
            // Fallback: explicit supportingEvidence list counts as a link
            if (!$hasSupport && count($claim->getSupportingEvidence()) === 0) {
                $issues[] = [
                    'claim'    => $claim->getClaimId(),
                    'message'  => sprintf(
                        'Approved claim "%s" is not linked to any supporting evidence.',
                        $claim->getClaimId(),
                    ),
                    'severity' => 'error',
                ];
            }
        }

        return $issues;
    }

    private function readField(ContextOfUseCard $cou, string $field): mixed
    {
        $getter = 'get' . ucfirst($field);
        if (method_exists($cou, $getter)) {
            return $cou->{$getter}();
        }
        return null;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        return false;
    }
}
