<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add ON DELETE CASCADE to every NAM-CORE foreign key so deleting a Project (or
 * reloading the demo) cascades cleanly through the NAM-CORE subgraph instead of
 * raising foreign-key violations. Cross-entity NAM-CORE references cascade too,
 * so the whole per-project graph is removed atomically.
 */
final class Version20260701000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ON DELETE CASCADE for NAM-CORE foreign keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE namcore_analysis_script DROP CONSTRAINT fk_d369436a166d1f9c');
        $this->addSql('ALTER TABLE namcore_analysis_script ADD CONSTRAINT fk_d369436a166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_assay DROP CONSTRAINT fk_7eafc147208ced66');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT fk_7eafc147208ced66 FOREIGN KEY (nam_study_id) REFERENCES nam_studies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_assay DROP CONSTRAINT fk_7eafc147166d1f9c');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT fk_7eafc147166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_audit_log DROP CONSTRAINT fk_ba0dfcca166d1f9c');
        $this->addSql('ALTER TABLE namcore_audit_log ADD CONSTRAINT fk_ba0dfcca166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_biological_system DROP CONSTRAINT fk_f07759567e674a04');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT fk_f07759567e674a04 FOREIGN KEY (cell_source_id) REFERENCES namcore_cell_source (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_biological_system DROP CONSTRAINT fk_f0775956166d1f9c');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT fk_f0775956166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_cell_source DROP CONSTRAINT fk_e408147c166d1f9c');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT fk_e408147c166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_cell_source DROP CONSTRAINT fk_e408147c3dd7b7a7');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT fk_e408147c3dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_device DROP CONSTRAINT fk_3191a968166d1f9c');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT fk_3191a968166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_device DROP CONSTRAINT fk_3191a968ffe6496f');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT fk_3191a968ffe6496f FOREIGN KEY (platform_id) REFERENCES namcore_platform (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_donor DROP CONSTRAINT fk_b12f6235166d1f9c');
        $this->addSql('ALTER TABLE namcore_donor ADD CONSTRAINT fk_b12f6235166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463be7b003e9');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463be7b003e9 FOREIGN KEY (study_id) REFERENCES nam_studies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b166d1f9c');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc002e4a4');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc002e4a4 FOREIGN KEY (analysis_activity_id) REFERENCES namcore_provenance_activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b62f55117');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b62f55117 FOREIGN KEY (raw_data_file_id) REFERENCES namcore_raw_data_file (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc005ed71');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc005ed71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b1b1fea20');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b1b1fea20 FOREIGN KEY (sample_id) REFERENCES namcore_sample (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b230675c');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b230675c FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b94a4c7d4');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b94a4c7d4 FOREIGN KEY (device_id) REFERENCES namcore_device (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b3dd7b7a7');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b3dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc677c140');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc677c140 FOREIGN KEY (exposure_id) REFERENCES namcore_exposure (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_exposure DROP CONSTRAINT fk_bca348ec166d1f9c');
        $this->addSql('ALTER TABLE namcore_exposure ADD CONSTRAINT fk_bca348ec166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_ontology_mapping DROP CONSTRAINT fk_e6ab2b13b014ed53');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT fk_e6ab2b13b014ed53 FOREIGN KEY (ontology_term_id) REFERENCES namcore_ontology_term (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_ontology_mapping DROP CONSTRAINT fk_e6ab2b13166d1f9c');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT fk_e6ab2b13166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_platform DROP CONSTRAINT fk_bc7eb1ea166d1f9c');
        $this->addSql('ALTER TABLE namcore_platform ADD CONSTRAINT fk_bc7eb1ea166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_provenance_activity DROP CONSTRAINT fk_ef7f1671534b016a');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT fk_ef7f1671534b016a FOREIGN KEY (analysis_script_id) REFERENCES namcore_analysis_script (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_provenance_activity DROP CONSTRAINT fk_ef7f1671166d1f9c');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT fk_ef7f1671166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_qc_result DROP CONSTRAINT fk_646ca1b1230675c');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT fk_646ca1b1230675c FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_qc_result DROP CONSTRAINT fk_646ca1b1166d1f9c');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT fk_646ca1b1166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_raw_data_file DROP CONSTRAINT fk_206c999b166d1f9c');
        $this->addSql('ALTER TABLE namcore_raw_data_file ADD CONSTRAINT fk_206c999b166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b56925c005ed71');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b56925c005ed71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b56925166d1f9c');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b56925166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b569253dd7b7a7');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b569253dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE namcore_analysis_script DROP CONSTRAINT fk_d369436a166d1f9c');
        $this->addSql('ALTER TABLE namcore_analysis_script ADD CONSTRAINT fk_d369436a166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_assay DROP CONSTRAINT fk_7eafc147208ced66');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT fk_7eafc147208ced66 FOREIGN KEY (nam_study_id) REFERENCES nam_studies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_assay DROP CONSTRAINT fk_7eafc147166d1f9c');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT fk_7eafc147166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_audit_log DROP CONSTRAINT fk_ba0dfcca166d1f9c');
        $this->addSql('ALTER TABLE namcore_audit_log ADD CONSTRAINT fk_ba0dfcca166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_biological_system DROP CONSTRAINT fk_f07759567e674a04');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT fk_f07759567e674a04 FOREIGN KEY (cell_source_id) REFERENCES namcore_cell_source (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_biological_system DROP CONSTRAINT fk_f0775956166d1f9c');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT fk_f0775956166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_cell_source DROP CONSTRAINT fk_e408147c166d1f9c');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT fk_e408147c166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_cell_source DROP CONSTRAINT fk_e408147c3dd7b7a7');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT fk_e408147c3dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_device DROP CONSTRAINT fk_3191a968166d1f9c');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT fk_3191a968166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_device DROP CONSTRAINT fk_3191a968ffe6496f');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT fk_3191a968ffe6496f FOREIGN KEY (platform_id) REFERENCES namcore_platform (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_donor DROP CONSTRAINT fk_b12f6235166d1f9c');
        $this->addSql('ALTER TABLE namcore_donor ADD CONSTRAINT fk_b12f6235166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463be7b003e9');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463be7b003e9 FOREIGN KEY (study_id) REFERENCES nam_studies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b166d1f9c');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc002e4a4');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc002e4a4 FOREIGN KEY (analysis_activity_id) REFERENCES namcore_provenance_activity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b62f55117');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b62f55117 FOREIGN KEY (raw_data_file_id) REFERENCES namcore_raw_data_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc005ed71');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc005ed71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b1b1fea20');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b1b1fea20 FOREIGN KEY (sample_id) REFERENCES namcore_sample (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b230675c');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b230675c FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b94a4c7d4');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b94a4c7d4 FOREIGN KEY (device_id) REFERENCES namcore_device (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463b3dd7b7a7');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463b3dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement DROP CONSTRAINT fk_a074463bc677c140');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT fk_a074463bc677c140 FOREIGN KEY (exposure_id) REFERENCES namcore_exposure (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_exposure DROP CONSTRAINT fk_bca348ec166d1f9c');
        $this->addSql('ALTER TABLE namcore_exposure ADD CONSTRAINT fk_bca348ec166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_ontology_mapping DROP CONSTRAINT fk_e6ab2b13b014ed53');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT fk_e6ab2b13b014ed53 FOREIGN KEY (ontology_term_id) REFERENCES namcore_ontology_term (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_ontology_mapping DROP CONSTRAINT fk_e6ab2b13166d1f9c');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT fk_e6ab2b13166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_platform DROP CONSTRAINT fk_bc7eb1ea166d1f9c');
        $this->addSql('ALTER TABLE namcore_platform ADD CONSTRAINT fk_bc7eb1ea166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_provenance_activity DROP CONSTRAINT fk_ef7f1671534b016a');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT fk_ef7f1671534b016a FOREIGN KEY (analysis_script_id) REFERENCES namcore_analysis_script (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_provenance_activity DROP CONSTRAINT fk_ef7f1671166d1f9c');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT fk_ef7f1671166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_qc_result DROP CONSTRAINT fk_646ca1b1230675c');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT fk_646ca1b1230675c FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_qc_result DROP CONSTRAINT fk_646ca1b1166d1f9c');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT fk_646ca1b1166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_raw_data_file DROP CONSTRAINT fk_206c999b166d1f9c');
        $this->addSql('ALTER TABLE namcore_raw_data_file ADD CONSTRAINT fk_206c999b166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b56925c005ed71');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b56925c005ed71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b56925166d1f9c');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b56925166d1f9c FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE namcore_sample DROP CONSTRAINT fk_c9b569253dd7b7a7');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT fk_c9b569253dd7b7a7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
