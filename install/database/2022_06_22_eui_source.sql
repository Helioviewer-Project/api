INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(84, 'FSI 174', 'Solar Orbiter EUI FSI 174',  NULL, 1, 1, '', 0),
(85, 'FSI 304', 'Solar Orbiter EUI FSI 304',  NULL, 1, 1, '', 0),
(86, 'HRI 174', 'Solar Orbiter EUI HRI 174',  NULL, 1, 1, '', 0),
(87, 'HRI LYA', 'Solar Orbiter EUI HRI LYA',  NULL, 1, 1, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(84, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(84, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(84, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(84, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(85, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(85, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(85, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(85, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4),
(86, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(86, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(86, 'Detector', 'HRI_EUV', 'HRI_EUV', 'High Resolution Imager Extreme Ultraviolet', 3),
(86, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(87, 'Observatory', 'Solar_Orbiter', 'Solar_Orbiter', 'Solar Orbiter', 1),
(87, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(87, 'Detector', 'HRI_LYA', 'HRI_LYA', 'High Resolution Imager Lyman-A', 3),
(87, 'Measurement', '1216', '1216', '1216 Ångström', 4);
