INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(132, 'GOES-19 CCOR-1', 'CCOR-1', NULL, 1, 0, '', 0),
(133, 'SWFO-L1 CCOR-2', 'CCOR-2',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(132, 'Observatory', 'GOES-19' ,'GOES-19', 'GOES Observatory',1),
(132, 'Instrument', 'CCOR-1', 'CCOR1', 'CCOR-1',2),
(132, 'Detector', 'CCOR-1', '1', 'CCOR-1',3),
(132, 'Measurement', 'white-light', 'white-light', 'White Light',4),

(133, 'Observatory', 'SWFO-L1', 'SWFO-L1', 'SWFO-L1 Observatory',1),
(133, 'Instrument', 'CCOR-2', 'CCOR2', 'CCOR-2',2),
(133, 'Detector', 'CCOR-2', '1', 'CCOR-2',2),
(133, 'Measurement', 'white-light', 'white-light', 'White Light',3);
