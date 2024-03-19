INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(95, 'SOLO PHI FDT BLOS', 'SOLO PHI FDT BLOS', NULL, 1, 0, '', 0),
(96, 'SOLO PHI FDT ICNT', 'SOLO PHI FDT ICNT', NULL, 1, 0, '', 0),
(97, 'SOLO PHI HRT BLOS', 'SOLO PHI HRT BLOS', NULL, 1, 0, '', 0),
(98, 'SOLO PHI HRT ICNT', 'SOLO PHI HRT ICNT', NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(95, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(96, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(97, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(98, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(95, 'Instrument', 'PHI', 'PHI', 'Polarimetric and Helioseismic Imager', 2),
(96, 'Instrument', 'PHI', 'PHI', 'Polarimetric and Helioseismic Imager', 2),
(97, 'Instrument', 'PHI', 'PHI', 'Polarimetric and Helioseismic Imager', 2),
(98, 'Instrument', 'PHI', 'PHI', 'Polarimetric and Helioseismic Imager', 2),
(95, 'Detector', 'FDT', 'FDT', 'Full Disk Telescope', 3),
(96, 'Detector', 'FDT', 'FDT', 'Full Disk Telescope', 3),
(97, 'Detector', 'HRT', 'HRT', 'High Resolution Telescope', 3),
(98, 'Detector', 'HRT', 'HRT', 'High Resolution Telescope', 3),
(95, 'Measurement', 'BLOS', 'BLOS', 'Line of Sight Magnetic Field', 4),
(96, 'Measurement', 'ICNT', 'ICNT', 'Intensity', 4),
(97, 'Measurement', 'BLOS', 'BLOS', 'Line of Sight Magnetic Field', 4),
(98, 'Measurement', 'ICNT', 'ICNT', 'Intensity', 4);