# -*- coding: utf-8 -*-
"""Helioviewer.org installer database functions"""
import sys
import os

def setup_database_schema(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql):
    """Sets up Helioviewer.org database schema"""
    if mysql:
        try:
            import mysql.connector as mysqld
            adaptor = mysqld
        except ImportError:
            try:
                import MySQLdb as mysqld
                adaptor = mysqld
            except ImportError:
                print(e)
                exit()
    else:
        import pgdb
        adaptor = pgdb

    print("Creating Database...")
    create_db(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql, adaptor)

    # connect to helioviewer database
    db, cursor = get_db_cursor(dbhost, dbname, dbuser, dbpass, mysql)

    print("Creating datasource table")
    create_datasource_table(cursor)
    print("Creating datasource property table")
    create_datasource_property_table(cursor)
    print("Creating data table")
    create_data_table(cursor)
    print("Creating corrupt table")
    create_corrupt_table(cursor)
    print("Creating screenshots table")
    create_screenshots_table(cursor)
    print("Creating movies table")
    create_movies_table(cursor)
    print("Creating movies jpx table")
    create_movies_jpx_table(cursor)
    print("Creating movie formats table")
    create_movie_formats_table(cursor)
    print("Creating youtube table")
    create_youtube_table(cursor)
    print("Creating data coverage table")
    create_data_coverage_table(cursor)
    print("Updating image table index")
    update_image_table_index(cursor)
    print("Creating events table")
    create_events_table(cursor)
    print("Creating events coverage table")
    create_events_coverage_table(cursor)
    print("Creating redis stats table")
    create_redis_stats_table(cursor)
    print("Creating rate limit table")
    create_rate_limit_table(cursor)
    print("Creating flare prediction dataset table")
    create_flare_prediction_dataset_table(cursor)
    print("Creating flare prediction table")
    create_flare_prediction_table(cursor)
    print("Creating client_states table")
    create_client_states_table(cursor)

    return db, cursor

def get_db_cursor(dbhost, dbname, dbuser, dbpass, mysql=True):
    """Creates a database connection"""
    if mysql:
        try:
            import mysql.connector as mysqld
            db = mysqld.connect(buffered=True, autocommit= True, use_unicode=True, charset="utf8", host=dbhost, database=dbname, user=dbuser, password=dbpass)
        except ImportError:
            try:
                import MySQLdb as mysqld
                db = mysqld.connect(charset="utf8", host=dbhost, db=dbname, user=dbuser, passwd=dbpass)
            except ImportError:
                print(e)
                exit()
    else:
        import pgdb
        db = pgdb.connect(use_unicode=True, charset="utf8", database=dbname, user=dbuser, password=dbpass)
        db.autocommit(True)

    cursor = db.cursor()
    return db, cursor

def check_db_info(adminuser, adminpass, mysql):
    """Validate database login information"""
    try:
        if mysql:
            try:
                import mysql.connector as mysqld
                db = mysqld.connect(user=adminuser, password=adminpass)
            except ImportError:
                try:
                    import MySQLdb as mysqld
                    db = mysqld.connect(user=adminuser, passwd=adminpass)
                except ImportError:
                    print(e)
                    return False
        else:
            import pgdb
            db = pgdb.connect(database="postgres", user=adminuser, password=adminpass)
    except mysqld.Error as e:
        print(e)
        return False

    db.close()
    return True

def create_db(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql, adaptor):
    """Creates Helioviewer database

    TODO (2009/08/18) Catch error when db already exists and gracefully exit
    """
    if dbhost == "localhost":
        hostname = dbhost
    else:
        hostname = '%'

    create_str = "CREATE DATABASE IF NOT EXISTS %s;" % dbname
    user_str = "CREATE USER '%s'@'%s' IDENTIFIED BY '%s';" % (dbuser, hostname, dbpass)
    grant_str = "GRANT ALL ON %s.* TO '%s'@'%s';" % (dbname, dbuser, hostname)

    if mysql:
        try:
           db = adaptor.connect(autocommit= True, use_unicode=True, charset="utf8", host=dbhost, user=adminuser, passwd=adminpass)
           cursor = db.cursor()
           cursor.execute(create_str)
           cursor.execute(user_str)
           cursor.execute(grant_str)
           cursor.execute("FLUSH PRIVILEGES;")
        except adaptor.Error as e:
            print("Error: " + e.args[1])
            sys.exit(2)
    else:
        try:
            db = adaptor.connect(database="postgres", user=adminuser, password=adminpass)
            cursor = db.cursor()
            cursor.execute(create_str)
            cursor.execute(user_str)
            cursor.execute(grant_str)
        except Exception as e:
            print("Error: " + e.args[1])
            sys.exit(2)

    cursor.close()

def create_data_table(cursor):
    """Creates table to store data information"""
    sql = \
    """CREATE TABLE `data` (
      `id`       int(10) unsigned NOT NULL AUTO_INCREMENT,
      `filepath` varchar(255) NOT NULL,
      `filename` varchar(255) NOT NULL,
      `date`     datetime DEFAULT NULL,
      `date_end` datetime DEFAULT NULL,
      `sourceId` smallint(5) unsigned NOT NULL,
      `scale` float DEFAULT NULL,
      `width` int(11) DEFAULT NULL,
      `height` int(11) DEFAULT NULL,
      `refPixelX` float DEFAULT NULL,
      `refPixelY` float DEFAULT NULL,
      `layeringOrder` int(11) DEFAULT NULL,
      `DSUN_OBS` float DEFAULT NULL,
      `SOLAR_R` float DEFAULT NULL,
      `RADIUS` float DEFAULT NULL,
      `CDELT1` float DEFAULT NULL,
      `CDELT2` float DEFAULT NULL,
      `CRVAL1` float DEFAULT NULL,
      `CRVAL2` float DEFAULT NULL,
      `CRPIX1` float DEFAULT NULL,
      `CRPIX2` float DEFAULT NULL,
      `XCEN` float DEFAULT NULL,
      `YCEN` float DEFAULT NULL,
      `CROTA1` float DEFAULT NULL,
      `process` tinyint(1) DEFAULT '0',
      `groupOne` int(11) NOT NULL DEFAULT '0',
      `groupTwo` int(11) DEFAULT NULL,
      `groupThree` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `filename_idx` (`filename`),
      KEY `date_index` (`sourceId`,`date`) USING BTREE,
      KEY `process` (`process`),
      KEY `sourceId` (`sourceId`),
      KEY `date_group` (`date`,`groupOne`) USING BTREE,
      KEY `date` (`date`),
      KEY `groupOne` (`groupOne`) USING BTREE,
      KEY `groupThree` (`groupThree`),
      KEY `groupTwo` (`groupTwo`),
      KEY `date_2` (`date`,`groupTwo`),
      KEY `date_3` (`date`,`groupThree`),
      INDEX `group_one_date` (`groupOne`, `date`),
      INDEX `group_two_date` (`groupTwo`, `date`),
      INDEX `group_three_date` (`groupThree`, `date`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"""
    cursor.execute(sql)

