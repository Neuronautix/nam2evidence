<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Strengthen the eCTD mapper with structured placement fields: module,
 * document type, evidence package component, placement rationale, supported
 * claim / validation-evidence id lists, reviewer status, and a standing caveat.
 * Additive; JSON columns default to an empty array so existing rows are valid.
 */
final class Version20260701000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured placement fields to ectd_mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ectd_mappings ADD module VARCHAR(10) DEFAULT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD document_type VARCHAR(120) DEFAULT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD evidence_package_component VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD placement_rationale TEXT DEFAULT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD claim_ids_supported JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD validation_evidence_ids_supported JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE ectd_mappings ADD reviewer_status VARCHAR(20) DEFAULT 'proposed'");
        $this->addSql("ALTER TABLE ectd_mappings ADD caveat TEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        foreach (['module', 'document_type', 'evidence_package_component', 'placement_rationale',
                  'claim_ids_supported', 'validation_evidence_ids_supported', 'reviewer_status', 'caveat'] as $col) {
            $this->addSql("ALTER TABLE ectd_mappings DROP COLUMN $col");
        }
    }
}
