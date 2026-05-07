<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClaimNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Reviewer-side claim transitions:
 *   POST /api/claims/{id}/approve  → review_status = approved
 *   POST /api/claims/{id}/reject   → review_status = rejected   (optional reason)
 *   POST /api/claims/{id}/reopen   → review_status = human_review_required
 *
 * Each transition stamps reviewedAt/reviewedBy on the ClaimNode for audit.
 */
#[Route('/api/claims/{id}', name: 'api_claim_review_')]
class ClaimReviewController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/approve', name: 'approve', methods: ['POST'])]
    public function approve(string $id, Request $request): JsonResponse
    {
        return $this->transition($id, $request, 'approved');
    }

    #[Route('/reject', name: 'reject', methods: ['POST'])]
    public function reject(string $id, Request $request): JsonResponse
    {
        return $this->transition($id, $request, 'rejected');
    }

    #[Route('/reopen', name: 'reopen', methods: ['POST'])]
    public function reopen(string $id, Request $request): JsonResponse
    {
        return $this->transition($id, $request, 'human_review_required');
    }

    private function transition(string $id, Request $request, string $newStatus): JsonResponse
    {
        try {
            $ulid = Ulid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'Invalid claim id'], Response::HTTP_BAD_REQUEST);
        }

        $claim = $this->em->find(ClaimNode::class, $ulid);
        if ($claim === null) {
            return $this->json(['error' => 'Claim not found'], Response::HTTP_NOT_FOUND);
        }

        $claim->setReviewStatus($newStatus);
        $claim->setReviewedAt(new \DateTimeImmutable());

        // Optional JSON body: {"reason": "...", "reviewer": "alice@example.org"}
        $body = $this->safeDecode($request->getContent());
        if (is_array($body)) {
            if (isset($body['reviewer']) && is_string($body['reviewer'])) {
                $claim->setReviewedBy($body['reviewer']);
            }
            if ($newStatus === 'rejected' && isset($body['reason']) && is_string($body['reason'])) {
                $claim->setReviewReason($body['reason']);
            }
            if ($newStatus === 'human_review_required') {
                // Reopen clears any previous rejection rationale
                $claim->setReviewReason(null);
            }
        }

        $this->em->flush();

        return $this->json([
            'id'            => (string) $claim->getId(),
            'claim_id'      => $claim->getClaimId(),
            'review_status' => $claim->getReviewStatus(),
            'reviewed_at'   => $claim->getReviewedAt()?->format(\DateTimeInterface::ATOM),
            'reviewed_by'   => $claim->getReviewedBy(),
            'review_reason' => $claim->getReviewReason(),
        ]);
    }

    private function safeDecode(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