def create_corrupt_table(cursor):
    """Creates table to store corrupt image information"""
    sql = \
    """CREATE TABLE `corrupt` (
      `id`        INT unsigned NOT NULL auto_increment,
      `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `filename`  VARCHAR(255) NOT NULL,
      `note`      VARCHAR(255) DEFAULT '',
      PRIMARY KEY (`id`),
      KEY `timestamp_index` (`filename`,`timestamp`) USING BTREE,
      UNIQUE INDEX filename_idx(filename)
    ) DEFAULT CHARSET=ascii;"""
    cursor.execute(sql)

def create_datasource_table(cursor):
    """Creates a table with the known datasources"""
    cursor.execute("""
    CREATE TABLE `datasources` (
      `id`            smallint(5) unsigned NOT NULL,
      `name`          varchar(127) NOT NULL,
      `description`   varchar(255) DEFAULT NULL,
      `units`         varchar(20) DEFAULT NULL,
      `layeringOrder` tinyint(3) unsigned NOT NULL,
      `enabled`       tinyint(1) unsigned NOT NULL,
      `sourceIdGroup` varchar(256) DEFAULT '',
      `displayOrder` tinyint(2) NOT NULL DEFAULT '0',
      `groupOne` int(11) DEFAULT '0',
      `groupTwo` int(11) NOT NULL DEFAULT '0',
      `groupThree` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;""")

    cursor.execute("""
    INSERT INTO `datasources` (`id`, `name`, `description`, `units`, `layeringOrder`, `enabled`, `sourceIdGroup`, `displayOrder`, `groupOne`, `groupTwo`, `groupThree`) VALUES
(0, 'EIT 171', 'SOHO EIT 171', 'Å', 1, 0, '', 2, 0, 0, 0),
(1, 'EIT 195', 'SOHO EIT 195', 'Å', 1, 0, '', 0, 0, 0, 0),
(2, 'EIT 284', 'SOHO EIT 284', 'Å', 1, 0, '', 0, 0, 0, 0),
(3, 'EIT 304', 'SOHO EIT 304', 'Å', 1, 0, '', 0, 0, 0, 0),
(4, 'LASCO C2', 'SOHO LASCO C2', 'DN', 2, 0, '', 0, 0, 0, 0),
(5, 'LASCO C3', 'SOHO LASCO C3', 'DN', 3, 0, '', 1, 0, 0, 0),
(6, 'MDI Mag', 'SOHO MDI Mag', 'Mx', 1, 0, '', 1, 0, 0, 0),
(7, 'MDI Int', 'SOHO MDI Int', 'DN', 1, 0, '', 0, 0, 0, 0),
(8, 'AIA 94', 'SDO AIA 94', 'Å', 1, 0, '', 0, 0, 0, 0),
(9, 'AIA 131', 'SDO AIA 131', 'Å', 1, 0, '', 0, 0, 0, 0),
(10, 'AIA 171', 'SDO AIA 171', 'Å', 1, 0, '', 0, 0, 0, 0),
(11, 'AIA 193', 'SDO AIA 193', 'Å', 1, 0, '', 0, 0, 0, 0),
(12, 'AIA 211', 'SDO AIA 211', 'Å', 1, 0, '', 0, 0, 0, 0),
(13, 'AIA 304', 'SDO AIA 304', 'Å', 1, 0, '', 0, 0, 0, 0),
(14, 'AIA 335', 'SDO AIA 335', 'Å', 1, 0, '', 0, 0, 0, 0),
(15, 'AIA 1600', 'SDO AIA 1600', 'Å', 1, 0, '', 0, 0, 0, 0),
(16, 'AIA 1700', 'SDO AIA 1700', 'Å', 1, 0, '', 0, 0, 0, 0),
(17, 'AIA 4500', 'SDO AIA 4500', 'Å', 1, 0, '', 0, 0, 0, 0),
(18, 'HMI Int', 'SDO HMI Int', 'DN', 1, 0, '', 0, 0, 0, 0),
(19, 'HMI Mag', 'SDO HMI Mag', 'Mx', 1, 0, '', 1, 0, 0, 0),
(20, 'EUVI-A 171', 'STEREO A EUVI 171', 'Å', 1, 0, '', 3, 0, 0, 0),
(21, 'EUVI-A 195', 'STEREO A EUVI 195', 'Å', 1, 0, '', 0, 0, 0, 0),
(22, 'EUVI-A 284', 'STEREO A EUVI 284', 'Å', 1, 0, '', 0, 0, 0, 0),
(23, 'EUVI-A 304', 'STEREO A EUVI 304', 'Å', 1, 0, '', 0, 0, 0, 0),
(24, 'EUVI-B 171', 'STEREO B EUVI 171', 'Å', 1, 0, '', 4, 0, 0, 0),
(25, 'EUVI-B 195', 'STEREO B EUVI 195', 'Å', 1, 0, '', 0, 0, 0, 0),
(26, 'EUVI-B 284', 'STEREO B EUVI 284', 'Å', 1, 0, '', 0, 0, 0, 0),
(27, 'EUVI-B 304', 'STEREO B EUVI 304', 'Å', 1, 0, '', 0, 0, 0, 0),
(28, 'COR1-A', 'STEREO A COR1', 'DN', 2, 0, '', 0, 0, 0, 0),
(29, 'COR2-A', 'STEREO A COR2', 'DN', 3, 0, '', 0, 0, 0, 0),
(30, 'COR1-B', 'STEREO B COR1', 'DN', 2, 0, '', 0, 0, 0, 0),
(31, 'COR2-B', 'STEREO B COR2', 'DN', 3, 0, '', 0, 0, 0, 0),
(32, 'SWAP 174', 'PROBA-2 SWAP 174', 'Å', 1, 0, '', 5, 0, 0, 0),
(33, 'SXT AlMgMn', 'Yohkoh SXT AlMgMn', 'Å', 1, 0, '', 6, 0, 0, 0),
(34, 'SXT thin-Al', 'Yohkoh SXT thin-Al', 'Å', 1, 0, '', 0, 0, 0, 0),
(35, 'SXT white-light', 'Yohkoh SXT white-light', '', 1, 0, '', 0, 0, 0, 0),
(38, 'XRT Al_med/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(39, 'XRT Al_med/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(40, 'XRT Al_med/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(41, 'XRT Al_med/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(42, 'XRT Al_med/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(43, 'XRT Al_med/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(44, 'XRT Al_poly/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(45, 'XRT Al_poly/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(46, 'XRT Al_poly/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(47, 'XRT Al_poly/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(48, 'XRT Al_poly/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(49, 'XRT Al_poly/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(50, 'XRT Be_med/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(51, 'XRT Be_med/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(52, 'XRT Be_med/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(53, 'XRT Be_med/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(54, 'XRT Be_med/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(55, 'XRT Be_med/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(56, 'XRT Be_thin/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(57, 'XRT Be_thin/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(58, 'XRT Be_thin/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(59, 'XRT Be_thin/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(60, 'XRT Be_thin/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(61, 'XRT Be_thin/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(62, 'XRT C_poly/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(63, 'XRT C_poly/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(64, 'XRT C_poly/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(65, 'XRT C_poly/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(66, 'XRT C_poly/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(67, 'XRT C_poly/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(68, 'XRT Mispositioned/Mispositioned', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(69, 'XRT Open/Al_mesh', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(70, 'XRT Open/Al_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(71, 'XRT Open/Be_thick', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(72, 'XRT Open/Gband', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(73, 'XRT Open/Open', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(74, 'XRT Open/Ti_poly', NULL, NULL, 1, 0, '', 13, 0, 0, 0),
(75, 'TRACE 171', 'TRACE 171', 'Å', 1, 0, '', 8, 0, 0, 0),
(76, 'TRACE 195', 'TRACE 195', 'Å', 1, 0, '', 0, 0, 0, 0),
(77, 'TRACE 284', 'TRACE 284', 'Å', 1, 0, '', 0, 0, 0, 0),
(78, 'TRACE 1216', 'TRACE 1216', 'Å', 1, 0, '', 0, 0, 0, 0),
(79, 'TRACE 1550', 'TRACE 1550', 'Å', 1, 0, '', 0, 0, 0, 0),
(80, 'TRACE 1600', 'TRACE 1600', 'Å', 1, 0, '', 0, 0, 0, 0),
(81, 'TRACE 1700', 'TRACE 1700', 'Å', 1, 0, '', 0, 0, 0, 0),
(82, 'TRACE white-light', 'TRACE white-light', '', 1, 0, '', 0, 0, 0, 0),
(83, 'COSMO KCor', 'COSMO KCor', 'NM', 1, 0, '', 1, 0, 0, 0),
(84, 'EUI FSI 174', 'Solar Orbiter EUI FSI 174',  NULL, 1, 0, '', 0, 0, 0, 0),
(85, 'EUI FSI 304', 'Solar Orbiter EUI FSI 304',  NULL, 1, 0, '', 0, 0, 0, 0),
(86, 'EUI HRI 174', 'Solar Orbiter EUI HRI 174',  NULL, 1, 0, '', 0, 0, 0, 0),
(87, 'EUI HRI 1216', 'Solar Orbiter EUI HRI 1216',  NULL, 1, 0, '', 0, 0, 0, 0),
(88, 'IRIS SJI 1330', 'IRIS SJI 1330',  NULL, 1, 0, '', 0, 0, 0, 0),
(89, 'IRIS SJI 2796', 'IRIS SJI 2796',  NULL, 1, 0, '', 0, 0, 0, 0),
(90, 'IRIS SJI 1400', 'IRIS SJI 1400',  NULL, 1, 0, '', 0, 0, 0, 0),
(91, 'IRIS SJI 1600', 'IRIS SJI 1600',  NULL, 1, 0, '', 0, 0, 0, 0),
(92, 'IRIS SJI 2832', 'IRIS SJI 2832',  NULL, 1, 0, '', 0, 0, 0, 0),
(93, 'IRIS SJI 5000', 'IRIS SJI 5000',  NULL, 1, 0, '', 0, 0, 0, 0),
(94, 'GONG H-alpha', 'GONG H-Alpha',  NULL, 1, 0, '', 0, 0, 0, 0),
(2000, 'GOES-R SUVI 94', 'GOES-R SUVI 94',  NULL, 1, 0, '', 0, 0, 0, 0),
(2001, 'GOES-R SUVI 131', 'GOES-R SUVI 131',  NULL, 1, 0, '', 0, 0, 0, 0),
(2002, 'GOES-R SUVI 171', 'GOES-R SUVI 171',  NULL, 1, 0, '', 0, 0, 0, 0),
(2003, 'GOES-R SUVI 195', 'GOES-R SUVI 195',  NULL, 1, 0, '', 0, 0, 0, 0),
(2004, 'GOES-R SUVI 284', 'GOES-R SUVI 284',  NULL, 1, 0, '', 0, 0, 0, 0),
(2005, 'GOES-R SUVI 304', 'GOES-R SUVI 304',  NULL, 1, 0, '', 0, 0, 0, 0),
(10001, 'XRT Any/Any', NULL, NULL, 1, 0, '38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74', 1, 1, 0, 0),
(10002, 'XRT Any/Al_mesh', NULL, NULL, 1, 0, '38,44,50,56,62,69', 2, 0, 1, 0),
(10003, 'XRT Any/Al_thick', NULL, NULL, 1, 0, '39,45,51,57,63,70', 2, 0, 1, 0),
(10004, 'XRT Any/Be_thick', NULL, NULL, 1, 0, '40,46,52,58,64,71', 3, 0, 1, 0),
(10005, 'XRT Any/Gband', NULL, NULL, 1, 0, '41,47,53,59,65,72', 4, 0, 1, 0),
(10006, 'XRT Any/Open', NULL, NULL, 1, 0, '42,48,54,60,66,73', 5, 0, 1, 0),
(10007, 'XRT Any/Ti_poly', NULL, NULL, 1, 0, '43,49,55,61,67,74', 6, 0, 1, 0),
(10008, 'XRT Al_med/Any', NULL, NULL, 1, 0, '38,39,40,41,42,43', 7, 0, 0, 1),
(10009, 'XRT Al_poly/Any', NULL, NULL, 1, 0, '44,45,46,47,48,49', 8, 0, 0, 1),
(10010, 'XRT Be_med/Any', NULL, NULL, 1, 0, '50,51,52,53,54,55', 9, 0, 0, 1),
(10011, 'XRT Be_thin/Any', NULL, NULL, 1, 0, '56,57,58,59,60,61', 10, 0, 0, 1),
(10012, 'XRT C_poly/Any', NULL, NULL, 1, 0, '62,63,64,65,66,67', 11, 0, 0, 1),
(10013, 'XRT Open/Any', NULL, NULL, 1, 0, '69,70,71,72,73,74', 12, 0, 0, 1);""")



