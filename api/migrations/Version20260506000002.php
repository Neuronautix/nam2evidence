<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 0 integrity constraints and composite indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uq_claim_edge_relation ON claim_edges (from_claim_id, to_claim_id, relationship)');
        $this->addSql('CREATE UNIQUE INDEX uq_evidence_study_domain_question ON evidence_items (study_id, domain, question)');
        $this->addSql('CREATE UNIQUE INDEX uq_mapping_claim_section ON ectd_mappings (claim_id, ectd_section) WHERE claim_id IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_mapping_study_section ON ectd_mappings (study_id, ectd_section) WHERE study_id IS NOT NULL');

        $this->addSql('CREATE INDEX idx_claim_project_review ON claim_nodes (project_id, review_status)');
        $this->addSql('CREATE INDEX idx_evidence_study_domain_status ON evidence_items (study_id, domain, status)');
        $this->addSql('CREATE INDEX idx_ectd_claim_study_section ON ectd_mappings (claim_id, study_id, ectd_section)');
        $this->addSql('CREATE INDEX idx_export_project_exported ON export_packages (project_id, exported_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_export_project_exported');
        $this->addSql('DROP INDEX IF EXISTS idx_ectd_claim_study_section');
        $this->addSql('DROP INDEX IF EXISTS idx_evidence_study_domain_status');
        $this->addSql('DROP INDEX IF EXISTS idx_claim_project_review');

        $this->addSql('DROP INDEX IF EXISTS uq_mapping_study_section');
        $this->addSql('DROP INDEX IF EXISTS uq_mapping_claim_section');
        $this->addSql('DROP INDEX IF EXISTS uq_evidence_study_domain_question');
        $this->addSql('DROP INDEX IF EXISTS uq_claim_edge_relation');
    }
}
