INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(88, 'IRIS SJI 1330', 'IRIS SJI 1330',  NULL, 1, 0, '', 0),
(89, 'IRIS SJI 2796', 'IRIS SJI 2796',  NULL, 1, 0, '', 0),
(90, 'IRIS SJI 1400', 'IRIS SJI 1400',  NULL, 1, 0, '', 0),
(91, 'IRIS SJI 1600', 'IRIS SJI 1600',  NULL, 1, 0, '', 0),
(92, 'IRIS SJI 2832', 'IRIS SJI 2832',  NULL, 1, 0, '', 0),
(93, 'IRIS SJI 5000', 'IRIS SJI 5000',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(88, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(88, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(88, 'Measurement', '1330', '1330', '1330 Ångström', 3),
(89, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(89, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(89, 'Measurement', '2796', '2796', '2796 Ångström', 3),
(90, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(90, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(90, 'Measurement', '1400', '1400', '1400 Ångström', 3),
(91, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(91, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(91, 'Measurement', '1600', '1600', '1600 Ångström', 3),
(92, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(92, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(92, 'Measurement', '2832', '2832', '2832 Ångström', 3),
(93, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(93, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(93, 'Measurement', '5000', '5000', '5000 Ångström', 3);

