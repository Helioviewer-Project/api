INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(2000, 'GOES-R SUVI 94', 'GOES-R SUVI 94',  NULL, 1, 0, '', 0),
(2001, 'GOES-R SUVI 131', 'GOES-R SUVI 131',  NULL, 1, 0, '', 0),
(2002, 'GOES-R SUVI 171', 'GOES-R SUVI 171',  NULL, 1, 0, '', 0),
(2003, 'GOES-R SUVI 195', 'GOES-R SUVI 195',  NULL, 1, 0, '', 0),
(2004, 'GOES-R SUVI 284', 'GOES-R SUVI 284',  NULL, 1, 0, '', 0),
(2005, 'GOES-R SUVI 304', 'GOES-R SUVI 304',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(2000, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2001, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2002, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2003, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2004, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2005, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2000, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2001, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2002, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2003, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2004, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2005, 'Detector', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2000, 'Measurement', '94', '94', '94 Ångström',    3),
(2001, 'Measurement', '131', '131', '131 Ångström', 3),
(2002, 'Measurement', '171', '171', '171 Ångström', 3),
(2003, 'Measurement', '195', '195', '195 Ångström', 3),
(2004, 'Measurement', '284', '284', '284 Ångström', 3),
(2005, 'Measurement', '304', '304', '304 Ångström', 3);