def create_datasource_property_table(cursor):
    """Creates a table with the known datasource properties"""
    cursor.execute(
    """CREATE TABLE `datasource_property` (
      `sourceId`    smallint(5) unsigned NOT NULL,
      `label`       varchar(16) NOT NULL,
      `name`        varchar(255) NOT NULL,
      `fitsName`    varchar(255) NOT NULL,
      `description` varchar(255) NOT NULL,
      `uiOrder`     tinyint(3) unsigned NOT NULL,
      KEY `sourceId` (`sourceId`),
      KEY `label` (`label`),
      KEY `name` (`name`),
      KEY `fitsName` (`fitsName`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;""")

    """Inserts properties about datasource
        @sourceId:      unique sidentifier which matches datasource table id
        @label:         one of the following Observatory/Instrument/Detector/Measurement
        @name:          the name that is embedded in the jp2 file
        @fitsName       the name that is embedded in the jp2 file
        @description:   verbal description of datasource
        @uiOrder:       the order of property appearance embedded in the jp2 image,
                        refers to the order of drop-down ui elements for choosing
                        image source on helioviewer.org"""
    cursor.execute("""
INSERT INTO `datasource_property` (`sourceId`, `label`, `name`, `fitsName`, `description`, `uiOrder`) VALUES
(0, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(1, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(2, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(3, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(4, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(5, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(6, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(7, 'Observatory', 'SOHO', 'SOHO', 'Solar and Heliospheric Observatory', 1),
(8, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(9, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(10, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(11, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(12, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(13, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(14, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(15, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(16, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(17, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(18, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(19, 'Observatory', 'SDO', 'SDO', 'Solar Dynamics Observatory', 1),
(20, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(21, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(22, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(23, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(24, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(25, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(26, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(27, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(28, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(29, 'Observatory', 'STEREO_A', 'STEREO_A', 'Solar Terrestrial Relations Observatory Ahead', 1),
(30, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(31, 'Observatory', 'STEREO_B', 'STEREO_B', 'Solar Terrestrial Relations Observatory Behind', 1),
(32, 'Observatory', 'PROBA2', 'PROBA2', 'Project for OnBoard Autonomy 2', 1),
(33, 'Observatory', 'Yohkoh', 'Yohkoh', 'Yohkoh (Solar-A)', 1),
(34, 'Observatory', 'Yohkoh', 'Yohkoh', 'Yohkoh (Solar-A)', 1),
(35, 'Observatory', 'Yohkoh', 'Yohkoh', 'Yohkoh (Solar-A)', 1),
(84, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(85, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(86, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(87, 'Observatory', 'SOLO', 'Solar_Orbiter', 'Solar Orbiter', 1),
(88, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(89, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(90, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(91, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(92, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(93, 'Observatory', 'IRIS', 'IRIS', 'IRIS', 1),
(94, 'Observatory', 'GONG', 'NSO-GONG', 'GONG', 1),
(2000, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2001, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2002, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2003, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2004, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(2005, 'Observatory', 'GOES-R', 'GOES-R', 'GOES-R', 1),
(0, 'Instrument', 'EIT', 'EIT', 'Extreme ultraviolet Imaging Telescope', 2),
(1, 'Instrument', 'EIT', 'EIT', 'Extreme ultraviolet Imaging Telescope', 2),
(2, 'Instrument', 'EIT', 'EIT', 'Extreme ultraviolet Imaging Telescope', 2),
(3, 'Instrument', 'EIT', 'EIT', 'Extreme ultraviolet Imaging Telescope', 2),
(4, 'Instrument', 'LASCO', 'LASCO', 'The Large Angle Spectrometric Coronagraph', 2),
(5, 'Instrument', 'LASCO', 'LASCO', 'The Large Angle Spectrometric Coronagraph', 2),
(6, 'Instrument', 'MDI', 'MDI', 'Michelson Doppler Imager', 2),
(7, 'Instrument', 'MDI', 'MDI', 'Michelson Doppler Imager', 2),
(8, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(9, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(10, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(11, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(12, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(13, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(14, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(15, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(16, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(17, 'Instrument', 'AIA', 'AIA', 'Atmospheric Imaging Assembly', 2),
(18, 'Instrument', 'HMI', 'HMI', 'Helioseismic and Magnetic Imager', 2),
(19, 'Instrument', 'HMI', 'HMI', 'Helioseismic and Magnetic Imager', 2),
(20, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(21, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(22, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(23, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(24, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(25, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(26, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(27, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(28, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(29, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(30, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(31, 'Instrument', 'SECCHI', 'SECCHI', 'Sun Earth Connection Coronal and Heliospheric Investigation', 2),
(32, 'Instrument', 'SWAP', 'SWAP', 'Sun watcher using APS detectors and image processing', 2),
(33, 'Instrument', 'SXT', 'SXT', 'Soft X-ray Telescope', 2),
(34, 'Instrument', 'SXT', 'SXT', 'Soft X-ray Telescope', 2),
(35, 'Instrument', 'SXT', 'SXT', 'Soft X-ray Telescope', 2),
(84, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(85, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(86, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(87, 'Instrument', 'EUI', 'EUI', 'Extreme Ultraviolet Imager', 2),
(88, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(89, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(90, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(91, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(92, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(93, 'Instrument', 'SJI', 'SJI', 'Slit Jaw Imager', 2),
(94, 'Instrument', 'GONG', 'GONG', 'GONG', 2),
(2000, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2001, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2002, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2003, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2004, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(2005, 'Instrument', 'SUVI', 'SUVI', 'Solar UltraViolet Imager', 2),
(4, 'Detector', 'C2', 'C2', 'Coronograph 2', 3),
(5, 'Detector', 'C3', 'C3', 'Coronograph 3', 3),
(20, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(21, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(22, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(23, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(24, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(25, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(26, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(27, 'Detector', 'EUVI', 'EUVI', 'Extreme Ultraviolet Imager', 3),
(28, 'Detector', 'COR1', 'COR1', 'Coronograph 1', 3),
(29, 'Detector', 'COR2', 'COR2', 'Coronograph 2', 3),
(30, 'Detector', 'COR1', 'COR1', 'Coronograph 1', 3),
(31, 'Detector', 'COR2', 'COR2', 'Coronograph 2', 3),
(84, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(85, 'Detector', 'FSI', 'FSI', 'Full Sun Imager', 3),
(86, 'Detector', 'HRI', 'HRI_EUV', 'High Resolution Imager Extreme Ultraviolet', 3),
(87, 'Detector', 'HRI', 'HRI_LYA', 'High Resolution Imager Lyman-a', 3),
(94, 'Detector', 'H-alpha', 'H-alpha', 'H-alpha', 3),
(0, 'Measurement', '171', '171', '171 Ångström extreme ultraviolet', 3),
(1, 'Measurement', '195', '195', '195 Ångström extreme ultraviolet', 3),
(2, 'Measurement', '284', '284', '284 Ångström extreme ultraviolet', 3),
(3, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 3),
(4, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(5, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(6, 'Measurement', 'magnetogram', 'magnetogram', 'Magnetogram', 3),
(7, 'Measurement', 'continuum', 'continuum', 'Intensitygram', 3),
(8, 'Measurement', '94', '94', '94 Ångström extreme ultraviolet', 3),
(9, 'Measurement', '131', '131', '131 Ångström extreme ultraviolet', 3),
(10, 'Measurement', '171', '171', '171 Ångström extreme ultraviolet', 3),
(11, 'Measurement', '193', '193', '193 Ångström extreme ultraviolet', 3),
(12, 'Measurement', '211', '211', '211 Ångström extreme ultraviolet', 3),
(13, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 3),
(14, 'Measurement', '335', '335', '335 Ångström extreme ultraviolet', 3),
(15, 'Measurement', '1600', '1600', '1600 Ångström extreme ultraviolet', 3),
(16, 'Measurement', '1700', '1700', '1700 Ångström extreme ultraviolet', 3),
(17, 'Measurement', '4500', '4500', '4500 Ångström extreme ultraviolet', 3),
(18, 'Measurement', 'continuum', 'continuum', 'Intensitygram', 3),
(19, 'Measurement', 'magnetogram', 'magnetogram', 'Magnetogram', 3),
(20, 'Measurement', '171', '171', '171 Ångström extreme ultraviolet', 4),
(21, 'Measurement', '195', '195', '195 Ångström extreme ultraviolet', 4),
(22, 'Measurement', '284', '284', '284 Ångström extreme ultraviolet', 4),
(23, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4),
(24, 'Measurement', '171', '171', '171 Ångström extreme ultraviolet', 4),
(25, 'Measurement', '195', '195', '195 Ångström extreme ultraviolet', 4),
(26, 'Measurement', '284', '284', '284 Ångström extreme ultraviolet', 4),
(27, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4),
(28, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(29, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(30, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(31, 'Measurement', 'white-light', 'white-light', 'White Light', 4),
(32, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 3),
(84, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(85, 'Measurement', '304', '304', '304 Ångström extreme ultraviolet', 4),
(86, 'Measurement', '174', '174', '174 Ångström extreme ultraviolet', 4),
(87, 'Measurement', '1216', '1216', '1216 Ångström', 4),
(88, 'Measurement', '1330', '1330', '1330 Ångström', 3),
(89, 'Measurement', '2796', '2796', '2796 Ångström', 3),
(90, 'Measurement', '1400', '1400', '1400 Ångström', 3),
(91, 'Measurement', '1600', '1600', '1600 Ångström', 3),
(92, 'Measurement', '2832', '2832', '2832 Ångström', 3),
(93, 'Measurement', '5000', '5000', '5000 Ångström', 3),
(94, 'Measurement', '6562', '6562', 'H-alpha 6562 angstrom', 4),
(2000, 'Measurement', '94', '94', '94 Ångström',    3),
(2001, 'Measurement', '131', '131', '131 Ångström', 3),
(2002, 'Measurement', '171', '171', '171 Ångström', 3),
(2003, 'Measurement', '195', '195', '195 Ångström', 3),
(2004, 'Measurement', '284', '284', '284 Ångström', 3),
(2005, 'Measurement', '304', '304', '304 Ångström', 3),
(33, 'Filter', 'AlMgMn', 'AlMgMn', 'Al/Mg/Mn filter (2.4 Å - 32 Å pass band)', 3),
(34, 'Measurement', 'thin-Al', 'thin-Al', '11.6 μm Al filter (2.4 Å - 13 Å pass band)', 3),
(35, 'Measurement', 'white-light', 'white-light', 'No filter', 3),
(38, 'Observatory', 'Hinode', 'Hinode', '', 1),
(38, 'Instrument', 'XRT', 'XRT', '', 2),
(38, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(38, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(39, 'Observatory', 'Hinode', 'Hinode', '', 1),
(39, 'Instrument', 'XRT', 'XRT', '', 2),
(39, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(39, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(40, 'Observatory', 'Hinode', 'Hinode', '', 1),
(40, 'Instrument', 'XRT', 'XRT', '', 2),
(40, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(40, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(41, 'Observatory', 'Hinode', 'Hinode', '', 1),
(41, 'Instrument', 'XRT', 'XRT', '', 2),
(41, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(41, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(42, 'Observatory', 'Hinode', 'Hinode', '', 1),
(42, 'Instrument', 'XRT', 'XRT', '', 2),
(42, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(42, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(43, 'Observatory', 'Hinode', 'Hinode', '', 1),
(43, 'Instrument', 'XRT', 'XRT', '', 2),
(43, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(43, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(44, 'Observatory', 'Hinode', 'Hinode', '', 1),
(44, 'Instrument', 'XRT', 'XRT', '', 2),
(44, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(44, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(45, 'Observatory', 'Hinode', 'Hinode', '', 1),
(45, 'Instrument', 'XRT', 'XRT', '', 2),
(45, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(45, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(46, 'Observatory', 'Hinode', 'Hinode', '', 1),
(46, 'Instrument', 'XRT', 'XRT', '', 2),
(46, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(46, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(47, 'Observatory', 'Hinode', 'Hinode', '', 1),
(47, 'Instrument', 'XRT', 'XRT', '', 2),
(47, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(47, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(48, 'Observatory', 'Hinode', 'Hinode', '', 1),
(48, 'Instrument', 'XRT', 'XRT', '', 2),
(48, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(48, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(49, 'Observatory', 'Hinode', 'Hinode', '', 1),
(49, 'Instrument', 'XRT', 'XRT', '', 2),
(49, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(49, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(50, 'Observatory', 'Hinode', 'Hinode', '', 1),
(50, 'Instrument', 'XRT', 'XRT', '', 2),
(50, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(50, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(51, 'Observatory', 'Hinode', 'Hinode', '', 1),
(51, 'Instrument', 'XRT', 'XRT', '', 2),
(51, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(51, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(52, 'Observatory', 'Hinode', 'Hinode', '', 1),
(52, 'Instrument', 'XRT', 'XRT', '', 2),
(52, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(52, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(53, 'Observatory', 'Hinode', 'Hinode', '', 1),
(53, 'Instrument', 'XRT', 'XRT', '', 2),
(53, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(53, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(54, 'Observatory', 'Hinode', 'Hinode', '', 1),
(54, 'Instrument', 'XRT', 'XRT', '', 2),
(54, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(54, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(55, 'Observatory', 'Hinode', 'Hinode', '', 1),
(55, 'Instrument', 'XRT', 'XRT', '', 2),
(55, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(55, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(56, 'Observatory', 'Hinode', 'Hinode', '', 1),
(56, 'Instrument', 'XRT', 'XRT', '', 2),
(56, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(56, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(57, 'Observatory', 'Hinode', 'Hinode', '', 1),
(57, 'Instrument', 'XRT', 'XRT', '', 2),
(57, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(57, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(58, 'Observatory', 'Hinode', 'Hinode', '', 1),
(58, 'Instrument', 'XRT', 'XRT', '', 2),
(58, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(58, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(59, 'Observatory', 'Hinode', 'Hinode', '', 1),
(59, 'Instrument', 'XRT', 'XRT', '', 2),
(59, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(59, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(60, 'Observatory', 'Hinode', 'Hinode', '', 1),
(60, 'Instrument', 'XRT', 'XRT', '', 2),
(60, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(60, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(61, 'Observatory', 'Hinode', 'Hinode', '', 1),
(61, 'Instrument', 'XRT', 'XRT', '', 2),
(61, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(61, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(62, 'Observatory', 'Hinode', 'Hinode', '', 1),
(62, 'Instrument', 'XRT', 'XRT', '', 2),
(62, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(62, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(63, 'Observatory', 'Hinode', 'Hinode', '', 1),
(63, 'Instrument', 'XRT', 'XRT', '', 2),
(63, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(63, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(64, 'Observatory', 'Hinode', 'Hinode', '', 1),
(64, 'Instrument', 'XRT', 'XRT', '', 2),
(64, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(64, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(65, 'Observatory', 'Hinode', 'Hinode', '', 1),
(65, 'Instrument', 'XRT', 'XRT', '', 2),
(65, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(65, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(66, 'Observatory', 'Hinode', 'Hinode', '', 1),
(66, 'Instrument', 'XRT', 'XRT', '', 2),
(66, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(66, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(67, 'Observatory', 'Hinode', 'Hinode', '', 1),
(67, 'Instrument', 'XRT', 'XRT', '', 2),
(67, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(67, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(68, 'Observatory', 'Hinode', 'Hinode', '', 1),
(68, 'Instrument', 'XRT', 'XRT', '', 2),
(68, 'Filter Wheel 1', 'Mispositioned', 'Mispositioned', '', 3),
(68, 'Filter Wheel 2', 'Mispositioned', 'Mispositioned', '', 4),
(69, 'Observatory', 'Hinode', 'Hinode', '', 1),
(69, 'Instrument', 'XRT', 'XRT', '', 2),
(69, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(69, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(70, 'Observatory', 'Hinode', 'Hinode', '', 1),
(70, 'Instrument', 'XRT', 'XRT', '', 2),
(70, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(70, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(71, 'Observatory', 'Hinode', 'Hinode', '', 1),
(71, 'Instrument', 'XRT', 'XRT', '', 2),
(71, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(71, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(72, 'Observatory', 'Hinode', 'Hinode', '', 1),
(72, 'Instrument', 'XRT', 'XRT', '', 2),
(72, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(72, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(73, 'Observatory', 'Hinode', 'Hinode', '', 1),
(73, 'Instrument', 'XRT', 'XRT', '', 2),
(73, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(73, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(74, 'Observatory', 'Hinode', 'Hinode', '', 1),
(74, 'Instrument', 'XRT', 'XRT', '', 2),
(74, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(74, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(75, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(75, 'Measurement', '171', '171', 'TRACE 171', 2),
(76, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(76, 'Measurement', '195', '195', 'TRACE 195', 2),
(77, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(77, 'Measurement', '284', '284', 'TRACE 284', 2),
(78, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(78, 'Measurement', '1216', '1216', 'TRACE 1216', 2),
(79, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(79, 'Measurement', '1550', '1550', 'TRACE 1550', 2),
(80, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(80, 'Measurement', '1600', '1600', 'TRACE 1600', 2),
(81, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(81, 'Measurement', '1700', '1700', 'TRACE 1700', 2),
(82, 'Observatory', 'TRACE', 'TRACE', 'Transition Region and Coronal Explorer', 1),
(82, 'Measurement', 'white-light', 'white-light', 'TRACE white-light', 2),
(83, 'Observatory', 'MLSO', 'MLSO', 'MLSO', '1'),
(83, 'Instrument', 'COSMO', 'COSMO', 'COSMO', '2'),
(83, 'Detector', 'KCor', 'KCor', 'KCor', '3'),
(83, 'Measurement', '735', '735', '735', '4'),
(10001, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10001, 'Instrument', 'XRT', 'XRT', '', 2),
(10001, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10001, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10002, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10002, 'Instrument', 'XRT', 'XRT', '', 2),
(10002, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10002, 'Filter Wheel 2', 'Al_mesh', 'Al_mesh', '', 4),
(10003, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10003, 'Instrument', 'XRT', 'XRT', '', 2),
(10003, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10003, 'Filter Wheel 2', 'Al_thick', 'Al_thick', '', 4),
(10004, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10004, 'Instrument', 'XRT', 'XRT', '', 2),
(10004, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10004, 'Filter Wheel 2', 'Be_thick', 'Be_thick', '', 4),
(10005, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10005, 'Instrument', 'XRT', 'XRT', '', 2),
(10005, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10005, 'Filter Wheel 2', 'Gband', 'Gband', '', 4),
(10006, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10006, 'Instrument', 'XRT', 'XRT', '', 2),
(10006, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10006, 'Filter Wheel 2', 'Open', 'Open', '', 4),
(10007, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10007, 'Instrument', 'XRT', 'XRT', '', 2),
(10007, 'Filter Wheel 1', 'Any', 'Any', '', 3),
(10007, 'Filter Wheel 2', 'Ti_poly', 'Ti_poly', '', 4),
(10008, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10008, 'Instrument', 'XRT', 'XRT', '', 2),
(10008, 'Filter Wheel 1', 'Al_med', 'Al_med', '', 3),
(10008, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10009, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10009, 'Instrument', 'XRT', 'XRT', '', 2),
(10009, 'Filter Wheel 1', 'Al_poly', 'Al_poly', '', 3),
(10009, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10010, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10010, 'Instrument', 'XRT', 'XRT', '', 2),
(10010, 'Filter Wheel 1', 'Be_med', 'Be_med', '', 3),
(10010, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10011, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10011, 'Instrument', 'XRT', 'XRT', '', 2),
(10011, 'Filter Wheel 1', 'Be_thin', 'Be_thin', '', 3),
(10011, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10012, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10012, 'Instrument', 'XRT', 'XRT', '', 2),
(10012, 'Filter Wheel 1', 'C_poly', 'C_poly', '', 3),
(10012, 'Filter Wheel 2', 'Any', 'Any', '', 4),
(10013, 'Observatory', 'Hinode', 'Hinode', '', 1),
(10013, 'Instrument', 'XRT', 'XRT', '', 2),
(10013, 'Filter Wheel 1', 'Open', 'Open', '', 3),
(10013, 'Filter Wheel 2', 'Any', 'Any', '', 4);""")

