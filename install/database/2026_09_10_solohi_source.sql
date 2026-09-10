INSERT INTO datasources (
    id,
    name,
    description,
    units,
    layeringOrder,
    enabled,
    sourceIdGroup,
    displayOrder
)
VALUES
    (503, 'SoloHI', 'Solar Orbiter Heliospheric Imager',  NULL, 1, 0, '', 0, 0, 0, 0);

INSERT INTO datasource_property (
    sourceId,
    label,
    name,
    fitsName,
    description,
    uiOrder
)
VALUES
    (503, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
    (503, 'Instrument',  'SoloHI', 'SoloHI', 'SoloHI', 2),
    (503, 'Measurement', 'Difference Mosaic', 'Difference Mosaic', 'Difference Mosaic', 3);
