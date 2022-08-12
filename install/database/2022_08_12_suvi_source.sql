INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(88, 'GOES-R SUVI 94', 'GOES-R SUVI 94',  NULL, 1, 0, '', 0),
(89, 'GOES-R SUVI 131', 'GOES-R SUVI 131',  NULL, 1, 0, '', 0),
(90, 'GOES-R SUVI 171', 'GOES-R SUVI 171',  NULL, 1, 0, '', 0),
(91, 'GOES-R SUVI 195', 'GOES-R SUVI 195',  NULL, 1, 0, '', 0),
(92, 'GOES-R SUVI 284', 'GOES-R SUVI 284',  NULL, 1, 0, '', 0),
(93, 'GOES-R SUVI 304', 'GOES-R SUVI 304',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(88, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(89, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(90, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(91, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(92, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(93, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(88, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(89, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(90, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(91, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(92, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(93, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(88, 'Measurement', '94', '94', '94 Ångström',    3),
(89, 'Measurement', '131', '131', '131 Ångström', 3),
(90, 'Measurement', '171', '171', '171 Ångström', 3),
(91, 'Measurement', '195', '195', '195 Ångström', 3),
(92, 'Measurement', '284', '284', '284 Ångström', 3),
(93, 'Measurement', '304', '304', '304 Ångström', 3);
