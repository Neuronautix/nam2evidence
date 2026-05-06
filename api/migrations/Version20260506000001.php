<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema for NAMO-to-IND Mapper.
 *
 * Tables:
 *   projects
 *   context_of_use_cards
 *   nam_studies
 *   evidence_items
 *   claim_nodes
 *   claim_edges
 *   ectd_mappings
 *   export_packages
 *
 * JSONB columns are used for all compound/array fields to allow schema-flexible
 * metadata storage aligned with the NAMO ontology.
 */
final class Version20260506000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial NAMO-to-IND Mapper schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
        SQL);

        // ── projects ──────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE projects (
                id             CHAR(36) NOT NULL,
                name           VARCHAR(255) NOT NULL,
                description    TEXT,
                drug_name      VARCHAR(255) NOT NULL,
                sponsor        VARCHAR(255),
                review_status  VARCHAR(50) NOT NULL DEFAULT 'pending',
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT pk_projects PRIMARY KEY (id)
            )
        SQL);

        // ── context_of_use_cards ──────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE context_of_use_cards (
                id                           CHAR(36) NOT NULL,
                cou_id                       VARCHAR(100) NOT NULL,
                project_id                   CHAR(36) NOT NULL,
                nam_type                     VARCHAR(60) NOT NULL,
                regulatory_question          TEXT NOT NULL,
                drug_development_stage       VARCHAR(60) NOT NULL,
                intended_use                 TEXT NOT NULL DEFAULT '',
                decision_supported           TEXT NOT NULL DEFAULT '',
                biological_domain            VARCHAR(255) NOT NULL DEFAULT '',
                endpoint_class               VARCHAR(255) NOT NULL DEFAULT '',
                population_relevance         TEXT,
                limitations                  JSONB NOT NULL DEFAULT '[]',
                acceptance_criteria          JSONB NOT NULL DEFAULT '[]',
                regulatory_confidence_level  VARCHAR(30) NOT NULL DEFAULT 'exploratory',
                version                      VARCHAR(20) NOT NULL DEFAULT '1.0',
                created_at                   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at                   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT pk_cou_cards PRIMARY KEY (id),
                CONSTRAINT uq_cou_id UNIQUE (cou_id),
                CONSTRAINT fk_cou_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_cou_project ON context_of_use_cards (project_id)');

        // ── nam_studies ───────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE nam_studies (
                id                  CHAR(36) NOT NULL,
                study_id            VARCHAR(100) NOT NULL,
                project_id          CHAR(36) NOT NULL,
                context_of_use_id   CHAR(36) NOT NULL,
                title               TEXT NOT NULL DEFAULT '',
                model_system        JSONB NOT NULL DEFAULT '{}',
                experimental_design JSONB NOT NULL DEFAULT '{}',
                assay_metadata      JSONB NOT NULL DEFAULT '{}',
                data_outputs        JSONB NOT NULL DEFAULT '{}',
                provenance          JSONB NOT NULL DEFAULT '{}',
                created_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT pk_nam_studies PRIMARY KEY (id),
                CONSTRAINT uq_study_id UNIQUE (study_id),
                CONSTRAINT fk_study_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_study_cou FOREIGN KEY (context_of_use_id) REFERENCES context_of_use_cards (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_study_project ON nam_studies (project_id)');

        // ── evidence_items ────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE evidence_items (
                id              CHAR(36) NOT NULL,
                evidence_id     VARCHAR(100) NOT NULL,
                study_id        CHAR(36) NOT NULL,
                domain          VARCHAR(60) NOT NULL,
                question        TEXT NOT NULL,
                evidence_type   VARCHAR(255) NOT NULL DEFAULT '',
                status          VARCHAR(20) NOT NULL DEFAULT 'not_applicable',
                notes           TEXT,
                supporting_data TEXT,
                CONSTRAINT pk_evidence_items PRIMARY KEY (id),
                CONSTRAINT uq_evidence_id UNIQUE (evidence_id),
                CONSTRAINT fk_evidence_study FOREIGN KEY (study_id) REFERENCES nam_studies (id) ON DELETE CASCADE,
                CONSTRAINT chk_evidence_status CHECK (status IN ('met','partial','not_met','not_applicable')),
                CONSTRAINT chk_evidence_domain CHECK (domain IN (
                    'analytical_validity','technical_reproducibility','biological_relevance',
                    'reference_compound_performance','exposure_relevance','data_integrity',
                    'limitation_analysis','regulatory_alignment'
                ))
            )
        SQL);
        $this->addSql('CREATE INDEX idx_evidence_study ON evidence_items (study_id)');
        $this->addSql('CREATE INDEX idx_evidence_domain ON evidence_items (domain)');

        // ── claim_nodes ───────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE claim_nodes (
                id                    CHAR(36) NOT NULL,
                claim_id              VARCHAR(100) NOT NULL,
                project_id            CHAR(36) NOT NULL,
                claim_text            TEXT NOT NULL,
                claim_type            VARCHAR(30) NOT NULL DEFAULT 'empirical',
                context_of_use_id     CHAR(36) NOT NULL,
                confidence            VARCHAR(30) NOT NULL DEFAULT 'exploratory',
                supporting_evidence   JSONB NOT NULL DEFAULT '[]',
                contradictory_evidence JSONB NOT NULL DEFAULT '[]',
                limitations           JSONB NOT NULL DEFAULT '[]',
                ectd_target_sections  JSONB NOT NULL DEFAULT '[]',
                review_status         VARCHAR(30) NOT NULL DEFAULT 'human_review_required',
                parent_claim_id       CHAR(36),
                CONSTRAINT pk_claim_nodes PRIMARY KEY (id),
                CONSTRAINT uq_claim_id UNIQUE (claim_id),
                CONSTRAINT fk_claim_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_claim_cou FOREIGN KEY (context_of_use_id) REFERENCES context_of_use_cards (id),
                CONSTRAINT fk_claim_parent FOREIGN KEY (parent_claim_id) REFERENCES claim_nodes (id),
                CONSTRAINT chk_confidence CHECK (confidence IN ('exploratory','supportive','decision_informing','potentially_pivotal')),
                CONSTRAINT chk_review_status CHECK (review_status IN ('pending','human_review_required','approved','rejected'))
            )
        SQL);
        $this->addSql('CREATE INDEX idx_claim_project ON claim_nodes (project_id)');
        $this->addSql('CREATE INDEX idx_claim_review ON claim_nodes (review_status)');

        // ── claim_edges ───────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE claim_edges (
                id              CHAR(36) NOT NULL,
                from_claim_id   CHAR(36) NOT NULL,
                to_claim_id     CHAR(36) NOT NULL,
                relationship    VARCHAR(20) NOT NULL DEFAULT 'supports',
                CONSTRAINT pk_claim_edges PRIMARY KEY (id),
                CONSTRAINT fk_edge_from FOREIGN KEY (from_claim_id) REFERENCES claim_nodes (id) ON DELETE CASCADE,
                CONSTRAINT fk_edge_to   FOREIGN KEY (to_claim_id)   REFERENCES claim_nodes (id) ON DELETE CASCADE,
                CONSTRAINT chk_relationship CHECK (relationship IN ('supports','refutes','qualifies','requires'))
            )
        SQL);

        // ── ectd_mappings ─────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE ectd_mappings (
                id              CHAR(36) NOT NULL,
                mapping_id      VARCHAR(100) NOT NULL,
                study_id        CHAR(36),
                claim_id        CHAR(36),
                evidence_type   VARCHAR(255) NOT NULL DEFAULT '',
                ectd_section    VARCHAR(30) NOT NULL,
                ectd_title      VARCHAR(255) NOT NULL DEFAULT '',
                notes           TEXT,
                justification   TEXT,
                CONSTRAINT pk_ectd_mappings PRIMARY KEY (id),
                CONSTRAINT uq_mapping_id UNIQUE (mapping_id),
                CONSTRAINT fk_mapping_study FOREIGN KEY (study_id) REFERENCES nam_studies (id) ON DELETE SET NULL,
                CONSTRAINT fk_mapping_claim FOREIGN KEY (claim_id) REFERENCES claim_nodes (id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_ectd_section ON ectd_mappings (ectd_section)');

        // ── export_packages ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE export_packages (
                id          CHAR(36) NOT NULL,
                package_id  VARCHAR(100) NOT NULL,
                project_id  CHAR(36) NOT NULL,
                payload     JSONB NOT NULL DEFAULT '{}',
                version     VARCHAR(20) NOT NULL DEFAULT '1.0',
                exported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT pk_export_packages PRIMARY KEY (id),
                CONSTRAINT uq_package_id UNIQUE (package_id),
                CONSTRAINT fk_pkg_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pkg_project ON export_packages (project_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS export_packages CASCADE');
        $this->addSql('DROP TABLE IF EXISTS ectd_mappings CASCADE');
        $this->addSql('DROP TABLE IF EXISTS claim_edges CASCADE');
        $this->addSql('DROP TABLE IF EXISTS claim_nodes CASCADE');
        $this->addSql('DROP TABLE IF EXISTS evidence_items CASCADE');
        $this->addSql('DROP TABLE IF EXISTS nam_studies CASCADE');
        $this->addSql('DROP TABLE IF EXISTS context_of_use_cards CASCADE');
        $this->addSql('DROP TABLE IF EXISTS projects CASCADE');
    }
}
