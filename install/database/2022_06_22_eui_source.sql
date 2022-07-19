INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(84, 'EUI FSI 174', 'Solar Orbiter EUI FSI 174',  NULL, 1, 0, '', 0),
(85, 'EUI FSI 304', 'Solar Orbiter EUI FSI 304',  NULL, 1, 0, '', 0),
(86, 'EUI HRI 174', 'Solar Orbiter EUI HRI 174',  NULL, 1, 0, '', 0),
(87, 'EUI HRI 1216', 'Solar Orbiter EUI HRI 1216',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(84, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(84, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(84, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(84, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(85, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(85, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(85, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(85, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4),
(86, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(86, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(86, 'Detector', 'HRI', 'HRI_EUV', 'High Resolution Imager Extreme Ultraviolet', 3),
(86, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(87, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(87, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(87, 'Detector', 'HRI', 'HRI_LYA', 'High Resolution Imager Lyman-a', 3),
(87, 'Measurement', '1216', '1216', '1216 Ångström', 4);

-- Refactoring fitsName requires fixing this discrepancy between fitsName and name
-- on the datasource_property table.
UPDATE datasource_property SET fitsName = 'white-light' WHERE fitsName = 'WL';