def create_movies_table(cursor):
    """Creates movie table

    Creates a simple table for storing information about movies built on
    Helioviewer.org.

    Note: Region of interest coordinates are stored in arc-seconds even though
    request is done in pixels in order to make it easier to find screenshots
    with similar ROIs regardless of scale.
    """
    cursor.execute("""
    CREATE TABLE `movies` (
      `id`                INT unsigned NOT NULL auto_increment,
      `timestamp`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `reqStartDate`      datetime NOT NULL,
      `reqEndDate`        datetime NOT NULL,
      `reqObservationDate` datetime DEFAULT NULL,
      `imageScale`        FLOAT UNSIGNED NOT NULL,
      `regionOfInterest`  POLYGON NOT NULL,
      `maxFrames`         SMALLINT UNSIGNED NOT NULL,
      `watermark`         TINYINT(1) UNSIGNED NOT NULL,
      `dataSourceString`  VARCHAR(255) NOT NULL,
      `dataSourceBitMask` BIGINT UNSIGNED,
      `eventSourceString` VARCHAR(1024) DEFAULT NULL,
      `eventsLabels`      TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
      `eventsState`       JSON NOT NULL DEFAULT '{}',
      `movieIcons`        tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
      `followViewport`    tinyint(1) DEFAULT '0',
      `scale`             TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
      `scaleType`         VARCHAR(12) DEFAULT 'earth',
      `scaleX`            FLOAT DEFAULT '0',
      `scaleY`            FLOAT DEFAULT '0',
      `numLayers`         TINYINT UNSIGNED,
      `queueNum`          SMALLINT UNSIGNED,
      `frameRate`         FLOAT UNSIGNED,
      `movieLength`       FLOAT UNSIGNED,
      `startDate`         datetime,
      `endDate`           datetime,
      `numFrames`         INT UNSIGNED,
      `width`             SMALLINT UNSIGNED,
      `height`            SMALLINT UNSIGNED,
      `buildTimeStart`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `buildTimeEnd`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `size` tinyint(2) NOT NULL DEFAULT '0',
      `switchSources`     TINYINT(1) NOT NULL DEFAULT 0,
      `celestialBodiesLabels` VARCHAR(372) NOT NULL,
      `celestialBodiesTrajectories` VARCHAR(372) NOT NULL,
       PRIMARY KEY (`id`),
       KEY `startDate` (`startDate`),
       KEY `endDate` (`endDate`),
       KEY `startDate_2` (`startDate`,`endDate`)
    ) DEFAULT CHARSET=utf8;""")

