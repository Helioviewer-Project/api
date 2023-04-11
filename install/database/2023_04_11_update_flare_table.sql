-- Nuke the existing flare predictions tables.
DROP TABLE IF EXISTS flare_predictions;
DROP TABLE IF EXISTS flare_datasets;
CREATE TABLE flare_datasets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (name)
);

-- Only create columns for information that is required to query
-- We want to keep all metadata provided by the CCMC APIs, so we can't rely on a schema.
-- In theory, we could make the only columns be the id and json, but as the table grows, there may be a performance problems with querying json columns.
CREATE TABLE flare_predictions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id INT UNSIGNED NOT NULL,
    start_window DATETIME NOT NULL,
    end_window DATETIME NOT NULL,
    issue_time DATETIME NOT NULL,
    hpc_x FLOAT,
    hpc_y FLOAT,
    json_data JSON NOT NULL,
    sha256 VARCHAR(64) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (dataset_id) REFERENCES flare_datasets(id),
    UNIQUE KEY (sha256),
    INDEX `issue_time_idx` (`issue_time`)
);

INSERT INTO flare_datasets (id, name) VALUES
    ( 1, "SIDC_Operator_REGIONS"),
    ( 2, "BoM_flare1_REGIONS"),
    ( 3, "ASSA_1_REGIONS"),
    ( 4, "ASSA_24H_1_REGIONS"),
    ( 5, "AMOS_v1_REGIONS"),
    ( 6, "ASAP_1_REGIONS"),
    ( 7, "MAG4_LOS_FEr_REGIONS"),
    ( 8, "MAG4_LOS_r_REGIONS"),
    ( 9, "MAG4_SHARP_FE_REGIONS"),
    (10, "MAG4_SHARP_REGIONS"),
    (11, "MAG4_SHARP_HMI_REGIONS"),
    (12, "AEffort_REGIONS")
;