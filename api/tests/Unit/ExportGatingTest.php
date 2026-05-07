<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ClaimNode;
use App\Service\Export\ExportGate;
use PHPUnit\Framework\TestCase;

final class ExportGatingTest extends TestCase
{
    public function testHumanReviewRequiredBlocksExport(): void
    {
        $claim = new ClaimNode();
        $claim->setClaimId('CLM-001');
        $claim->setReviewStatus('human_review_required');

        self::assertTrue(
            ExportGate::isBlocked([$claim]),
            'A claim in human_review_required must block export',
        );
        self::assertSame(['CLM-001'], ExportGate::blockingClaimIds([$claim]));
    }

    public function testApprovedClaimsDoNotBlockExport(): void
    {
        $a = new ClaimNode();
        $a->setClaimId('CLM-A')->setReviewStatus('approved');
        $b = new ClaimNode();
        $b->setClaimId('CLM-B')->setReviewStatus('approved');

        self::assertFalse(ExportGate::isBlocked([$a, $b]));
        self::assertSame([], ExportGate::blockingClaimIds([$a, $b]));
    }

    public function testRejectedClaimsDoNotBlockExport(): void
    {
        // Rejected is a terminal state — reviewer has decided. It does not block.
        $rejected = new ClaimNode();
        $rejected->setClaimId('CLM-R')->setReviewStatus('rejected');

        self::assertFalse(ExportGate::isBlocked([$rejected]));
    }

    public function testSinglePendingClaimAmongApprovedBlocks(): void
    {
        $approved = (new ClaimNode())->setClaimId('CLM-OK')->setReviewStatus('approved');
        $pending  = (new ClaimNode())->setClaimId('CLM-WAIT')->setReviewStatus('human_review_required');

        self::assertTrue(ExportGate::isBlocked([$approved, $pending]));
        self::assertSame(['CLM-WAIT'], ExportGate::blockingClaimIds([$approved, $pending]));
    }

    public function testEmptyClaimSetDoesNotBlock(): void
    {
        self::assertFalse(ExportGate::isBlocked([]));
    }
}