def create_movies_jpx_table(cursor):
    """Creates movie table

    Creates a table for logging jpx movies created with JHelioviewer
    """
    cursor.execute("""
    CREATE TABLE `movies_jpx` (
      `id`                INT unsigned NOT NULL auto_increment,
      `timestamp`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `reqStartDate`      datetime NOT NULL,
      `reqEndDate`        datetime NOT NULL,
      `sourceId`          INT unsigned,
       PRIMARY KEY (`id`),
       KEY `sourceId` (`sourceId`)
    ) DEFAULT CHARSET=utf8;""")

def create_movie_formats_table(cursor):
    """Creates movie formats table

    Creates a table to keep track of the processing status for each format
    (mp4, web, etc) movie that needsto be created for a given movie request.
    """
    cursor.execute("""
    CREATE TABLE `movieFormats` (
      `id`                INT unsigned NOT NULL auto_increment,
      `movieId`           INT unsigned NOT NULL,
      `format`            VARCHAR(255) NOT NULL,
      `status`            VARCHAR(255) NOT NULL,
      `procTime`          SMALLINT UNSIGNED,
      `modified`          timestamp NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       KEY `format` (`format`),
       KEY `movieId` (`movieId`,`format`),
       KEY `movieId_2` (`movieId`)
    ) DEFAULT CHARSET=utf8;""")

