<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NAM-CORE v0.1 canonical schema: biological system, donor, cell source,
 * platform, device, assay, sample, exposure, endpoint measurement, QC result,
 * ontology term + mapping, provenance activity, raw data file, analysis script,
 * and the append-only audit log. Additive only — existing tables are untouched.
 */
final class Version20260701000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'NAM-CORE v0.1 canonical schema (endpoint measurements, ontology, provenance, audit log)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE namcore_exposure (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, test_article VARCHAR(255) NOT NULL, test_article_ontology_iri VARCHAR(255) DEFAULT NULL, concentration_value DOUBLE PRECISION DEFAULT NULL, concentration_unit VARCHAR(60) DEFAULT NULL, concentration_unit_ontology_iri VARCHAR(255) DEFAULT NULL, timepoint_value DOUBLE PRECISION DEFAULT NULL, timepoint_unit VARCHAR(60) DEFAULT NULL, vehicle VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_BCA348EC166D1F9C ON namcore_exposure (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_exposure.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_exposure.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_exposure.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_exposure.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_donor (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, donor_code VARCHAR(255) NOT NULL, species_label VARCHAR(255) DEFAULT NULL, species_ontology_iri VARCHAR(255) DEFAULT NULL, sex VARCHAR(20) DEFAULT NULL, age_value DOUBLE PRECISION DEFAULT NULL, age_unit VARCHAR(40) DEFAULT NULL, passage_number VARCHAR(40) DEFAULT NULL, health_status TEXT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_B12F6235166D1F9C ON namcore_donor (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_donor.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_donor.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_donor.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_donor.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_cell_source (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, donor_id CHAR(36) DEFAULT NULL, source_type VARCHAR(60) NOT NULL, vendor VARCHAR(255) DEFAULT NULL, catalog_number VARCHAR(255) DEFAULT NULL, lot_number VARCHAR(255) DEFAULT NULL, cell_type_label VARCHAR(255) DEFAULT NULL, cell_type_ontology_iri VARCHAR(255) DEFAULT NULL, differentiation_protocol TEXT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_E408147C166D1F9C ON namcore_cell_source (project_id);');
        $this->addSql('CREATE INDEX IDX_E408147C3DD7B7A7 ON namcore_cell_source (donor_id);');
        $this->addSql('COMMENT ON COLUMN namcore_cell_source.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_cell_source.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_cell_source.donor_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_cell_source.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_cell_source.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_ontology_mapping (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, ontology_term_id CHAR(36) DEFAULT NULL, source_entity_type VARCHAR(60) NOT NULL, source_entity_id VARCHAR(120) DEFAULT NULL, source_field VARCHAR(120) DEFAULT NULL, source_value VARCHAR(255) NOT NULL, mapping_confidence DOUBLE PRECISION NOT NULL, mapping_status VARCHAR(20) NOT NULL, mandatory BOOLEAN NOT NULL, reviewer_note TEXT DEFAULT NULL, reviewed_by VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_E6AB2B13B014ED53 ON namcore_ontology_mapping (ontology_term_id);');
        $this->addSql('CREATE INDEX idx_ontmap_project ON namcore_ontology_mapping (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_mapping.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_mapping.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_mapping.ontology_term_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_mapping.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_mapping.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_analysis_script (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, repository_url VARCHAR(500) DEFAULT NULL, reference VARCHAR(255) DEFAULT NULL, language VARCHAR(60) DEFAULT NULL, script_version VARCHAR(60) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_D369436A166D1F9C ON namcore_analysis_script (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_analysis_script.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_analysis_script.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_analysis_script.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_analysis_script.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_qc_result (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, assay_id CHAR(36) DEFAULT NULL, metric_name VARCHAR(255) NOT NULL, metric_value DOUBLE PRECISION DEFAULT NULL, threshold VARCHAR(120) DEFAULT NULL, pass_fail VARCHAR(20) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_646CA1B1166D1F9C ON namcore_qc_result (project_id);');
        $this->addSql('CREATE INDEX IDX_646CA1B1230675C ON namcore_qc_result (assay_id);');
        $this->addSql('COMMENT ON COLUMN namcore_qc_result.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_qc_result.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_qc_result.assay_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_qc_result.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_qc_result.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_ontology_term (id CHAR(36) NOT NULL, label VARCHAR(255) NOT NULL, ontology_prefix VARCHAR(40) NOT NULL, iri VARCHAR(500) DEFAULT NULL, curie VARCHAR(120) NOT NULL, definition TEXT DEFAULT NULL, synonyms JSON NOT NULL, source VARCHAR(120) DEFAULT NULL, term_version VARCHAR(60) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX uq_ontology_curie ON namcore_ontology_term (curie);');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_term.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_ontology_term.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_biological_system (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, cell_source_id CHAR(36) DEFAULT NULL, model_system_type VARCHAR(60) NOT NULL, species_label VARCHAR(255) DEFAULT NULL, species_ontology_iri VARCHAR(255) DEFAULT NULL, anatomy_label VARCHAR(255) DEFAULT NULL, anatomy_ontology_iri VARCHAR(255) DEFAULT NULL, cell_type_label VARCHAR(255) DEFAULT NULL, cell_type_ontology_iri VARCHAR(255) DEFAULT NULL, differentiation_protocol TEXT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_F0775956166D1F9C ON namcore_biological_system (project_id);');
        $this->addSql('CREATE INDEX IDX_F07759567E674A04 ON namcore_biological_system (cell_source_id);');
        $this->addSql('COMMENT ON COLUMN namcore_biological_system.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_biological_system.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_biological_system.cell_source_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_biological_system.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_biological_system.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_raw_data_file (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, file_name VARCHAR(255) NOT NULL, checksum VARCHAR(255) DEFAULT NULL, checksum_algorithm VARCHAR(40) DEFAULT NULL, upload_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_system VARCHAR(60) DEFAULT NULL, instrument_version VARCHAR(255) DEFAULT NULL, media_type VARCHAR(120) DEFAULT NULL, byte_size BIGINT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_206C999B166D1F9C ON namcore_raw_data_file (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_raw_data_file.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_raw_data_file.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_raw_data_file.upload_date IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_raw_data_file.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_raw_data_file.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_platform (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, platform_type VARCHAR(60) NOT NULL, vendor VARCHAR(255) DEFAULT NULL, model VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_BC7EB1EA166D1F9C ON namcore_platform (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_platform.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_platform.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_platform.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_platform.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_sample (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, biological_system_id CHAR(36) DEFAULT NULL, donor_id CHAR(36) DEFAULT NULL, sample_code VARCHAR(255) NOT NULL, batch_id VARCHAR(255) DEFAULT NULL, replicate_id VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_C9B56925166D1F9C ON namcore_sample (project_id);');
        $this->addSql('CREATE INDEX IDX_C9B56925C005ED71 ON namcore_sample (biological_system_id);');
        $this->addSql('CREATE INDEX IDX_C9B569253DD7B7A7 ON namcore_sample (donor_id);');
        $this->addSql('COMMENT ON COLUMN namcore_sample.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_sample.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_sample.biological_system_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_sample.donor_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_sample.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_sample.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_assay (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, nam_study_id CHAR(36) DEFAULT NULL, assay_type VARCHAR(80) NOT NULL, method TEXT DEFAULT NULL, readout VARCHAR(255) DEFAULT NULL, technology_label VARCHAR(255) DEFAULT NULL, technology_ontology_iri VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_7EAFC147166D1F9C ON namcore_assay (project_id);');
        $this->addSql('CREATE INDEX IDX_7EAFC147208CED66 ON namcore_assay (nam_study_id);');
        $this->addSql('COMMENT ON COLUMN namcore_assay.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_assay.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_assay.nam_study_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_assay.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_assay.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_provenance_activity (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, analysis_script_id CHAR(36) DEFAULT NULL, activity_type VARCHAR(80) NOT NULL, software_name VARCHAR(255) DEFAULT NULL, software_version VARCHAR(255) DEFAULT NULL, script_reference TEXT DEFAULT NULL, agent_name VARCHAR(255) DEFAULT NULL, agent_role VARCHAR(255) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_EF7F1671166D1F9C ON namcore_provenance_activity (project_id);');
        $this->addSql('CREATE INDEX IDX_EF7F1671534B016A ON namcore_provenance_activity (analysis_script_id);');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.analysis_script_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.started_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.ended_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_provenance_activity.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_device (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, platform_id CHAR(36) DEFAULT NULL, device_type VARCHAR(60) NOT NULL, vendor VARCHAR(255) DEFAULT NULL, model VARCHAR(255) DEFAULT NULL, serial_number VARCHAR(255) DEFAULT NULL, firmware_version VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_3191A968166D1F9C ON namcore_device (project_id);');
        $this->addSql('CREATE INDEX IDX_3191A968FFE6496F ON namcore_device (platform_id);');
        $this->addSql('COMMENT ON COLUMN namcore_device.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_device.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_device.platform_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_device.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_device.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_endpoint_measurement (id CHAR(36) NOT NULL, project_id CHAR(36) NOT NULL, study_id CHAR(36) DEFAULT NULL, assay_id CHAR(36) DEFAULT NULL, sample_id CHAR(36) DEFAULT NULL, biological_system_id CHAR(36) DEFAULT NULL, exposure_id CHAR(36) DEFAULT NULL, donor_id CHAR(36) DEFAULT NULL, device_id CHAR(36) DEFAULT NULL, raw_data_file_id CHAR(36) DEFAULT NULL, analysis_activity_id CHAR(36) DEFAULT NULL, endpoint_id VARCHAR(120) NOT NULL, endpoint_label VARCHAR(255) NOT NULL, endpoint_ontology_iri VARCHAR(255) DEFAULT NULL, value DOUBLE PRECISION DEFAULT NULL, value_raw VARCHAR(255) DEFAULT NULL, unit VARCHAR(60) DEFAULT NULL, unit_ontology_iri VARCHAR(255) DEFAULT NULL, timepoint_value DOUBLE PRECISION DEFAULT NULL, timepoint_unit VARCHAR(60) DEFAULT NULL, replicate_id VARCHAR(120) DEFAULT NULL, batch_id VARCHAR(120) DEFAULT NULL, qc_status VARCHAR(20) NOT NULL, exclusion_status VARCHAR(20) NOT NULL, exclusion_reason TEXT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, validation_status VARCHAR(20) NOT NULL, extensions JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_A074463BE7B003E9 ON namcore_endpoint_measurement (study_id);');
        $this->addSql('CREATE INDEX IDX_A074463B230675C ON namcore_endpoint_measurement (assay_id);');
        $this->addSql('CREATE INDEX IDX_A074463B1B1FEA20 ON namcore_endpoint_measurement (sample_id);');
        $this->addSql('CREATE INDEX IDX_A074463BC005ED71 ON namcore_endpoint_measurement (biological_system_id);');
        $this->addSql('CREATE INDEX IDX_A074463BC677C140 ON namcore_endpoint_measurement (exposure_id);');
        $this->addSql('CREATE INDEX IDX_A074463B3DD7B7A7 ON namcore_endpoint_measurement (donor_id);');
        $this->addSql('CREATE INDEX IDX_A074463B94A4C7D4 ON namcore_endpoint_measurement (device_id);');
        $this->addSql('CREATE INDEX IDX_A074463B62F55117 ON namcore_endpoint_measurement (raw_data_file_id);');
        $this->addSql('CREATE INDEX IDX_A074463BC002E4A4 ON namcore_endpoint_measurement (analysis_activity_id);');
        $this->addSql('CREATE INDEX idx_epm_project ON namcore_endpoint_measurement (project_id);');
        $this->addSql('CREATE INDEX idx_epm_endpoint ON namcore_endpoint_measurement (endpoint_id);');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.study_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.assay_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.sample_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.biological_system_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.exposure_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.donor_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.device_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.raw_data_file_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.analysis_activity_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('COMMENT ON COLUMN namcore_endpoint_measurement.updated_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('CREATE TABLE namcore_audit_log (id CHAR(36) NOT NULL, project_id CHAR(36) DEFAULT NULL, entity_type VARCHAR(120) NOT NULL, entity_id VARCHAR(120) DEFAULT NULL, action VARCHAR(40) NOT NULL, old_value JSON DEFAULT NULL, new_value JSON DEFAULT NULL, user_or_role VARCHAR(120) NOT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX idx_audit_project ON namcore_audit_log (project_id);');
        $this->addSql('COMMENT ON COLUMN namcore_audit_log.id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_audit_log.project_id IS \'(DC2Type:ulid)\';');
        $this->addSql('COMMENT ON COLUMN namcore_audit_log.created_at IS \'(DC2Type:datetime_immutable)\';');
        $this->addSql('ALTER TABLE namcore_exposure ADD CONSTRAINT FK_BCA348EC166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_donor ADD CONSTRAINT FK_B12F6235166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT FK_E408147C166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_cell_source ADD CONSTRAINT FK_E408147C3DD7B7A7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT FK_E6AB2B13166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_ontology_mapping ADD CONSTRAINT FK_E6AB2B13B014ED53 FOREIGN KEY (ontology_term_id) REFERENCES namcore_ontology_term (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_analysis_script ADD CONSTRAINT FK_D369436A166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT FK_646CA1B1166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_qc_result ADD CONSTRAINT FK_646CA1B1230675C FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT FK_F0775956166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_biological_system ADD CONSTRAINT FK_F07759567E674A04 FOREIGN KEY (cell_source_id) REFERENCES namcore_cell_source (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_raw_data_file ADD CONSTRAINT FK_206C999B166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_platform ADD CONSTRAINT FK_BC7EB1EA166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT FK_C9B56925166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT FK_C9B56925C005ED71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_sample ADD CONSTRAINT FK_C9B569253DD7B7A7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT FK_7EAFC147166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_assay ADD CONSTRAINT FK_7EAFC147208CED66 FOREIGN KEY (nam_study_id) REFERENCES nam_studies (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT FK_EF7F1671166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_provenance_activity ADD CONSTRAINT FK_EF7F1671534B016A FOREIGN KEY (analysis_script_id) REFERENCES namcore_analysis_script (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT FK_3191A968166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_device ADD CONSTRAINT FK_3191A968FFE6496F FOREIGN KEY (platform_id) REFERENCES namcore_platform (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463BE7B003E9 FOREIGN KEY (study_id) REFERENCES nam_studies (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B230675C FOREIGN KEY (assay_id) REFERENCES namcore_assay (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B1B1FEA20 FOREIGN KEY (sample_id) REFERENCES namcore_sample (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463BC005ED71 FOREIGN KEY (biological_system_id) REFERENCES namcore_biological_system (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463BC677C140 FOREIGN KEY (exposure_id) REFERENCES namcore_exposure (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B3DD7B7A7 FOREIGN KEY (donor_id) REFERENCES namcore_donor (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B94A4C7D4 FOREIGN KEY (device_id) REFERENCES namcore_device (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463B62F55117 FOREIGN KEY (raw_data_file_id) REFERENCES namcore_raw_data_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_endpoint_measurement ADD CONSTRAINT FK_A074463BC002E4A4 FOREIGN KEY (analysis_activity_id) REFERENCES namcore_provenance_activity (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE namcore_audit_log ADD CONSTRAINT FK_BA0DFCCA166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS namcore_audit_log CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_endpoint_measurement CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_device CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_provenance_activity CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_assay CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_sample CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_platform CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_raw_data_file CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_biological_system CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_ontology_term CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_qc_result CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_analysis_script CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_ontology_mapping CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_cell_source CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_donor CASCADE');
        $this->addSql('DROP TABLE IF EXISTS namcore_exposure CASCADE');
    }
}
