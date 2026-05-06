<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive schema migration:
 *   - context_of_use_cards.review_status        (NOT NULL, default 'draft')
 *   - evidence_items.metric_value / threshold / pass_fail   (nullable)
 *   - claim_nodes.node_type                     (NOT NULL, default 'claim')
 *   - claim_nodes.claim_type                    (relax to nullable)
 *   - claim_nodes.reviewed_at / reviewed_by / review_reason (nullable)
 *   - ectd_mappings.confidence                  (nullable)
 *   - claim_edges.relationship CHECK widened to 10 NAMO-brief edges
 *
 * All changes are additive; no existing data is destroyed.
 */
final class Version20260507000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'NAMO brief alignment: review status, ontology + threshold fields, expanded claim graph vocabulary';
    }

    public function up(Schema $schema): void
    {
        // ── context_of_use_cards.review_status ────────────────────────────────
        $this->addSql(<<<'SQL'
            ALTER TABLE context_of_use_cards
            ADD COLUMN IF NOT EXISTS review_status VARCHAR(30) NOT NULL DEFAULT 'draft'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE context_of_use_cards
            DROP CONSTRAINT IF EXISTS chk_cou_review_status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE context_of_use_cards
            ADD CONSTRAINT chk_cou_review_status
            CHECK (review_status IN ('draft','validated','reviewer_pending','approved','rejected'))
        SQL);

        // ── evidence_items: metric_value / threshold / pass_fail ──────────────
        $this->addSql('ALTER TABLE evidence_items ADD COLUMN IF NOT EXISTS metric_value VARCHAR(255)');
        $this->addSql('ALTER TABLE evidence_items ADD COLUMN IF NOT EXISTS threshold VARCHAR(255)');
        $this->addSql('ALTER TABLE evidence_items ADD COLUMN IF NOT EXISTS pass_fail VARCHAR(10)');
        $this->addSql(<<<'SQL'
            ALTER TABLE evidence_items
            DROP CONSTRAINT IF EXISTS chk_evidence_pass_fail
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE evidence_items
            ADD CONSTRAINT chk_evidence_pass_fail
            CHECK (pass_fail IS NULL OR pass_fail IN ('pass','fail'))
        SQL);

        // ── claim_nodes.node_type + relax claim_type ──────────────────────────
        $this->addSql(<<<'SQL'
            ALTER TABLE claim_nodes
            ADD COLUMN IF NOT EXISTS node_type VARCHAR(30) NOT NULL DEFAULT 'claim'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE claim_nodes
            DROP CONSTRAINT IF EXISTS chk_claim_node_type
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE claim_nodes
            ADD CONSTRAINT chk_claim_node_type
            CHECK (node_type IN (
                'claim','evidence_item','study','model_system',
                'limitation','assumption','reviewer_decision','export_target'
            ))
        SQL);
        // Relax claim_type to nullable so limitation/assumption nodes don't need it.
        $this->addSql('ALTER TABLE claim_nodes ALTER COLUMN claim_type DROP NOT NULL');

        // ── claim_nodes: reviewer audit columns ───────────────────────────────
        $this->addSql('ALTER TABLE claim_nodes ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE claim_nodes ADD COLUMN IF NOT EXISTS reviewed_by VARCHAR(255)');
        $this->addSql('ALTER TABLE claim_nodes ADD COLUMN IF NOT EXISTS review_reason TEXT');

        // ── ectd_mappings.confidence ──────────────────────────────────────────
        $this->addSql('ALTER TABLE ectd_mappings ADD COLUMN IF NOT EXISTS confidence VARCHAR(10)');
        $this->addSql(<<<'SQL'
            ALTER TABLE ectd_mappings
            DROP CONSTRAINT IF EXISTS chk_ectd_confidence
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ectd_mappings
            ADD CONSTRAINT chk_ectd_confidence
            CHECK (confidence IS NULL OR confidence IN ('low','medium','high'))
        SQL);

        // ── claim_edges.relationship: widen vocabulary ────────────────────────
        $this->addSql('ALTER TABLE claim_edges ALTER COLUMN relationship TYPE VARCHAR(30)');
        $this->addSql('ALTER TABLE claim_edges DROP CONSTRAINT IF EXISTS chk_relationship');
        $this->addSql(<<<'SQL'
            ALTER TABLE claim_edges
            ADD CONSTRAINT chk_relationship
            CHECK (relationship IN (
                'supports','contradicts','refutes','qualifies','requires',
                'depends_on','limited_by','derived_from','conforms_to','maps_to_ectd_section'
            ))
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Reverse the relationship CHECK widening
        $this->addSql('ALTER TABLE claim_edges DROP CONSTRAINT IF EXISTS chk_relationship');
        $this->addSql(<<<'SQL'
            ALTER TABLE claim_edges
            ADD CONSTRAINT chk_relationship
            CHECK (relationship IN ('supports','refutes','qualifies','requires'))
        SQL);
        $this->addSql('ALTER TABLE claim_edges ALTER COLUMN relationship TYPE VARCHAR(20)');

        // ectd_mappings
        $this->addSql('ALTER TABLE ectd_mappings DROP CONSTRAINT IF EXISTS chk_ectd_confidence');
        $this->addSql('ALTER TABLE ectd_mappings DROP COLUMN IF EXISTS confidence');

        // claim_nodes audit + node_type
        $this->addSql('ALTER TABLE claim_nodes DROP COLUMN IF EXISTS review_reason');
        $this->addSql('ALTER TABLE claim_nodes DROP COLUMN IF EXISTS reviewed_by');
        $this->addSql('ALTER TABLE claim_nodes DROP COLUMN IF EXISTS reviewed_at');
        $this->addSql("UPDATE claim_nodes SET claim_type = 'empirical' WHERE claim_type IS NULL");
        $this->addSql('ALTER TABLE claim_nodes ALTER COLUMN claim_type SET NOT NULL');
        $this->addSql('ALTER TABLE claim_nodes DROP CONSTRAINT IF EXISTS chk_claim_node_type');
        $this->addSql('ALTER TABLE claim_nodes DROP COLUMN IF EXISTS node_type');

        // evidence_items
        $this->addSql('ALTER TABLE evidence_items DROP CONSTRAINT IF EXISTS chk_evidence_pass_fail');
        $this->addSql('ALTER TABLE evidence_items DROP COLUMN IF EXISTS pass_fail');
        $this->addSql('ALTER TABLE evidence_items DROP COLUMN IF EXISTS threshold');
        $this->addSql('ALTER TABLE evidence_items DROP COLUMN IF EXISTS metric_value');

        // context_of_use_cards
        $this->addSql('ALTER TABLE context_of_use_cards DROP CONSTRAINT IF EXISTS chk_cou_review_status');
        $this->addSql('ALTER TABLE context_of_use_cards DROP COLUMN IF EXISTS review_status');
    }
}
