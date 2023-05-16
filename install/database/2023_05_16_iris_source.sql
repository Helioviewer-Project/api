INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(88, 'IRIS SJI 1330', 'IRIS SJI 1330',  NULL, 1, 0, '', 0),
(89, 'IRIS SJI 2796', 'IRIS SJI 2796',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(88, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(88, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(88, 'Measurement', '1330', '1330', '1330 Ångström', 3),
(89, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(89, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(89, 'Measurement', '2796', '2796', '2796 Ångström', 3);
