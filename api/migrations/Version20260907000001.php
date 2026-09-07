<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'NAM integration model: methods, source projects, CoU applicability, assessments, and comparison-ready project metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE projects ADD project_type VARCHAR(40) DEFAULT 'drug_development' NOT NULL");
        $this->addSql('ALTER TABLE projects ADD source_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD source_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ALTER drug_name DROP NOT NULL');

        $this->addSql("CREATE TABLE namcore_source_project (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, source_type VARCHAR(40) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, organisation VARCHAR(255) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, source_version VARCHAR(120) DEFAULT NULL, source_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IDX_NAM_SOURCE_PROJECT_PROJECT ON namcore_source_project (project_id)');
        $this->addSql("COMMENT ON COLUMN namcore_source_project.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_source_project.project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_source_project.source_updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_source_project.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_source_project.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_source_project ADD CONSTRAINT FK_NAM_SOURCE_PROJECT_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE namcore_nam_method (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, source_project_id CHAR(36) DEFAULT NULL, method_id VARCHAR(160) NOT NULL, method_type VARCHAR(80) NOT NULL, developer VARCHAR(255) DEFAULT NULL, method_version VARCHAR(120) DEFAULT NULL, technical_description TEXT DEFAULT NULL, maturity_status VARCHAR(40) NOT NULL, ontology_iri VARCHAR(500) DEFAULT NULL, external_identifiers JSON NOT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IDX_NAM_METHOD_PROJECT ON namcore_nam_method (project_id)');
        $this->addSql('CREATE INDEX IDX_NAM_METHOD_SOURCE ON namcore_nam_method (source_project_id)');
        $this->addSql('CREATE UNIQUE INDEX UQ_NAM_METHOD_PROJECT_METHOD ON namcore_nam_method (project_id, method_id)');
        $this->addSql("COMMENT ON COLUMN namcore_nam_method.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_nam_method.project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_nam_method.source_project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_nam_method.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_nam_method.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_nam_method ADD CONSTRAINT FK_NAM_METHOD_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_nam_method ADD CONSTRAINT FK_NAM_METHOD_SOURCE FOREIGN KEY (source_project_id) REFERENCES namcore_source_project (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE context_of_use_cards ADD nam_method_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE context_of_use_cards ADD test_article_scope TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE context_of_use_cards ADD applicability_domain JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE context_of_use_cards ALTER applicability_domain DROP DEFAULT');
        $this->addSql('ALTER TABLE context_of_use_cards ADD regulatory_authority VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE context_of_use_cards ADD source_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE context_of_use_cards ADD source_reference VARCHAR(2048) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_COU_NAM_METHOD ON context_of_use_cards (nam_method_id)');
        $this->addSql("COMMENT ON COLUMN context_of_use_cards.nam_method_id IS '(DC2Type:ulid)'");
        $this->addSql('ALTER TABLE context_of_use_cards ADD CONSTRAINT FK_COU_NAM_METHOD FOREIGN KEY (nam_method_id) REFERENCES namcore_nam_method (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE nam_studies ADD nam_method_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_NAM_STUDY_METHOD ON nam_studies (nam_method_id)');
        $this->addSql("COMMENT ON COLUMN nam_studies.nam_method_id IS '(DC2Type:ulid)'");
        $this->addSql('ALTER TABLE nam_studies ADD CONSTRAINT FK_NAM_STUDY_METHOD FOREIGN KEY (nam_method_id) REFERENCES namcore_nam_method (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE namcore_evidence_assessment (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, nam_method_id CHAR(36) DEFAULT NULL, context_of_use_id CHAR(36) DEFAULT NULL, source_project_id CHAR(36) DEFAULT NULL, assessment_type VARCHAR(60) NOT NULL, assessor_organization VARCHAR(255) NOT NULL, authority VARCHAR(120) DEFAULT NULL, status VARCHAR(40) NOT NULL, conclusion TEXT DEFAULT NULL, assessed_at DATE DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, evidence_basis JSON NOT NULL, conditions JSON NOT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IDX_NAM_ASSESS_PROJECT ON namcore_evidence_assessment (project_id)');
        $this->addSql('CREATE INDEX IDX_NAM_ASSESS_METHOD ON namcore_evidence_assessment (nam_method_id)');
        $this->addSql('CREATE INDEX IDX_NAM_ASSESS_COU ON namcore_evidence_assessment (context_of_use_id)');
        $this->addSql('CREATE INDEX IDX_NAM_ASSESS_SOURCE ON namcore_evidence_assessment (source_project_id)');
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.nam_method_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.context_of_use_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.source_project_id IS '(DC2Type:ulid)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.assessed_at IS '(DC2Type:date_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN namcore_evidence_assessment.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE namcore_evidence_assessment ADD CONSTRAINT FK_NAM_ASSESS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_evidence_assessment ADD CONSTRAINT FK_NAM_ASSESS_METHOD FOREIGN KEY (nam_method_id) REFERENCES namcore_nam_method (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_evidence_assessment ADD CONSTRAINT FK_NAM_ASSESS_COU FOREIGN KEY (context_of_use_id) REFERENCES context_of_use_cards (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_evidence_assessment ADD CONSTRAINT FK_NAM_ASSESS_SOURCE FOREIGN KEY (source_project_id) REFERENCES namcore_source_project (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE namcore_evidence_assessment');
        $this->addSql('ALTER TABLE nam_studies DROP CONSTRAINT FK_NAM_STUDY_METHOD');
        $this->addSql('DROP INDEX IDX_NAM_STUDY_METHOD');
        $this->addSql('ALTER TABLE nam_studies DROP nam_method_id');
        $this->addSql('ALTER TABLE context_of_use_cards DROP CONSTRAINT FK_COU_NAM_METHOD');
        $this->addSql('DROP INDEX IDX_COU_NAM_METHOD');
        $this->addSql('ALTER TABLE context_of_use_cards DROP nam_method_id');
        $this->addSql('ALTER TABLE context_of_use_cards DROP test_article_scope');
        $this->addSql('ALTER TABLE context_of_use_cards DROP applicability_domain');
        $this->addSql('ALTER TABLE context_of_use_cards DROP regulatory_authority');
        $this->addSql('ALTER TABLE context_of_use_cards DROP source_text');
        $this->addSql('ALTER TABLE context_of_use_cards DROP source_reference');
        $this->addSql('DROP TABLE namcore_nam_method');
        $this->addSql('DROP TABLE namcore_source_project');
        // Legacy schema requires a non-null drug name. Generic imported projects
        // legitimately use NULL while this migration is active, so make rollback
        // total rather than failing on ALTER ... SET NOT NULL.
        $this->addSql("UPDATE projects SET drug_name = '[not applicable]' WHERE drug_name IS NULL");
        $this->addSql('ALTER TABLE projects ALTER drug_name SET NOT NULL');
        $this->addSql('ALTER TABLE projects DROP project_type');
        $this->addSql('ALTER TABLE projects DROP source_name');
        $this->addSql('ALTER TABLE projects DROP external_id');
        $this->addSql('ALTER TABLE projects DROP source_url');
    }
}
