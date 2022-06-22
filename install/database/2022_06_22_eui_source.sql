INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(84, 'FSI 174', 'Solar Orbiter EUI FSI 174',  NULL, 1, 1, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(84, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(84, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(84, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(84, 'Measurement', '174', '174', '174 Ångströ extreme ultraviolet', 4);

