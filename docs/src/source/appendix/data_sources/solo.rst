Solar Orbiter
-------------

.. table:: Solar Orbiter Data Sources

    +-----------+----------------+----------------+------------+-----------+---------------------+
    | Source ID | Description    | Observatory    | Instrument | Measurement| Line                |
    +===========+================+================+============+===========+=====================+
    | 84        | EUI FSI 174    | Solar Orbiter  | EUI        | 174       |                     |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 85        | EUI FSI 304    | Solar Orbiter  | EUI        | 304       |                     |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 86        | EUI HRI 174    | Solar Orbiter  | EUI        | 174       |                     |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 87        | EUI HRI 1216   | Solar Orbiter  | EUI        | 1216      |                     |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 601       | SPICE 77.04    | Solar Orbiter  | SPICE      | intensity | Ne VIII 77.04 nm    |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 602       | SPICE 103.19   | Solar Orbiter  | SPICE      | intensity | O VI 103.19 nm      |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 603       | SPICE 70.38    | Solar Orbiter  | SPICE      | intensity | O III 70.38 nm      |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 604       | SPICE 78.77    | Solar Orbiter  | SPICE      | intensity | O IV 78.77 nm       |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 605       | SPICE 70.6     | Solar Orbiter  | SPICE      | intensity | Mg IX 70.60 nm      |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 606       | SPICE 97.25    | Solar Orbiter  | SPICE      | intensity | H Ly gamma 97.25 nm |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 607       | SPICE 76.51    | Solar Orbiter  | SPICE      | intensity | N IV 76.51 nm       |
    +-----------+----------------+----------------+------------+-----------+---------------------+
    | 608       | SPICE 97.7     | Solar Orbiter  | SPICE      | intensity | C III 97.70 nm      |
    +-----------+----------------+----------------+------------+-----------+---------------------+

SPICE Import
^^^^^^^^^^^^

SPICE JP2 files are retrieved directly from the IAS Helioviewer archive:

    https://helioviewer.ias.u-psud.fr/jp2/SPICE/

The downloader scans the SPICE measurement directories for each requested date.
The remote archive follows the layout ``YYYY/MM/DD/<measurement>``.

The currently supported measurement directories are:

- ``774`` (Ne VIII 77.04 nm)
- ``103.19`` (O VI 103.19 nm)
- ``70.38`` (O III 70.38 nm)
- ``78.77`` (O IV 78.77 nm)
- ``70.6`` (Mg IX 70.60 nm)
- ``97.25`` (H Ly gamma 97.25 nm)
- ``76.51`` (N IV 76.51 nm)
- ``97.7`` (C III 97.70 nm)

SPICE observation times are extracted from the acquisition timestamp embedded
in the JP2 filename, using the ``YYYYMMDDTHHMMSS`` format. For example::

    solo_L4_spice-n-ras_20260101T061925_369098771-000-DR5_7_0.jp2

The SPICE datasource is polled every 60 minutes.

Example::

    python install/downloader.py -d spice -b http -m urllib \
        -s "2026-01-01 00:00:00" -e "2026-01-01 23:59:59"
