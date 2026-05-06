<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ContextOfUseCard;
use PHPUnit\Framework\TestCase;

final class ContextOfUseCardTest extends TestCase
{
    public function testReviewStatusDefaultsToDraft(): void
    {
        $cou = new ContextOfUseCard();
        self::assertSame('draft', $cou->getReviewStatus());
    }

    public function testReviewStatusIsSettable(): void
    {
        $cou = new ContextOfUseCard();
        $cou->setReviewStatus('approved');
        self::assertSame('approved', $cou->getReviewStatus());
    }

    public function testRegulatoryConfidenceDefaultsToExploratory(): void
    {
        $cou = new ContextOfUseCard();
        self::assertSame('exploratory', $cou->getRegulatoryConfidenceLevel());
    }

    public function testTimestampsArePopulatedOnConstruct(): void
    {
        $cou = new ContextOfUseCard();
        self::assertInstanceOf(\DateTimeImmutable::class, $cou->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $cou->getUpdatedAt());
    }

    public function testLimitationsAndAcceptanceCriteriaDefaultToEmptyArrays(): void
    {
        $cou = new ContextOfUseCard();
        self::assertSame([], $cou->getLimitations());
        self::assertSame([], $cou->getAcceptanceCriteria());
    }
}
