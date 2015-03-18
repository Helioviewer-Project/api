# -*- coding: utf-8 -*-
"""Helioviewer.org installer database functions"""
import sys
import os

def setup_database_schema(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql):
    """Sets up Helioviewer.org database schema"""
    if mysql:
        import MySQLdb
        adaptor = MySQLdb
    else:
        import pgdb
        adaptor = pgdb

    create_db(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql, adaptor)

    # connect to helioviewer database
    cursor = get_db_cursor(dbhost, dbname, dbuser, dbpass, mysql)

    create_datasource_table(cursor)
    create_datasource_property_table(cursor)
    create_data_table(cursor)
    create_corrupt_table(cursor)
    create_screenshots_table(cursor)
    create_movies_table(cursor)
    create_movie_formats_table(cursor)
    create_youtube_table(cursor)
    create_statistics_table(cursor)
    create_data_coverage_table(cursor)
    update_image_table_index(cursor)

    return cursor

def get_db_cursor(dbhost, dbname, dbuser, dbpass, mysql=True):
    """Creates a database connection"""
    if mysql:
        import MySQLdb
    else:
        import pgdb

    if mysql:
        db = MySQLdb.connect(use_unicode=True, charset="utf8",
                             host=dbhost, db=dbname, user=dbuser,
                             passwd=dbpass)
    else:
        db = pgdb.connect(use_unicode=True, charset="utf8", database=dbname,
                          user=dbuser, password=dbpass)

    db.autocommit(True)
    return db.cursor()

def check_db_info(adminuser, adminpass, mysql):
    """Validate database login information"""
    try:
        if mysql:
            try:
                import MySQLdb
            except ImportError as e:
                print(e)
                return False
            db = MySQLdb.connect(user=adminuser, passwd=adminpass)
        else:
            import pgdb
            db = pgdb.connect(database="postgres", user=adminuser,
                              password=adminpass)
    except MySQLdb.Error as e:
        print(e)
        return False

    db.close()
    return True

