INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(95, 'RHESSI', 'RHESSI 25keV-50keV Back Projection',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(95, 'Observatory', 'RHESSI', 'RHESSI', 'RHESSI', 1),
(95, 'Energy Band', '25keV to 50keV', '25.0_50.0', '25 - 50', 2),
(95, 'Reconstruction', 'Back Projection', 'Back_Projection', 'Back Projection', 3);