def create_redis_stats_table(cursor):
    """
    Creates the table used to hold API statistics
    """
    cursor.execute("""
    CREATE TABLE `redis_stats` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `datetime`       datetime NOT NULL,
        `action`         varchar(32) NOT NULL,
        `count`          int unsigned NOT NULL,
        `device`         VARCHAR(64) DEFAULT 'x',
        PRIMARY KEY (`id`),
        INDEX dates (`datetime`),
        INDEX devices (`device`, `action`, `datetime`),
        INDEX actions (`action`, `datetime`)
    ) DEFAULT CHARSET=utf8;""")

def create_rate_limit_table(cursor):
    """
    Create table for tracking rate limiting events
    """
    cursor.execute("""
    CREATE TABLE `rate_limit_exceeded` (
        `datetime`    datetime NOT NULL,
        `identifier`  varchar(39) NOT NULL,
        `count`       int unsigned NOT NULL,
        PRIMARY KEY (`datetime`, `identifier`)
    ) DEFAULT CHARSET=utf8;""")

def create_client_states_table(cursor):
    """
    Create table for client states
    """
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS `client_states` (
        `id`      CHAR(64) PRIMARY KEY,
        `state`   JSON NOT NULL DEFAULT '{}',
        `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;""")

def create_flare_prediction_table(cursor):
    """
    Create table for storing CCMC Flare Predictions
    """
    cursor.execute("""
        CREATE TABLE flare_predictions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dataset_id INT UNSIGNED NOT NULL,
        start_window DATETIME NOT NULL,
        end_window DATETIME NOT NULL,
        issue_time DATETIME NOT NULL,
        c FLOAT,
        m FLOAT,
        x FLOAT,
        cplus FLOAT,
        mplus FLOAT,
        latitude FLOAT NOT NULL,
        longitude FLOAT NOT NULL,
        hpc_x FLOAT,
        hpc_y FLOAT,
        sha256 VARCHAR(64) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (dataset_id) REFERENCES flare_datasets(id),
        UNIQUE KEY (sha256),
        INDEX `issue_time_idx` (`issue_time`)
    );""")

def create_flare_prediction_dataset_table(cursor):
    cursor.execute("""
        CREATE TABLE flare_datasets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (name)
        );"""
    )

    cursor.execute("""
        INSERT INTO flare_datasets (id, name) VALUES
            ( 1, "SIDC_Operator_REGIONS"),
            ( 2, "BoM_flare1_REGIONS"),
            ( 3, "ASSA_1_REGIONS"),
            ( 4, "ASSA_24H_1_REGIONS"),
            ( 5, "AMOS_v1_REGIONS"),
            ( 6, "ASAP_1_REGIONS"),
            ( 7, "MAG4_LOS_FEr_REGIONS"),
            ( 8, "MAG4_LOS_r_REGIONS"),
            ( 9, "MAG4_SHARP_FE_REGIONS"),
            (10, "MAG4_SHARP_REGIONS"),
            (11, "MAG4_SHARP_HMI_REGIONS"),
            (12, "AEffort_REGIONS");"""
    )

def create_youtube_table(cursor):
    """Creates a table to track shared movie uploads.

    Creates table to keep track of movies that have been uploaded to YouTube
    and shared with other Helioviewer users.
    """
    cursor.execute("""
    CREATE TABLE `youtube` (
      `id`          INT unsigned NOT NULL auto_increment,
      `movieId`     INT unsigned NOT NULL,
      `youtubeId`   VARCHAR(16),
      `timestamp`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `title`       VARCHAR(100) NOT NULL,
      `description` VARCHAR(5000) NOT NULL,
      `keywords`    VARCHAR(500) NOT NULL,
      `thumbnail`   VARCHAR(512) DEFAULT NULL,
      `shared`      TINYINT(1) UNSIGNED NOT NULL,
      `checked`     datetime DEFAULT NULL,
       PRIMARY KEY (`id`),
       UNIQUE INDEX movieid_idx(movieId),
       KEY `shared` (`shared`),
       KEY `movieId` (`movieId`,`shared`),
       KEY `youtubeId` (`youtubeId`),
       KEY `youtubeId_2` (`youtubeId`,`shared`)
    ) DEFAULT CHARSET=utf8;""")

def create_screenshots_table(cursor):
    """Creates screenshot table

    Creates a simple table for storing information about screenshots built on
    Helioviewer.org

    Note: Region of interest coordinates are stored in arc-seconds even though
    request is done in pixels in order to make it easier to find screenshots
    with similar ROIs regardless of scale.
    """

    cursor.execute("""
    CREATE TABLE `screenshots` (
      `id`                INT unsigned NOT NULL auto_increment,
      `timestamp`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `observationDate`   datetime NOT NULL,
      `imageScale`        FLOAT UNSIGNED,
      `regionOfInterest`  POLYGON NOT NULL,
      `watermark`         TINYINT(1) UNSIGNED DEFAULT TRUE,
      `dataSourceString`  VARCHAR(255) NOT NULL,
      `dataSourceBitMask` BIGINT UNSIGNED,
      `eventSourceString` VARCHAR(1024) DEFAULT NULL,
      `eventsLabels`      TINYINT(1) UNSIGNED NOT NULL,
      `eventsState`       JSON NOT NULL DEFAULT '{}',
      `movieIcons`        tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
      `scale`             TINYINT(1) unsigned NOT NULL DEFAULT '0',
      `scaleType`         VARCHAR(12) DEFAULT 'earth',
      `scaleX`            FLOAT DEFAULT '0',
      `scaleY`            FLOAT DEFAULT '0',
      `numLayers`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
      `switchSources`     TINYINT(1) NOT NULL DEFAULT 0,
      `celestialBodiesLabels` VARCHAR(372),
      `celestialBodiesTrajectories` VARCHAR(372),
       PRIMARY KEY (`id`)
    ) DEFAULT CHARSET=utf8;""")

def create_data_coverage_table(cursor):
    """Creates a table to keep data coverage statistics

    Creates a simple table for storing data coverage statistics
    """
    cursor.execute("""
    CREATE TABLE `data_coverage_30_min` (
      `date` datetime NOT NULL,
      `sourceId` int(10) unsigned NOT NULL,
      `count` int(10) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`date`,`sourceId`),
      KEY `index1` (`date`),
      KEY `index2` (`sourceId`),
      KEY `index3` (`sourceId`,`date`),
      KEY `index4` (`date`,`sourceId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;""")

def create_events_table(cursor):
    """Creates a table to keep events data

    Creates a simple table for storing events
    """
    cursor.execute("""
    CREATE TABLE `events` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `kb_archivid` varchar(128) DEFAULT NULL,
      `frm_name` varchar(128) DEFAULT NULL,
      `concept` varchar(128) DEFAULT NULL,
      `frm_specificid` varchar(128) DEFAULT NULL,
      `event_starttime` datetime DEFAULT NULL,
      `event_endtime` datetime DEFAULT NULL,
      `event_peaktime` datetime DEFAULT NULL,
      `event_type` varchar(32) DEFAULT NULL,
      `event_before` varchar(128) DEFAULT NULL,
      `event_after` varchar(128) DEFAULT NULL,
      `hpc_boundcc` text,
      `hv_labels_formatted` text,
      `hv_poly_url` varchar(256) DEFAULT NULL,
      `hv_event_starttime` datetime DEFAULT NULL,
      `hv_event_endtime` datetime DEFAULT NULL,
      `hv_rot_hpc_time_base` datetime DEFAULT NULL,
      `hv_rot_hpc_time_targ` datetime DEFAULT NULL,
      `hv_hpc_x_notscaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_hpc_y_notscaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_hpc_x_rot_delta_notscaled` decimal(20,16) DEFAULT NULL,
      `hv_hpc_y_rot_delta_notscaled` decimal(20,16) DEFAULT NULL,
      `hv_hpc_x_scaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_hpc_y_scaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_hpc_y_final` decimal(20,16) DEFAULT NULL,
      `hv_hpc_x_final` decimal(20,16) DEFAULT NULL,
      `hv_hpc_r_scaled` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_x_final` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_y_final` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_x_ul_scaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_y_ul_scaled_rot` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_x_ul_scaled_norot` decimal(20,16) DEFAULT NULL,
      `hv_poly_hpc_y_ul_scaled_norot` decimal(20,16) DEFAULT NULL,
      `hv_poly_width_max_zoom_pixels` decimal(20,16) DEFAULT NULL,
      `hv_poly_height_max_zoom_pixels` decimal(20,16) DEFAULT NULL,
      `hv_marker_offset_x` float NOT NULL DEFAULT '0',
      `hv_marker_offset_y` float NOT NULL DEFAULT '0',
      `event_json` longtext,
      PRIMARY KEY (`id`),
      UNIQUE KEY `kb_archivid` (`kb_archivid`) USING BTREE,
      KEY `concept` (`concept`),
      KEY `event_type` (`event_type`),
      KEY `event_starttime` (`event_starttime`),
      KEY `event_endtime` (`event_endtime`),
      KEY `event_starttime_2` (`event_starttime`,`event_endtime`),
      KEY `frm_name` (`frm_name`),
      KEY `frm_name_2` (`frm_name`,`event_type`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;""")

def create_events_coverage_table(cursor):
    """Creates a table to keep events coverage statistics

    Creates a simple table for storing events coverage statistics
    """
    cursor.execute("""
    CREATE TABLE `events_coverage` (
      `date` datetime NOT NULL,
      `period` varchar(4) NOT NULL DEFAULT '30m',
      `event_type` varchar(32) NOT NULL,
      `frm_name` varchar(128) NOT NULL DEFAULT '',
      `count` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`date`,`period`,`event_type`,`frm_name`),
      KEY `event_type` (`event_type`),
      KEY `period` (`period`,`event_type`),
      KEY `date` (`date`,`period`,`event_type`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;""")


def enable_datasource(cursor, sourceId):
    """Enables datasource

    Marks a single datasource as enabled to signal that there is data for that
    source
    """
    sql="UPDATE datasources SET enabled=1 WHERE id=%s;" % sourceId
    cursor.execute(sql)

def update_image_table_index(cursor):
    """Updates index on data table"""
    cursor.execute("OPTIMIZE TABLE data;")

def mark_as_corrupt(cursor, filename, note):
    """Adds an image to the 'corrupt' database table"""
    sql = "INSERT INTO corrupt(filename, note) VALUES ('%s', '%s');" % (filename,
                                                                    note)

    cursor.execute(sql)

def get_datasources(cursor):
    """Returns a list of the known datasources"""
    __SOURCE_ID_IDX__ = 0
    __ENABLED_IDX__ = 1

    letters = {0:'a', 1:'b', 2:'c', 3:'d', 4:'e'}

    sql = \
    """ SELECT
            s.id AS id,
            s.enabled AS enabled"""

    for i,letter in letters.items():
        sql += ', '
        sql += letter+'.fitsName AS '+letter+'_name'

    sql += ' FROM datasources s '

    for i,letter in letters.items():
        sql += 'LEFT JOIN datasource_property '+letter+' '
        sql += 'ON s.id='+letter+'.sourceId '
        sql += 'AND '+letter+'.uiOrder='+str(i+1)+' '

    # Fetch available data-sources
    cursor.execute(sql)
    results = cursor.fetchall()

    # Convert results into a more easily traversable tree structure
    tree = {}

    for source in results:
        id = int(source[__SOURCE_ID_IDX__])
        enabled = bool(source[__ENABLED_IDX__])

        leaf = tree
        for i in range(2, len(letters)+2):
            if source[i] is None:
                leaf['id'] = id
                leaf['enabled'] = enabled
                break
            if source[i] not in leaf:
                leaf[source[i]] = {}
            leaf = leaf[source[i]]

    return tree
