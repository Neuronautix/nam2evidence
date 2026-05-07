<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ClaimNode;

/**
 * Pure predicate for the human-review export gate.
 *
 * Export is blocked while ANY claim in the project is still in
 * `human_review_required`. This service exists as a free-standing predicate so
 * it can be unit-tested without a database round-trip and reused by both the
 * ExportController and the ValidationController.
 */
final class ExportGate
{
    /** Statuses that block export. */
    public const BLOCKING_STATUSES = ['human_review_required', 'pending'];

    /**
     * @param iterable<ClaimNode> $claims
     */
    public static function isBlocked(iterable $claims): bool
    {
        foreach ($claims as $claim) {
            if (in_array($claim->getReviewStatus(), self::BLOCKING_STATUSES, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param iterable<ClaimNode> $claims
     * @return string[] claim_id values that are blocking export
     */
    public static function blockingClaimIds(iterable $claims): array
    {
        $blocking = [];
        foreach ($claims as $claim) {
            if (in_array($claim->getReviewStatus(), self::BLOCKING_STATUSES, true)) {
                $blocking[] = $claim->getClaimId();
            }
        }
        return $blocking;
    }
}
