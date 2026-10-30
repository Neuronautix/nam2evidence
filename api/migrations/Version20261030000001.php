<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'External statements (TIB Knowledge Loom and similar) captured as human-linked external evidence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE namcore_external_statement (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, source_system VARCHAR(40) NOT NULL, statement_uri VARCHAR(500) NOT NULL, record_uri VARCHAR(500) DEFAULT NULL, original_work_doi VARCHAR(255) DEFAULT NULL, source_version VARCHAR(120) NOT NULL, statement_text TEXT NOT NULL, statement_kind VARCHAR(40) NOT NULL, authors JSON NOT NULL, dataset_links JSON NOT NULL, code_links JSON NOT NULL, study_context JSON NOT NULL, uncertainty JSON NOT NULL, licence VARCHAR(120) DEFAULT NULL, access_scope VARCHAR(20) NOT NULL, raw_payload JSON NOT NULL, payload_sha256 VARCHAR(64) NOT NULL, captured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, captured_by VARCHAR(255) NOT NULL, capture_status VARCHAR(30) NOT NULL, verified_by VARCHAR(255) DEFAULT NULL, verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_external_statement_identity ON namcore_external_statement (project_id, statement_uri, source_version)');
        $this->addSql('CREATE INDEX idx_external_statement_project ON namcore_external_statement (project_id)');
        $this->addSql('CREATE INDEX idx_external_statement_source ON namcore_external_statement (source_system)');
        foreach (['id', 'project_id'] as $col) {
            $this->addSql(sprintf("COMMENT ON COLUMN namcore_external_statement.%s IS '(DC2Type:ulid)'", $col));
        }
        foreach (['captured_at', 'verified_at', 'created_at', 'updated_at'] as $col) {
            $this->addSql(sprintf("COMMENT ON COLUMN namcore_external_statement.%s IS '(DC2Type:datetime_immutable)'", $col));
        }
        $this->addSql('ALTER TABLE namcore_external_statement ADD CONSTRAINT fk_external_statement_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE namcore_external_statement_link (id CHAR(36) NOT NULL, external_statement_id CHAR(36) NOT NULL, requirement_assessment_id CHAR(36) DEFAULT NULL, claim_id CHAR(36) DEFAULT NULL, evidence_item_id CHAR(36) DEFAULT NULL, relationship VARCHAR(40) NOT NULL, link_origin VARCHAR(30) NOT NULL, review_status VARCHAR(20) NOT NULL, reviewed_by VARCHAR(255) DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reviewer_note TEXT DEFAULT NULL, comparability_note TEXT DEFAULT NULL, provenance JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_extlink_statement ON namcore_external_statement_link (external_statement_id)');
        $this->addSql('CREATE INDEX idx_extlink_assessment ON namcore_external_statement_link (requirement_assessment_id)');
        $this->addSql('CREATE INDEX idx_extlink_claim ON namcore_external_statement_link (claim_id)');
        $this->addSql('CREATE INDEX idx_extlink_evidence ON namcore_external_statement_link (evidence_item_id)');
        foreach (['id', 'external_statement_id', 'requirement_assessment_id', 'claim_id', 'evidence_item_id'] as $col) {
            $this->addSql(sprintf("COMMENT ON COLUMN namcore_external_statement_link.%s IS '(DC2Type:ulid)'", $col));
        }
        foreach (['reviewed_at', 'created_at', 'updated_at'] as $col) {
            $this->addSql(sprintf("COMMENT ON COLUMN namcore_external_statement_link.%s IS '(DC2Type:datetime_immutable)'", $col));
        }
        // Exactly one target per link, enforced in the database as well as in the entity validator.
        $this->addSql('ALTER TABLE namcore_external_statement_link ADD CONSTRAINT chk_extlink_single_target CHECK ((CASE WHEN requirement_assessment_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN claim_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN evidence_item_id IS NULL THEN 0 ELSE 1 END) = 1)');
        $this->addSql('ALTER TABLE namcore_external_statement_link ADD CONSTRAINT fk_extlink_statement FOREIGN KEY (external_statement_id) REFERENCES namcore_external_statement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_external_statement_link ADD CONSTRAINT fk_extlink_assessment FOREIGN KEY (requirement_assessment_id) REFERENCES namcore_requirement_assessment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_external_statement_link ADD CONSTRAINT fk_extlink_claim FOREIGN KEY (claim_id) REFERENCES claim_nodes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_external_statement_link ADD CONSTRAINT fk_extlink_evidence FOREIGN KEY (evidence_item_id) REFERENCES evidence_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE namcore_external_statement_link');
        $this->addSql('DROP TABLE namcore_external_statement');
    }
}
