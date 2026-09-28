<?php

declare(strict_types=1);

namespace App\Tests\Unit\NamCore;

use App\Entity\NamCore\RequirementAssessment;
use PHPUnit\Framework\TestCase;

final class RequirementAssessmentTest extends TestCase
{
    public function testDefaultsAreConservativeAndExplicit(): void
    {
        $assessment = new RequirementAssessment();

        self::assertSame(RequirementAssessment::STATUS_REQUIRES_HUMAN, $assessment->getStatus());
        self::assertSame(RequirementAssessment::ORIGIN_HUMAN, $assessment->getAssessmentOrigin());
        self::assertSame(RequirementAssessment::REVIEW_HUMAN_REQUIRED, $assessment->getReviewStatus());
        self::assertNull($assessment->getReviewedBy());
        self::assertNull($assessment->getReviewedAt());
    }

    public function testMachineOriginDoesNotImplyHumanReview(): void
    {
        $assessment = (new RequirementAssessment())
            ->setAssessmentOrigin(RequirementAssessment::ORIGIN_MACHINE_SUGGESTION)
            ->setStatus(RequirementAssessment::STATUS_PARTIAL);

        self::assertSame(RequirementAssessment::ORIGIN_MACHINE_SUGGESTION, $assessment->getAssessmentOrigin());
        self::assertSame(RequirementAssessment::REVIEW_HUMAN_REQUIRED, $assessment->getReviewStatus());
    }
}
