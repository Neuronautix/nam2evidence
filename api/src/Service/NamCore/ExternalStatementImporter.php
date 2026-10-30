<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\NamCore\ExternalStatement;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Captures external statements (TIB Knowledge Loom records today) into a
 * project without ever rewriting a capture.
 *
 * Contract: `external-statement-capture/0.1.0`
 *
 *   {
 *     "source_system": "loom",
 *     "captured_by": "name or adapter id",
 *     "record": { "record_uri", "original_work_doi", "source_version", "licence", "access_scope", "authors": [] },
 *     "statements": [ { "statement_uri", "text", "kind", "dataset_links", "code_links",
 *                       "study_context", "uncertainty", "licence"?, "access_scope"?, "source_version"? } ]
 *   }
 *
 * Identity is (project, statement_uri, source_version). Re-importing identical
 * bytes is a replay; importing different bytes under the same identity is a
 * conflict and nothing is written — a changed Loom statement must arrive as a
 * new source_version so existing human decisions keep their meaning.
 */
final class ExternalStatementImporter
{
    public const CONTRACT = 'external-statement-capture/0.1.0';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array{created: list<ExternalStatement>, replayed: list<ExternalStatement>, conflicts: list<array<string,string>>, errors: list<string>}
     */
    public function import(Project $project, array $payload, bool $flush = true): array
    {
        $result = ['created' => [], 'replayed' => [], 'conflicts' => [], 'errors' => []];

        $sourceSystem = strtolower(trim((string) ($payload['source_system'] ?? ExternalStatement::SOURCE_LOOM)));
        $capturedBy = trim((string) ($payload['captured_by'] ?? ''));
        if ($capturedBy === '') {
            $result['errors'][] = 'captured_by is required so every capture is attributable.';
            return $result;
        }

        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $statements = $payload['statements'] ?? null;
        if (!is_array($statements) || $statements === []) {
            $result['errors'][] = 'statements must be a non-empty array.';
            return $result;
        }

        $capture = trim((string) ($payload['capture_status'] ?? ExternalStatement::CAPTURE_UNVERIFIED));
        if (!in_array($capture, [ExternalStatement::CAPTURE_UNVERIFIED, ExternalStatement::CAPTURE_VERIFIED], true)) {
            $result['errors'][] = 'capture_status must be captured_unverified or captured_verified.';
            return $result;
        }

        $pending = [];
        $seen = [];
        foreach ($statements as $index => $row) {
            if (!is_array($row)) {
                $result['errors'][] = sprintf('statements[%d] must be an object.', $index);
                continue;
            }
            $uri = trim((string) ($row['statement_uri'] ?? ''));
            $text = trim((string) ($row['text'] ?? $row['statement_text'] ?? ''));
            $version = trim((string) ($row['source_version'] ?? $record['source_version'] ?? '1'));
            if ($uri === '' || $text === '') {
                $result['errors'][] = sprintf('statements[%d] requires statement_uri and text.', $index);
                continue;
            }
            $identity = $uri . '|' . $version;
            if (isset($seen[$identity])) {
                $result['errors'][] = sprintf('Duplicate statement identity in payload: %s (version %s).', $uri, $version);
                continue;
            }
            $seen[$identity] = true;

            $accessScope = strtolower(trim((string) ($row['access_scope'] ?? $record['access_scope'] ?? ExternalStatement::ACCESS_UNKNOWN)));
            if (!in_array($accessScope, [ExternalStatement::ACCESS_PUBLIC, ExternalStatement::ACCESS_RESTRICTED, ExternalStatement::ACCESS_UNKNOWN], true)) {
                $result['errors'][] = sprintf('statements[%d] access_scope must be public, restricted or unknown.', $index);
                continue;
            }
            $kind = trim((string) ($row['kind'] ?? $row['statement_kind'] ?? ExternalStatement::KIND_CURATED_STATEMENT));

            $hash = self::hashPayload($row);
            $existing = $this->em->getRepository(ExternalStatement::class)->findOneBy([
                'project' => $project,
                'statementUri' => $uri,
                'sourceVersion' => $version,
            ]);
            if ($existing instanceof ExternalStatement) {
                if ($existing->getPayloadSha256() === $hash) {
                    $result['replayed'][] = $existing;
                } else {
                    $result['conflicts'][] = [
                        'statement_uri' => $uri,
                        'source_version' => $version,
                        'existing_sha256' => $existing->getPayloadSha256(),
                        'incoming_sha256' => $hash,
                        'message' => 'Different bytes for an already captured statement identity. Import it as a new source_version instead of rewriting the capture.',
                    ];
                }
                continue;
            }

            $statement = (new ExternalStatement())
                ->setProject($project)
                ->setSourceSystem($sourceSystem !== '' ? $sourceSystem : ExternalStatement::SOURCE_LOOM)
                ->setStatementUri($uri)
                ->setRecordUri($this->nullableString($row['record_uri'] ?? $record['record_uri'] ?? null))
                ->setOriginalWorkDoi($this->nullableString($row['original_work_doi'] ?? $record['original_work_doi'] ?? null))
                ->setSourceVersion($version)
                ->setStatementText($text)
                ->setStatementKind($kind)
                ->setAuthors($this->arrayValue($row['authors'] ?? $record['authors'] ?? []))
                ->setDatasetLinks($this->arrayValue($row['dataset_links'] ?? []))
                ->setCodeLinks($this->arrayValue($row['code_links'] ?? []))
                ->setStudyContext($this->arrayValue($row['study_context'] ?? []))
                ->setUncertainty($this->arrayValue($row['uncertainty'] ?? []))
                ->setLicence($this->nullableString($row['licence'] ?? $record['licence'] ?? null))
                ->setAccessScope($accessScope)
                ->setRawPayload($row)
                ->setPayloadSha256($hash)
                ->setCapturedBy($capturedBy)
                ->setCaptureStatus($capture);
            if ($capture === ExternalStatement::CAPTURE_VERIFIED) {
                $statement->setVerifiedBy($capturedBy)->setVerifiedAt(new \DateTimeImmutable());
            }
            if (isset($row['captured_at']) && is_string($row['captured_at'])) {
                try { $statement->setCapturedAt(new \DateTimeImmutable($row['captured_at'])); } catch (\Throwable) {
                    $result['errors'][] = sprintf('statements[%d] captured_at is not a valid date.', $index);
                    continue;
                }
            }

            $violations = $this->validator->validate($statement);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $result['errors'][] = sprintf('statements[%d] %s: %s', $index, $violation->getPropertyPath(), $violation->getMessage());
                }
                continue;
            }
            $pending[] = $statement;
        }

        // Fail closed: a payload with any error or conflict writes nothing.
        if ($result['errors'] !== [] || $result['conflicts'] !== []) {
            return $result;
        }

        foreach ($pending as $statement) {
            $this->em->persist($statement);
            $result['created'][] = $statement;
        }
        if ($flush && $pending !== []) {
            $this->em->flush();
        }

        return $result;
    }

    /** Canonical SHA-256 of one statement payload (sorted keys, stable encoding). */
    public static function hashPayload(array $row): string
    {
        return hash('sha256', json_encode(self::sortRecursive($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $k => $v) $out[$k] = self::sortRecursive($v);
        if (!$isList) ksort($out, SORT_STRING);
        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @return array<mixed> */
    private function arrayValue(mixed $value): array { return is_array($value) ? $value : []; }
}
