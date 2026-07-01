<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\NamCore\AuditLog;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Central append-only audit writer. Every material change (import, ontology
 * approval/rejection, review-gate decision, export) records who/what/when/why
 * so a project carries a defensible trail. Flushing is the caller's choice
 * (default: flush immediately) so it can participate in a larger transaction.
 */
final class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @param array<string,mixed>|null $oldValue
     * @param array<string,mixed>|null $newValue
     */
    public function log(
        ?Project $project,
        string $entityType,
        ?string $entityId,
        string $action,
        ?array $oldValue,
        ?array $newValue,
        ?string $reason = null,
        string $userOrRole = 'system',
        bool $flush = true,
    ): AuditLog {
        $entry = (new AuditLog())
            ->setProject($project)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setAction($action)
            ->setOldValue($oldValue)
            ->setNewValue($newValue)
            ->setReason($reason)
            ->setUserOrRole($userOrRole);

        $this->em->persist($entry);
        if ($flush) {
            $this->em->flush();
        }

        return $entry;
    }
}