def create_db(adminuser, adminpass, dbhost, dbname, dbuser, dbpass, mysql, adaptor):
    """Creates Helioviewer database

    TODO (2009/08/18) Catch error when db already exists and gracefully exit
    """

    create_str = "CREATE DATABASE IF NOT EXISTS %s;" % dbname
    grant_str = "GRANT ALL ON %s.* TO '%s'@'localhost' IDENTIFIED BY '%s';" % (
                dbname, dbuser, dbpass)

    if mysql:
        try:
           db = adaptor.connect(user=adminuser, passwd=adminpass)
           cursor = db.cursor()
           cursor.execute(create_str)
           cursor.execute(grant_str)
        except adaptor.Error as e:
            print("Error: " + e.args[1])
            sys.exit(2)
    else:
        try:
            db = adaptor.connect(database="postgres", user=adminuser,
                                 password=adminpass)
            cursor = db.cursor()
            cursor.execute(create_str)
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
      `date`     datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
      `date_end` datetime DEFAULT NULL,
      `sourceId` smallint(5) unsigned NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `filename_idx` (`filename`),
      KEY `date_index` (`sourceId`,`date`) USING BTREE
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
    cursor.execute(
    """CREATE TABLE `datasources` (
      `id`            smallint(5) unsigned NOT NULL,
      `name`          varchar(127) NOT NULL,
      `description`   varchar(255) DEFAULT NULL,
      `units`         varchar(20) DEFAULT NULL,
      `layeringOrder` tinyint(3) unsigned NOT NULL,
      `enabled`       tinyint(1) unsigned NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;""")

    cursor.execute("""
    INSERT INTO `datasources` VALUES
        (0,'EIT 171','SOHO EIT 171','Å',1,1),
        (1,'EIT 195','SOHO EIT 195','Å',1,1),
        (2,'EIT 284','SOHO EIT 284','Å',1,1),
        (3,'EIT 304','SOHO EIT 304','Å',1,1),
        (4,'LASCO C2','SOHO LASCO C2','DN',2,1),
        (5,'LASCO C3','SOHO LASCO C3','DN',3,1),
        (6,'MDI Mag','SOHO MDI Mag','Mx',1,1),
        (7,'MDI Int','SOHO MDI Int','DN',1,1),
        (8,'AIA 94','SDO AIA 94','Å',1,1),
        (9,'AIA 131','SDO AIA 131','Å',1,1),
        (10,'AIA 171','SDO AIA 171','Å',1,1),
        (11,'AIA 193','SDO AIA 193','Å',1,1),
        (12,'AIA 211','SDO AIA 211','Å',1,1),
        (13,'AIA 304','SDO AIA 304','Å',1,1),
        (14,'AIA 335','SDO AIA 335','Å',1,1),
        (15,'AIA 1600','SDO AIA 1600','Å',1,1),
        (16,'AIA 1700','SDO AIA 1700','Å',1,1),
        (17,'AIA 4500','SDO AIA 4500','Å',1,1),
        (18,'HMI Int','SDO HMI Int','DN',1,1),
        (19,'HMI Mag','SDO HMI Mag','Mx',1,1),
        (20,'EUVI-A 171','STEREO A EUVI 171','Å',1,1),
        (21,'EUVI-A 195','STEREO A EUVI 195','Å',1,1),
        (22,'EUVI-A 284','STEREO A EUVI 284','Å',1,1),
        (23,'EUVI-A 304','STEREO A EUVI 304','Å',1,1),
        (24,'EUVI-B 171','STEREO B EUVI 171','Å',1,1),
        (25,'EUVI-B 195','STEREO B EUVI 195','Å',1,1),
        (26,'EUVI-B 284','STEREO B EUVI 284','Å',1,1),
        (27,'EUVI-B 304','STEREO B EUVI 304','Å',1,1),
        (28,'COR1-A','STEREO A COR1','DN',2,1),
        (29,'COR2-A','STEREO A COR2','DN',3,1),
        (30,'COR1-B','STEREO B COR1','DN',2,1),
        (31,'COR2-B','STEREO B COR2','DN',3,1),
        (32,'SWAP 174','PROBA-2 SWAP 174','Å',1,1),
        (33,'SXT AlMgMn','Yohkoh SXT AlMgMn','Å',1,1),
        (34,'SXT thin-Al','Yohkoh SXT thin-Al','Å',1,1),
        (35,'SXT white-light','Yohkoh SXT white-light','',1,1),
        (38,'XRT Al_med/Al_mesh',NULL,NULL,1,1),
        (39,'XRT Al_med/Al_thick',NULL,NULL,1,1),
        (40,'XRT Al_med/Be_thick',NULL,NULL,1,1),
        (41,'XRT Al_med/Gband',NULL,NULL,1,1),
        (42,'XRT Al_med/Open',NULL,NULL,1,1),
        (43,'XRT Al_med/Ti_poly',NULL,NULL,1,1),
        (44,'XRT Al_poly/Al_mesh',NULL,NULL,1,1),
        (45,'XRT Al_poly/Al_thick',NULL,NULL,1,1),
        (46,'XRT Al_poly/Be_thick',NULL,NULL,1,1),
        (47,'XRT Al_poly/Gband',NULL,NULL,1,1),
        (48,'XRT Al_poly/Open',NULL,NULL,1,1),
        (49,'XRT Al_poly/Ti_poly',NULL,NULL,1,1),
        (50,'XRT Be_med/Al_mesh',NULL,NULL,1,1),
        (51,'XRT Be_med/Al_thick',NULL,NULL,1,1),
        (52,'XRT Be_med/Be_thick',NULL,NULL,1,1),
        (53,'XRT Be_med/Gband',NULL,NULL,1,1),
        (54,'XRT Be_med/Open',NULL,NULL,1,1),
        (55,'XRT Be_med/Ti_poly',NULL,NULL,1,1),
        (56,'XRT Be_thin/Al_mesh',NULL,NULL,1,1),
        (57,'XRT Be_thin/Al_thick',NULL,NULL,1,1),
        (58,'XRT Be_thin/Be_thick',NULL,NULL,1,1),
        (59,'XRT Be_thin/Gband',NULL,NULL,1,1),
        (60,'XRT Be_thin/Open',NULL,NULL,1,1),
        (61,'XRT Be_thin/Ti_poly',NULL,NULL,1,1),
        (62,'XRT C_poly/Al_mesh',NULL,NULL,1,1),
        (63,'XRT C_poly/Al_thick',NULL,NULL,1,1),
        (64,'XRT C_poly/Be_thick',NULL,NULL,1,1),
        (65,'XRT C_poly/Gband',NULL,NULL,1,1),
        (66,'XRT C_poly/Open',NULL,NULL,1,1),
        (67,'XRT C_poly/Ti_poly',NULL,NULL,1,1),
        (68,'XRT Mispositioned/Mispositioned',NULL,NULL,1,1),
        (69,'XRT Open/Al_mesh',NULL,NULL,1,1),
        (70,'XRT Open/Al_thick',NULL,NULL,1,1),
        (71,'XRT Open/Be_thick',NULL,NULL,1,1),
        (72,'XRT Open/Gband',NULL,NULL,1,1),
        (73,'XRT Open/Open',NULL,NULL,1,1),
        (74,'XRT Open/Ti_poly',NULL,NULL,1,1),
        (75,'TRACE 171','TRACE 171','Å',1,1),
        (76,'TRACE 195','TRACE 195','Å',1,1),
        (77,'TRACE 284','TRACE 284','Å',1,1),
        (78,'TRACE 1216','TRACE 1216','Å',1,1),
        (79,'TRACE 1550','TRACE 1550','Å',1,1),
        (80,'TRACE 1600','TRACE 1600','Å',1,1),
        (81,'TRACE 1700','TRACE 1700','Å',1,1),
        (82,'TRACE white-light','TRACE white-light','',1,1);""")



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

    cursor.execute("""
    INSERT INTO `datasource_property` VALUES
        (0,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (1,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (2,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (3,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (4,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (5,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (6,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (7,'Observatory','SOHO','SOHO','Solar and Heliospheric Observatory',1),
        (8,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (9,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (10,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (11,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (12,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (13,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (14,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (15,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (16,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (17,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (18,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (19,'Observatory','SDO','SDO','Solar Dynamics Observatory',1),
        (20,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (21,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (22,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (23,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (24,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (25,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (26,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (27,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (28,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (29,'Observatory','STEREO_A','STEREO_A','Solar Terrestrial Relations Observatory Ahead',1),
        (30,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (31,'Observatory','STEREO_B','STEREO_B','Solar Terrestrial Relations Observatory Behind',1),
        (32,'Observatory','PROBA2','PROBA2','Project for OnBoard Autonomy 2',1),
        (33,'Observatory','Yohkoh','Yohkoh','Yohkoh (Solar-A)',1),
        (34,'Observatory','Yohkoh','Yohkoh','Yohkoh (Solar-A)',1),
        (35,'Observatory','Yohkoh','Yohkoh','Yohkoh (Solar-A)',1),
        (0,'Instrument','EIT','EIT','Extreme ultraviolet Imaging Telescope',2),
        (1,'Instrument','EIT','EIT','Extreme ultraviolet Imaging Telescope',2),
        (2,'Instrument','EIT','EIT','Extreme ultraviolet Imaging Telescope',2),
        (3,'Instrument','EIT','EIT','Extreme ultraviolet Imaging Telescope',2),
        (4,'Instrument','LASCO','LASCO','The Large Angle Spectrometric Coronagraph',2),
        (5,'Instrument','LASCO','LASCO','The Large Angle Spectrometric Coronagraph',2),
        (6,'Instrument','MDI','MDI','Michelson Doppler Imager',2),
        (7,'Instrument','MDI','MDI','Michelson Doppler Imager',2),
        (8,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (9,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (10,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (11,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (12,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (13,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (14,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (15,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (16,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (17,'Instrument','AIA','AIA','Atmospheric Imaging Assembly',2),
        (18,'Instrument','HMI','HMI','Helioseismic and Magnetic Imager',2),
        (19,'Instrument','HMI','HMI','Helioseismic and Magnetic Imager',2),
        (20,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (21,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (22,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (23,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (24,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (25,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (26,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (27,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (28,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (29,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (30,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (31,'Instrument','SECCHI','SECCHI','Sun Earth Connection Coronal and Heliospheric Investigation',2),
        (32,'Instrument','SWAP','SWAP','Sun watcher using APS detectors and image processing',2),
        (33,'Instrument','SXT','SXT','Soft X-ray Telescope',2),
        (34,'Instrument','SXT','SXT','Soft X-ray Telescope',2),
        (35,'Instrument','SXT','SXT','Soft X-ray Telescope',2),
        (4,'Detector','C2','C2','Coronograph 2',3),
        (5,'Detector','C3','C3','Coronograph 3',3),
        (20,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (21,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (22,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (23,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (24,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (25,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (26,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (27,'Detector','EUVI','EUVI','Extreme Ultraviolet Imager',3),
        (28,'Detector','COR1','COR1','Coronograph 1',3),
        (29,'Detector','COR2','COR2','Coronograph 2',3),
        (30,'Detector','COR1','COR1','Coronograph 1',3),
        (31,'Detector','COR2','COR2','Coronograph 2',3),
        (0,'Measurement','171','171','171 Ångström extreme ultraviolet',3),
        (1,'Measurement','195','195','195 Ångström extreme ultraviolet',3),
        (2,'Measurement','284','284','284 Ångström extreme ultraviolet',3),
        (3,'Measurement','304','304','304 Ångström extreme ultraviolet',3),
        (4,'Measurement','white-light','white-light','White Light',4),
        (5,'Measurement','white-light','white-light','White Light',4),
        (6,'Measurement','magnetogram','magnetogram','Magnetogram',3),
        (7,'Measurement','continuum','continuum','Intensitygram',3),
        (8,'Measurement','94','94','94 Ångström extreme ultraviolet',3),
        (9,'Measurement','131','131','131 Ångström extreme ultraviolet',3),
        (10,'Measurement','171','171','171 Ångström extreme ultraviolet',3),
        (11,'Measurement','193','193','193 Ångström extreme ultraviolet',3),
        (12,'Measurement','211','211','211 Ångström extreme ultraviolet',3),
        (13,'Measurement','304','304','304 Ångström extreme ultraviolet',3),
        (14,'Measurement','335','335','335 Ångström extreme ultraviolet',3),
        (15,'Measurement','1600','1600','1600 Ångström extreme ultraviolet',3),
        (16,'Measurement','1700','1700','1700 Ångström extreme ultraviolet',3),
        (17,'Measurement','4500','4500','4500 Ångström extreme ultraviolet',3),
        (18,'Measurement','continuum','continuum','Intensitygram',3),
        (19,'Measurement','magnetogram','magnetogram','Magnetogram',3),
        (20,'Measurement','171','171','171 Ångström extreme ultraviolet',4),
        (21,'Measurement','195','195','195 Ångström extreme ultraviolet',4),
        (22,'Measurement','284','284','284 Ångström extreme ultraviolet',4),
        (23,'Measurement','304','304','304 Ångström extreme ultraviolet',4),
        (24,'Measurement','171','171','171 Ångström extreme ultraviolet',4),
        (25,'Measurement','195','195','195 Ångström extreme ultraviolet',4),
        (26,'Measurement','284','284','284 Ångström extreme ultraviolet',4),
        (27,'Measurement','304','304','304 Ångström extreme ultraviolet',4),
        (28,'Measurement','white-light','white-light','White Light',4),
        (29,'Measurement','white-light','white-light','White Light',4),
        (30,'Measurement','white-light','white-light','White Light',4),
        (31,'Measurement','white-light','white-light','White Light',4),
        (32,'Measurement','174','174','174 Ångström extreme ultraviolet',3),
        (33,'Filter','AlMgMn','AlMgMn','Al/Mg/Mn filter (2.4 Å - 32 Å pass band)',3),
        (34,'Measurement','thin-Al','thin-Al','11.6 μm Al filter (2.4 Å - 13 Å pass band)',3),
        (35,'Measurement','white-light','white-light','No filter',3),
        (38,'Observatory','Hinode','Hinode','',1),
        (38,'Instrument','XRT','XRT','',2),
        (38,'Filter Wheel 1','Al_med','Al_med','',3),
        (38,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (39,'Observatory','Hinode','Hinode','',1),
        (39,'Instrument','XRT','XRT','',2),
        (39,'Filter Wheel 1','Al_med','Al_med','',3),
        (39,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (40,'Observatory','Hinode','Hinode','',1),
        (40,'Instrument','XRT','XRT','',2),
        (40,'Filter Wheel 1','Al_med','Al_med','',3),
        (40,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (41,'Observatory','Hinode','Hinode','',1),
        (41,'Instrument','XRT','XRT','',2),
        (41,'Filter Wheel 1','Al_med','Al_med','',3),
        (41,'Filter Wheel 2','Gband','Gband','',4),
        (42,'Observatory','Hinode','Hinode','',1),
        (42,'Instrument','XRT','XRT','',2),
        (42,'Filter Wheel 1','Al_med','Al_med','',3),
        (42,'Filter Wheel 2','Open','Open','',4),
        (43,'Observatory','Hinode','Hinode','',1),
        (43,'Instrument','XRT','XRT','',2),
        (43,'Filter Wheel 1','Al_med','Al_med','',3),
        (43,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (44,'Observatory','Hinode','Hinode','',1),
        (44,'Instrument','XRT','XRT','',2),
        (44,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (44,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (45,'Observatory','Hinode','Hinode','',1),
        (45,'Instrument','XRT','XRT','',2),
        (45,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (45,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (46,'Observatory','Hinode','Hinode','',1),
        (46,'Instrument','XRT','XRT','',2),
        (46,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (46,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (47,'Observatory','Hinode','Hinode','',1),
        (47,'Instrument','XRT','XRT','',2),
        (47,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (47,'Filter Wheel 2','Gband','Gband','',4),
        (48,'Observatory','Hinode','Hinode','',1),
        (48,'Instrument','XRT','XRT','',2),
        (48,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (48,'Filter Wheel 2','Open','Open','',4),
        (49,'Observatory','Hinode','Hinode','',1),
        (49,'Instrument','XRT','XRT','',2),
        (49,'Filter Wheel 1','Al_poly','Al_poly','',3),
        (49,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (50,'Observatory','Hinode','Hinode','',1),
        (50,'Instrument','XRT','XRT','',2),
        (50,'Filter Wheel 1','Be_med','Be_med','',3),
        (50,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (51,'Observatory','Hinode','Hinode','',1),
        (51,'Instrument','XRT','XRT','',2),
        (51,'Filter Wheel 1','Be_med','Be_med','',3),
        (51,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (52,'Observatory','Hinode','Hinode','',1),
        (52,'Instrument','XRT','XRT','',2),
        (52,'Filter Wheel 1','Be_med','Be_med','',3),
        (52,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (53,'Observatory','Hinode','Hinode','',1),
        (53,'Instrument','XRT','XRT','',2),
        (53,'Filter Wheel 1','Be_med','Be_med','',3),
        (53,'Filter Wheel 2','Gband','Gband','',4),
        (54,'Observatory','Hinode','Hinode','',1),
        (54,'Instrument','XRT','XRT','',2),
        (54,'Filter Wheel 1','Be_med','Be_med','',3),
        (54,'Filter Wheel 2','Open','Open','',4),
        (55,'Observatory','Hinode','Hinode','',1),
        (55,'Instrument','XRT','XRT','',2),
        (55,'Filter Wheel 1','Be_med','Be_med','',3),
        (55,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (56,'Observatory','Hinode','Hinode','',1),
        (56,'Instrument','XRT','XRT','',2),
        (56,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (56,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (57,'Observatory','Hinode','Hinode','',1),
        (57,'Instrument','XRT','XRT','',2),
        (57,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (57,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (58,'Observatory','Hinode','Hinode','',1),
        (58,'Instrument','XRT','XRT','',2),
        (58,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (58,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (59,'Observatory','Hinode','Hinode','',1),
        (59,'Instrument','XRT','XRT','',2),
        (59,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (59,'Filter Wheel 2','Gband','Gband','',4),
        (60,'Observatory','Hinode','Hinode','',1),
        (60,'Instrument','XRT','XRT','',2),
        (60,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (60,'Filter Wheel 2','Open','Open','',4),
        (61,'Observatory','Hinode','Hinode','',1),
        (61,'Instrument','XRT','XRT','',2),
        (61,'Filter Wheel 1','Be_thin','Be_thin','',3),
        (61,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (62,'Observatory','Hinode','Hinode','',1),
        (62,'Instrument','XRT','XRT','',2),
        (62,'Filter Wheel 1','C_poly','C_poly','',3),
        (62,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (63,'Observatory','Hinode','Hinode','',1),
        (63,'Instrument','XRT','XRT','',2),
        (63,'Filter Wheel 1','C_poly','C_poly','',3),
        (63,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (64,'Observatory','Hinode','Hinode','',1),
        (64,'Instrument','XRT','XRT','',2),
        (64,'Filter Wheel 1','C_poly','C_poly','',3),
        (64,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (65,'Observatory','Hinode','Hinode','',1),
        (65,'Instrument','XRT','XRT','',2),
        (65,'Filter Wheel 1','C_poly','C_poly','',3),
        (65,'Filter Wheel 2','Gband','Gband','',4),
        (66,'Observatory','Hinode','Hinode','',1),
        (66,'Instrument','XRT','XRT','',2),
        (66,'Filter Wheel 1','C_poly','C_poly','',3),
        (66,'Filter Wheel 2','Open','Open','',4),
        (67,'Observatory','Hinode','Hinode','',1),
        (67,'Instrument','XRT','XRT','',2),
        (67,'Filter Wheel 1','C_poly','C_poly','',3),
        (67,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (68,'Observatory','Hinode','Hinode','',1),
        (68,'Instrument','XRT','XRT','',2),
        (68,'Filter Wheel 1','Mispositioned','Mispositioned','',3),
        (68,'Filter Wheel 2','Mispositioned','Mispositioned','',4),
        (69,'Observatory','Hinode','Hinode','',1),
        (69,'Instrument','XRT','XRT','',2),
        (69,'Filter Wheel 1','Open','Open','',3),
        (69,'Filter Wheel 2','Al_mesh','Al_mesh','',4),
        (70,'Observatory','Hinode','Hinode','',1),
        (70,'Instrument','XRT','XRT','',2),
        (70,'Filter Wheel 1','Open','Open','',3),
        (70,'Filter Wheel 2','Al_thick','Al_thick','',4),
        (71,'Observatory','Hinode','Hinode','',1),
        (71,'Instrument','XRT','XRT','',2),
        (71,'Filter Wheel 1','Open','Open','',3),
        (71,'Filter Wheel 2','Be_thick','Be_thick','',4),
        (72,'Observatory','Hinode','Hinode','',1),
        (72,'Instrument','XRT','XRT','',2),
        (72,'Filter Wheel 1','Open','Open','',3),
        (72,'Filter Wheel 2','Gband','Gband','',4),
        (73,'Observatory','Hinode','Hinode','',1),
        (73,'Instrument','XRT','XRT','',2),
        (73,'Filter Wheel 1','Open','Open','',3),
        (73,'Filter Wheel 2','Open','Open','',4),
        (74,'Observatory','Hinode','Hinode','',1),
        (74,'Instrument','XRT','XRT','',2),
        (74,'Filter Wheel 1','Open','Open','',3),
        (74,'Filter Wheel 2','Ti_poly','Ti_poly','',4),
        (75,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (75,'Measurement','171','171','TRACE 171',2),
        (76,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (76,'Measurement','195','195','TRACE 195',2),
        (77,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (77,'Measurement','284','284','TRACE 284',2),
        (78,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (78,'Measurement','1216','1216','TRACE 1216',2),
        (79,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (79,'Measurement','1550','1550','TRACE 1550',2),
        (80,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (80,'Measurement','1600','1600','TRACE 1600',2),
        (81,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (81,'Measurement','1700','1700','TRACE 1700',2),
        (82,'Observatory','TRACE','TRACE','Transition Region and Coronal Explorer',1),
        (82,'Measurement','white-light','WL','TRACE white-light',2);""")

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
      `timestamp`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `reqStartDate`      datetime NOT NULL,
      `reqEndDate`        datetime NOT NULL,
      `imageScale`        FLOAT NOT NULL,
      `regionOfInterest`  POLYGON NOT NULL,
      `maxFrames`         SMALLINT NOT NULL,
      `watermark`         TINYINT(1) UNSIGNED NOT NULL,
      `dataSourceString`  VARCHAR(255) NOT NULL,
      `dataSourceBitMask` BIGINT UNSIGNED,
      `eventSourceString` VARCHAR(1024) DEFAULT NULL,
      `eventsLabels`      TINYINT(1) UNSIGNED NOT NULL,
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
      `numFrames`         SMALLINT UNSIGNED,
      `width`             SMALLINT UNSIGNED,
      `height`            SMALLINT UNSIGNED,
      `buildTimeStart`    TIMESTAMP,
      `buildTimeEnd`      TIMESTAMP,
       PRIMARY KEY (`id`)
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
       PRIMARY KEY (`id`)
    ) DEFAULT CHARSET=utf8;""")

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
      `shared`      TINYINT(1) UNSIGNED NOT NULL,
       PRIMARY KEY (`id`),
       UNIQUE INDEX movieid_idx(movieId)
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
      `scale`             TINYINT(1) unsigned NOT NULL DEFAULT '0',
      `scaleType`         VARCHAR(12) DEFAULT 'earth',
      `scaleX`            FLOAT DEFAULT '0',
      `scaleY`            FLOAT DEFAULT '0',
      `numLayers`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
       PRIMARY KEY (`id`)
    ) DEFAULT CHARSET=utf8;""")

def create_statistics_table(cursor):
    """Creates a table to keep query statistics

    Creates a simple table for storing query statistics for selected types of
    requests
    """
    cursor.execute("""
    CREATE TABLE `statistics` (
      `id`          INT unsigned NOT NULL auto_increment,
      `timestamp`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `action`      VARCHAR(32)  NOT NULL,
       PRIMARY KEY (`id`),
       KEY `date_idx` (`timestamp`,`action`)
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


def enable_datasource(cursor, sourceId):
    """Enables datasource

    Marks a single datasource as enabled to signal that there is data for that
    source
    """
    cursor.execute("UPDATE datasources SET enabled=1 WHERE id=%d;" % sourceId)

def update_image_table_index(cursor):
    """Updates index on data table"""
    cursor.execute("OPTIMIZE TABLE data;")

def mark_as_corrupt(cursor, filename, note):
    """Adds an image to the 'corrupt' database table"""
    sql = "INSERT INTO corrupt VALUES (NULL, NULL, '%s', '%s');" % (filename,
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

    for i,letter in letters.iteritems():
        sql += ', '
        sql += letter+'.name AS '+letter+'_name'

    sql += ' FROM datasources s '

    for i,letter in letters.iteritems():
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
