INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(95,  'RHESSI', 'RHESSI 3keV-6keV Back Projection',      NULL, 1, 0, '', 0),
(96,  'RHESSI', 'RHESSI 6keV-12keV Back Projection',     NULL, 1, 0, '', 0),
(97,  'RHESSI', 'RHESSI 12keV-25keV Back Projection',    NULL, 1, 0, '', 0),
(98,  'RHESSI', 'RHESSI 25keV-50keV Back Projection',    NULL, 1, 0, '', 0),
(99,  'RHESSI', 'RHESSI 50keV-100keV Back Projection',   NULL, 1, 0, '', 0),
(100, 'RHESSI', 'RHESSI 100keV-300keV Back Projection',  NULL, 1, 0, '', 0),

(101, 'RHESSI', 'RHESSI 3keV-6keV Clean',      NULL, 1, 0, '', 0),
(102, 'RHESSI', 'RHESSI 6keV-12keV Clean',     NULL, 1, 0, '', 0),
(103, 'RHESSI', 'RHESSI 12keV-25keV Clean',    NULL, 1, 0, '', 0),
(104, 'RHESSI', 'RHESSI 25keV-50keV Clean',    NULL, 1, 0, '', 0),
(105, 'RHESSI', 'RHESSI 50keV-100keV Clean',   NULL, 1, 0, '', 0),
(106, 'RHESSI', 'RHESSI 100keV-300keV Clean',  NULL, 1, 0, '', 0),

(107, 'RHESSI', 'RHESSI 3keV-6keV Clean 59',      NULL, 1, 0, '', 0),
(108, 'RHESSI', 'RHESSI 6keV-12keV Clean 59',     NULL, 1, 0, '', 0),
(109, 'RHESSI', 'RHESSI 12keV-25keV Clean 59',    NULL, 1, 0, '', 0),
(110, 'RHESSI', 'RHESSI 25keV-50keV Clean 59',    NULL, 1, 0, '', 0),
(111, 'RHESSI', 'RHESSI 50keV-100keV Clean 59',   NULL, 1, 0, '', 0),
(112, 'RHESSI', 'RHESSI 100keV-300keV Clean 59',  NULL, 1, 0, '', 0),

(113, 'RHESSI', 'RHESSI 3keV-6keV MEM_GE',      NULL, 1, 0, '', 0),
(114, 'RHESSI', 'RHESSI 6keV-12keV MEM_GE',     NULL, 1, 0, '', 0),
(115, 'RHESSI', 'RHESSI 12keV-25keV MEM_GE',    NULL, 1, 0, '', 0),
(116, 'RHESSI', 'RHESSI 25keV-50keV MEM_GE',    NULL, 1, 0, '', 0),
(117, 'RHESSI', 'RHESSI 50keV-100keV MEM_GE',   NULL, 1, 0, '', 0),
(118, 'RHESSI', 'RHESSI 100keV-300keV MEM_GE',  NULL, 1, 0, '', 0),

(119, 'RHESSI', 'RHESSI 3keV-6keV VIS CS',      NULL, 1, 0, '', 0),
(120, 'RHESSI', 'RHESSI 6keV-12keV VIS CS',     NULL, 1, 0, '', 0),
(121, 'RHESSI', 'RHESSI 12keV-25keV VIS CS',    NULL, 1, 0, '', 0),
(122, 'RHESSI', 'RHESSI 25keV-50keV VIS CS',    NULL, 1, 0, '', 0),
(123, 'RHESSI', 'RHESSI 50keV-100keV VIS CS',   NULL, 1, 0, '', 0),
(124, 'RHESSI', 'RHESSI 100keV-300keV VIS CS',  NULL, 1, 0, '', 0),

