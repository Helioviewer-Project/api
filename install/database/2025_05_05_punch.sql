INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(131,  'PUNCH', 'PUNCH WFI+NFI', NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(131, 'Observatory', 'PUNCH', 'PUNCH', 'PUNCH', 1),
(131, 'Instrument', 'WFI+NFI', 'WFI+NFI', 'Wide and Near Field Imagers', 2),
(131, 'Measurement', '530', '530', '', 3);
