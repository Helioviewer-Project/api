INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(84, 'FSI 174', 'Solar Orbiter EUI FSI 174',  NULL, 1, 1, '', 0),
(85, 'FSI 304', 'Solar Orbiter EUI FSI 304',  NULL, 1, 1, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(84, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(84, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(84, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(84, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(85, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(85, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(85, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(85, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4);