(125, 'RHESSI', 'RHESSI 3keV-6keV VIS FWDFIT',      NULL, 1, 0, '', 0),
(126, 'RHESSI', 'RHESSI 6keV-12keV VIS FWDFIT',     NULL, 1, 0, '', 0),
(127, 'RHESSI', 'RHESSI 12keV-25keV VIS FWDFIT',    NULL, 1, 0, '', 0),
(128, 'RHESSI', 'RHESSI 25keV-50keV VIS FWDFIT',    NULL, 1, 0, '', 0),
(129, 'RHESSI', 'RHESSI 50keV-100keV VIS FWDFIT',   NULL, 1, 0, '', 0),
(130, 'RHESSI', 'RHESSI 100keV-300keV VIS FWDFIT',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(95, 'Observatory',     'RHESSI',           'RHESSI',          'RHESSI'         , 1),
(95, 'Energy Band',     '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV'   , 2),
(95, 'Reconstruction',  'Back Projection',  'Back_Projection', 'Back Projection', 3),

(96, 'Observatory',     'RHESSI',           'RHESSI',          'RHESSI',          1),
(96, 'Energy Band',     '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(96, 'Reconstruction',  'Back Projection',  'Back_Projection', 'Back Projection', 3),

(97, 'Observatory',     'RHESSI',           'RHESSI',          'RHESSI',          1),
(97, 'Energy Band',     '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(97, 'Reconstruction',  'Back Projection',  'Back_Projection', 'Back Projection', 3),

(98, 'Observatory',     'RHESSI',           'RHESSI',          'RHESSI',          1),
(98, 'Energy Band',     '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(98, 'Reconstruction',  'Back Projection',  'Back_Projection', 'Back Projection', 3),

(99, 'Observatory',     'RHESSI',           'RHESSI',          'RHESSI',          1),
(99, 'Energy Band',     '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(99, 'Reconstruction',  'Back Projection',  'Back_Projection', 'Back Projection', 3),

(100, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(100, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(100, 'Reconstruction', 'Back Projection',  'Back_Projection', 'Back Projection', 3),

(101, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(101, 'Energy Band',    '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV',    2),
(101, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(102, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(102, 'Energy Band',    '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(102, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(103, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(103, 'Energy Band',    '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(103, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(104, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(104, 'Energy Band',    '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(104, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(105, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(105, 'Energy Band',    '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(105, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(106, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(106, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(106, 'Reconstruction', 'Clean',            'Clean',           'Clean',           3),

(107, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(107, 'Energy Band',    '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV',    2),
(107, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(108, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(108, 'Energy Band',    '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(108, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(109, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(109, 'Energy Band',    '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(109, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(110, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(110, 'Energy Band',    '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(110, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(111, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(111, 'Energy Band',    '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(111, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(112, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(112, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(112, 'Reconstruction', 'Clean 59',         'Clean59',         'Clean 59',        3),

(113, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(113, 'Energy Band',    '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV',    2),
(113, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(114, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(114, 'Energy Band',    '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(114, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(115, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(115, 'Energy Band',    '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(115, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(116, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(116, 'Energy Band',    '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(116, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(117, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(117, 'Energy Band',    '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(117, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(118, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(118, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(118, 'Reconstruction', 'MEM GE',           'MEM_GE',          'MEM GE',          3),

(119, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(119, 'Energy Band',    '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV',    2),
(119, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(120, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(120, 'Energy Band',    '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(120, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(121, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(121, 'Energy Band',    '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(121, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(122, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(122, 'Energy Band',    '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(122, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(123, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(123, 'Energy Band',    '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(123, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(124, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(124, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(124, 'Reconstruction', 'VIS CS',           'VIS_CS',          'VIS CS',          3),

(125, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(125, 'Energy Band',    '3keV to 6keV',     '3.0_6.0',         '3keV to 6keV',    2),
(125, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3),

(126, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(126, 'Energy Band',    '6keV to 12keV',    '6.0_12.0',        '6keV to 12keV',   2),
(126, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3),

(127, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(127, 'Energy Band',    '12keV to 25keV',   '12.0_25.0',       '12keV to 25keV',  2),
(127, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3),

(128, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(128, 'Energy Band',    '25keV to 50keV',   '25.0_50.0',       '25keV to 50keV',  2),
(128, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3),

(129, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(129, 'Energy Band',    '50keV to 100keV',  '50.0_100.0',      '50keV to 100keV', 2),
(129, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3),

(130, 'Observatory',    'RHESSI',           'RHESSI',          'RHESSI',          1),
(130, 'Energy Band',    '100keV to 300keV', '100.0_300.0',     '50keV to 100keV', 2),
(130, 'Reconstruction', 'VIS FWDFIT',       'VIS_FWDFIT',      'VIS FWDFIT',      3);
