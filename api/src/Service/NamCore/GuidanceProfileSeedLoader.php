<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Controlled loader for versioned guidance-profile seed files.
 *
 * Reference records are immutable by profile_key + version. Re-running the
 * identical seed is idempotent. If the seed content changes while keeping the
 * same version, loading fails and the curator must bump the profile version.
 */
final class GuidanceProfileSeedLoader
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @return array{profile:GuidanceProfile,created:bool,requirements_created:int,requirements_total:int,seed_sha256:string}
     */
    public function loadFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('Guidance seed file not found or unreadable: %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException(sprintf('Unable to read guidance seed file: %s', $path));
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Invalid JSON in %s: %s', $path, $e->getMessage()), previous: $e);
        }

        if (!is_array($data) || !is_array($data['profile'] ?? null) || !is_array($data['requirements'] ?? null)) {
            throw new \RuntimeException('Guidance seed must contain "profile" and "requirements" objects.');
        }

        $profileData = $data['profile'];
        $requirementsData = $data['requirements'];
        $profileKey = trim((string) ($profileData['profile_key'] ?? ''));
        $version = trim((string) ($profileData['version'] ?? ''));
        if ($profileKey === '' || $version === '') {
            throw new \RuntimeException('Guidance seed profile_key and version are required.');
        }
        if ($requirementsData === []) {
            throw new \RuntimeException('Guidance seed must contain at least one requirement.');
        }

        $seedSha = hash('sha256', $raw);

        /** @var GuidanceProfile|null $existing */
        $existing = $this->em->getRepository(GuidanceProfile::class)->findOneBy([
            'profileKey' => $profileKey,
            'version' => $version,
        ]);

        if ($existing !== null) {
            $storedSeedSha = (string) ($existing->getSourceMetadata()['seed_sha256'] ?? '');
            if ($storedSeedSha !== $seedSha) {
                throw new \RuntimeException(sprintf(
                    'Guidance profile %s version %s already exists but the seed content changed. Create a new profile version instead of overwriting a curated reference.',
                    $profileKey,
                    $version,
                ));
            }

            $total = count($this->em->getRepository(GuidanceRequirement::class)->findBy(['profile' => $existing]));
            if ($total !== count($requirementsData)) {
                throw new \RuntimeException(sprintf(
                    'Guidance profile %s version %s is incomplete in the database (%d/%d requirements). Refusing silent repair; reload from a clean database or create a new version.',
                    $profileKey,
                    $version,
                    $total,
                    count($requirementsData),
                ));
            }

            return [
                'profile' => $existing,
                'created' => false,
                'requirements_created' => 0,
                'requirements_total' => $total,
                'seed_sha256' => $seedSha,
            ];
        }

        $this->validateSeedRequirements($requirementsData);

        $metadata = is_array($profileData['source_metadata'] ?? null) ? $profileData['source_metadata'] : [];
        $metadata['seed_sha256'] = $seedSha;
        $metadata['seed_file'] = basename($path);

        $profile = (new GuidanceProfile())
            ->setProfileKey($profileKey)
            ->setAuthority($this->requiredString($profileData, 'authority'))
            ->setTitle($this->requiredString($profileData, 'title'))
            ->setCanonicalSourceUrl($this->requiredString($profileData, 'canonical_source_url'))
            ->setPublicationDate($this->optionalDate($profileData['publication_date'] ?? null))
            ->setEffectiveDate($this->optionalDate($profileData['effective_date'] ?? null))
            ->setStatus($this->requiredString($profileData, 'status'))
            ->setVersion($version)
            ->setSourceVersion($this->optionalString($profileData['source_version'] ?? null))
            ->setJurisdiction($this->requiredString($profileData, 'jurisdiction'))
            ->setSourceHash($this->optionalString($profileData['source_hash'] ?? null))
            ->setRetrievedAt($this->requiredDateTime($profileData, 'retrieved_at'))
            ->setSourceMetadata($metadata);

        $this->assertValid($profile, 'GuidanceProfile');
        $this->em->persist($profile);

        $created = 0;
        foreach ($requirementsData as $entry) {
            $requirement = (new GuidanceRequirement())
                ->setProfile($profile)
                ->setRequirementKey($this->requiredString($entry, 'requirement_key'))
                ->setTitle($this->requiredString($entry, 'title'))
                ->setRequirementText($this->requiredString($entry, 'requirement_text'))
                ->setEvidenceDomain($this->requiredString($entry, 'evidence_domain'))
                ->setApplicabilityRules(is_array($entry['applicability_rules'] ?? null) ? $entry['applicability_rules'] : [])
                ->setImportance($this->requiredString($entry, 'importance'))
                ->setSourceLocator($this->requiredString($entry, 'source_locator'))
                ->setInterpretationNote($this->optionalString($entry['interpretation_note'] ?? null));

            $this->assertValid($requirement, 'GuidanceRequirement ' . $requirement->getRequirementKey());
            $this->em->persist($requirement);
            $created++;
        }

        $this->em->flush();

        return [
            'profile' => $profile,
            'created' => true,
            'requirements_created' => $created,
            'requirements_total' => count($requirementsData),
            'seed_sha256' => $seedSha,
        ];
    }

    /** @param list<mixed> $requirements */
    private function validateSeedRequirements(array $requirements): void
    {
        $seen = [];
        foreach ($requirements as $index => $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException(sprintf('Requirement at index %d must be an object.', $index));
            }
            $key = trim((string) ($entry['requirement_key'] ?? ''));
            if ($key === '') {
                throw new \RuntimeException(sprintf('Requirement at index %d is missing requirement_key.', $index));
            }
            if (isset($seen[$key])) {
                throw new \RuntimeException(sprintf('Duplicate requirement_key in seed: %s', $key));
            }
            $seen[$key] = true;

            foreach (['title', 'requirement_text', 'evidence_domain', 'importance', 'source_locator'] as $required) {
                if (trim((string) ($entry[$required] ?? '')) === '') {
                    throw new \RuntimeException(sprintf('Requirement %s is missing %s.', $key, $required));
                }
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \RuntimeException(sprintf('Guidance seed is missing required field "%s".', $key));
        }
        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function optionalDate(mixed $value): ?\DateTimeImmutable
    {
        $text = $this->optionalString($value);
        if ($text === null) return null;
        try {
            return new \DateTimeImmutable($text);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Invalid date "%s" in guidance seed.', $text), previous: $e);
        }
    }

    /** @param array<string,mixed> $data */
    private function requiredDateTime(array $data, string $key): \DateTimeImmutable
    {
        $value = $this->requiredString($data, $key);
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Invalid datetime "%s" for "%s".', $value, $key), previous: $e);
        }
    }

    private function assertValid(object $entity, string $label): void
    {
        $violations = $this->validator->validate($entity);
        if (count($violations) === 0) return;

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }
        throw new \RuntimeException(sprintf('%s validation failed: %s', $label, implode('; ', $messages)));
    }
}
