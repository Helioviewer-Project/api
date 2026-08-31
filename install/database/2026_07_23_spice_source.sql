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
    (601, 'SPICE 77.04',  'Solar Orbiter SPICE 77.04',  'NM', 1, 0, '', 0),
    (602, 'SPICE 103.19', 'Solar Orbiter SPICE 103.19', 'NM', 1, 0, '', 0),
    (603, 'SPICE 70.38',  'Solar Orbiter SPICE 70.38',  'NM', 1, 0, '', 0),
    (604, 'SPICE 78.77',  'Solar Orbiter SPICE 78.77',  'NM', 1, 0, '', 0),
    (605, 'SPICE 70.6',   'Solar Orbiter SPICE 70.6',   'NM', 1, 0, '', 0),
    (606, 'SPICE 97.25',  'Solar Orbiter SPICE 97.25',  'NM', 1, 0, '', 0),
    (607, 'SPICE 76.51',  'Solar Orbiter SPICE 76.51',  'NM', 1, 0, '', 0),
    (608, 'SPICE 97.7',   'Solar Orbiter SPICE 97.7',   'NM', 1, 0, '', 0);

INSERT INTO datasource_property (
    sourceId,
    label,
    name,
    fitsName,
    description,
    uiOrder
)
VALUES
    (601, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (601, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (601, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (601, 'Line',        'Ne VIII 77.04 nm',    'Ne VIII 77.04 nm',    'Ne VIII 77.04 nm',    4),

    (602, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (602, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (602, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (602, 'Line',        'O VI 103.19 nm',      'O VI 103.19 nm',      'O VI 103.19 nm',      4),

    (603, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (603, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (603, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (603, 'Line',        'O III 70.38 nm',      'O III 70.38 nm',      'O III 70.38 nm',      4),

    (604, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (604, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (604, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (604, 'Line',        'O IV 78.77 nm',       'O IV 78.77 nm',       'O IV 78.77 nm',       4),

    (605, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (605, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (605, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (605, 'Line',        'Mg IX 70.60 nm',      'Mg IX 70.60 nm',      'Mg IX 70.60 nm',      4),

    (606, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (606, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (606, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (606, 'Line',        'H Ly gamma 97.25 nm', 'H Ly gamma 97.25 nm', 'H Ly gamma 97.25 nm', 4),

    (607, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (607, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (607, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (607, 'Line',        'N IV 76.51 nm',       'N IV 76.51 nm',       'N IV 76.51 nm',       4),

    (608, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter',                1),
    (608, 'Instrument',  'SPICE',               'SPICE',               'SPICE',               2),
    (608, 'Measurement', 'intensity',           'intensity',           'intensity',           3),
    (608, 'Line',        'C III 97.70 nm',      'C III 97.70 nm',      'C III 97.70 nm',      4);
