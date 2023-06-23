INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(94, 'GONG H-alpha', 'GONG H-Alpha',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(94, 'Observatory', 'GONG', 'NSO-GONG', 'GONG', 1),
(94, 'Instrument', 'GONG', 'GONG', 'GONG', 2),
(94, 'Detector', 'H-alpha', 'H-alpha', 'H-alpha', 3),
(94, 'Measurement', '6562', '6562', 'H-alpha 6562 angstrom', 4);
