UPDATE datasources SET description = 'PUNCH WFI+NFI CAM Mosaic' WHERE id = 131;

UPDATE datasource_property SET name = 'WFI+NFI', fitsName = 'WFI+NFI', description = 'Clear low-noise science mosaic, bkg-sub & resolved into B & uncertainty layer' WHERE sourceId = 131 AND label = 'Instrument';

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES (131, 'Measurement', 'Total Brightness', 'PUNCH Level-3 Intermediate F-corona Subtracted Unpolarized Mosaic', '', 3);

INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES (134, 'PUNCH', 'PUNCH WFI+NFI PAM Mosaic', NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(134, 'Observatory', 'PUNCH', 'PUNCH', 'PUNCH', 1),
(134, 'Instrument', 'WFI+NFI', 'WFI+NFI', 'Polarized low-noise science mosaic, bkg-sub & resolved into B, pB, & uncertainty layer', 2),
(134, 'Measurement', 'Polarized Brightness', 'PUNCH Level-3 Intermediate F-corona Subtracted Polarized Mosaic', '', 3);
