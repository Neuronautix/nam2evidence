<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Versioned regulatory guidance profiles, requirements, Context-of-Use assessments, and evidence links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE namcore_guidance_profile (id CHAR(36) NOT NULL, profile_key VARCHAR(160) NOT NULL, authority VARCHAR(255) NOT NULL, title VARCHAR(500) NOT NULL, canonical_source_url VARCHAR(2048) NOT NULL, publication_date DATE DEFAULT NULL, effective_date DATE DEFAULT NULL, status VARCHAR(20) NOT NULL, version VARCHAR(40) NOT NULL, source_version VARCHAR(120) DEFAULT NULL, jurisdiction VARCHAR(120) NOT NULL, source_hash VARCHAR(128) DEFAULT NULL, retrieved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_guidance_profile_key_version ON namcore_guidance_profile (profile_key, version)');
        $this->addSql('CREATE INDEX idx_guidance_profile_authority ON namcore_guidance_profile (authority)');
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.publication_date IS '(DC2Type:date_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.effective_date IS '(DC2Type:date_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.retrieved_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_profile.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql("CREATE TABLE namcore_guidance_requirement (id CHAR(36) NOT NULL, profile_id CHAR(36) NOT NULL, requirement_key VARCHAR(160) NOT NULL, title VARCHAR(500) NOT NULL, requirement_text TEXT NOT NULL, evidence_domain VARCHAR(80) NOT NULL, applicability_rules JSON NOT NULL, importance VARCHAR(20) NOT NULL, source_locator VARCHAR(500) NOT NULL, interpretation_note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_guidance_requirement_profile_key ON namcore_guidance_requirement (profile_id, requirement_key)');
        $this->addSql('CREATE INDEX idx_guidance_requirement_profile ON namcore_guidance_requirement (profile_id)');
        $this->addSql('CREATE INDEX idx_guidance_requirement_domain ON namcore_guidance_requirement (evidence_domain)');
        $this->addSql("COMMENT ON COLUMN namcore_guidance_requirement.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_requirement.profile_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_requirement.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_guidance_requirement.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_guidance_requirement ADD CONSTRAINT fk_guidance_requirement_profile FOREIGN KEY (profile_id) REFERENCES namcore_guidance_profile (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE namcore_requirement_assessment (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, context_of_use_id CHAR(36) NOT NULL, requirement_id CHAR(36) NOT NULL, status VARCHAR(40) NOT NULL, rationale TEXT DEFAULT NULL, assessment_origin VARCHAR(30) NOT NULL, review_status VARCHAR(30) NOT NULL, reviewed_by VARCHAR(255) DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reviewer_comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_requirement_assessment_scope ON namcore_requirement_assessment (project_id, context_of_use_id, requirement_id)');
        $this->addSql('CREATE INDEX idx_requirement_assessment_project ON namcore_requirement_assessment (project_id)');
        $this->addSql('CREATE INDEX idx_requirement_assessment_cou ON namcore_requirement_assessment (context_of_use_id)');
        $this->addSql('CREATE INDEX idx_requirement_assessment_requirement ON namcore_requirement_assessment (requirement_id)');
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.context_of_use_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.requirement_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.reviewed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_assessment.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_requirement_assessment ADD CONSTRAINT fk_requirement_assessment_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_requirement_assessment ADD CONSTRAINT fk_requirement_assessment_cou FOREIGN KEY (context_of_use_id) REFERENCES context_of_use_cards (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_requirement_assessment ADD CONSTRAINT fk_requirement_assessment_requirement FOREIGN KEY (requirement_id) REFERENCES namcore_guidance_requirement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE namcore_requirement_evidence_link (id CHAR(36) NOT NULL, assessment_id CHAR(36) NOT NULL, evidence_item_id CHAR(36) NOT NULL, relationship VARCHAR(40) NOT NULL, provenance JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_requirement_evidence_relationship ON namcore_requirement_evidence_link (assessment_id, evidence_item_id, relationship)');
        $this->addSql('CREATE INDEX idx_requirement_evidence_assessment ON namcore_requirement_evidence_link (assessment_id)');
        $this->addSql('CREATE INDEX idx_requirement_evidence_item ON namcore_requirement_evidence_link (evidence_item_id)');
        $this->addSql("COMMENT ON COLUMN namcore_requirement_evidence_link.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_evidence_link.assessment_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_evidence_link.evidence_item_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_requirement_evidence_link.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_requirement_evidence_link ADD CONSTRAINT fk_requirement_evidence_assessment FOREIGN KEY (assessment_id) REFERENCES namcore_requirement_assessment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_requirement_evidence_link ADD CONSTRAINT fk_requirement_evidence_item FOREIGN KEY (evidence_item_id) REFERENCES evidence_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE namcore_requirement_evidence_link');
        $this->addSql('DROP TABLE namcore_requirement_assessment');
        $this->addSql('DROP TABLE namcore_guidance_requirement');
        $this->addSql('DROP TABLE namcore_guidance_profile');
    }
}
