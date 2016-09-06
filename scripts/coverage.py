#!/usr/bin/env python
#-*- coding:utf-8 -*-

# Licensed under MOZILLA PUBLIC LICENSE Version 1.1
# Author: Keith Hughitt <keith.hughitt@nasa.gov>

"""Helioviewer.org Database Coverage Plotter"""

# Python imports
import sys
import os
import errno
import datetime
import getpass
import mysql.connector
from matplotlib import pyplot
from numpy import std, median

def main(argv):
    """Main application"""
    printGreeting()

    dbhost, dbname, dbuser, dbpass = getDatabaseInfo()

    db = mysql.connector.connect(use_unicode=True, charset = "utf8", host=dbhost, database=dbname, user=dbuser, password=dbpass)
    cursor = db.cursor()

    sources = getDataSources(cursor)
        
    # Setup directory structure to write graphs to
    try:
        createDirectories(sources)
    except:
        print("Unable to create directories.")
        sys.exit()
 
    numDays = int(input("How many days per graph? "))
    timeIncrement = datetime.timedelta(days=numDays)
    
    now  = datetime.datetime.now()
    
    # For each data source
    for name,sourceId in sources.items():
        print("Processing: " + name)
        date = getDataSourceStartDate(sourceId, cursor)
        
        # For each n day block from the start date until present
        while (date < now):
            startDate = date
            date = date + timeIncrement

            # Find and plot the number of images per day            
            dates,freqs = getFrequencies(cursor, sourceId, startDate, date)
            
            filename = "%s/%s_%s-%s.svg" % (name, name, 
                startDate.strftime("%Y%m%d"), date.strftime("%Y%m%d"))
            filename = filename.replace(" ", "_")

            plotFrequencies(name, filename, dates, freqs)
            
    print("Finished!")
    print("Cleaning up and exiting...")
        
def createDirectories(sources):
    """Creates a directory structure to use for storing the coverage graphs."""
    dir = "Helioviewer_Coverage_" + datetime.datetime.now().strftime("%Y%m%d")

    try:
        os.mkdir(dir)
    except OSError as exc:
        if exc.errno != errno.EEXIST:
            raise exc
        pass

    os.chdir(dir)

    for name,sourceId in sources.items():
        try:
            os.mkdir(name.replace(" ", "_"))
        except OSError as exc:
            if exc.errno != errno.EEXIST:
                raise exc
            pass

def getDatabaseInfo():
    """Prompts the user for database information"""
    while True:
        print ("Please enter database information:")
        dbhost = input("    Hostname [localhost]: ") or "localhost"
        dbname = input("    Database [helioviewer]: ") or "helioviewer"
        dbuser = input("    Username [helioviewer]: ") or "helioviewer"
        dbpass = getpass.getpass("    Password: ")
        
        if not checkDBInfo(dbhost, dbname, dbuser, dbpass):
            print ("Unable to connect to the database. "
                   "Please check your login information and try again.")
        else:
            return dbhost, dbname, dbuser,dbpass
                
def checkDBInfo(dbhost, dbname, dbuser, dbpass):
    """Validates database login information"""
    try:
        db = mysql.connector.connect(host=dbhost, database=dbname, user=dbuser, password=dbpass)
    except mysql.connector.Error as e:
        print(e)
        return False

    db.close()
    return True

def getDataSourceStartDate(sourceId, cursor):
    """Returns a datetime object for the beginning of the first day 
       where data was available for a given source id
    """
    cursor.execute("""SELECT date FROM data 
                      WHERE sourceId = %d 
                      ORDER BY date ASC LIMIT 1;""" % sourceId)
    
    return cursor.fetchone()[0].replace(hour=0,minute=0,second=0,microsecond=0)
         
def getDataSources(cursor):
    """Returns a list of datasources to query"""
    cursor.execute("SELECT name, id FROM datasources")
    datasources = {}
    
    # Get data sources
    for ds in cursor.fetchall():
        name = ds[0]
        sourceId   = int(ds[1])
        
        # Only include datasources which for images exist in the database
        cursor.execute("""SELECT COUNT(*) FROM data 
                          WHERE sourceId=%d""" % sourceId)
        count = cursor.fetchone()[0]
        
        if count > 0:
            datasources[name] = sourceId
       
    return datasources
        
def getFrequencies(cursor, sourceId, startDate, endDate):
    """Returns arrays containing the dates queried and the counts for 
       each of those days.
    """
    # Get counts for each day
    freqs = []
    dates = []
    day   = datetime.timedelta(days=1)
    
    date = startDate

    while date <= endDate:
        sql = """SELECT COUNT(*) FROM data
                 WHERE date BETWEEN '%s' AND '%s' 
                 AND sourceId = %d;""" % (date, date + day, sourceId)
        cursor.execute(sql)
        n = int(cursor.fetchone()[0])
        freqs.append(n)
        dates.append(date)
        date += day
        
    return dates,freqs
    
def plotFrequencies(name, filename, dates, freqs):
    """Creates a histogram representing the data counts for each day"""
    # Mean, median, and standard deviation  
    numDays = len(freqs)
    avg     = sum(freqs) / numDays
    med     = median(freqs)
    sigma   = std(freqs)
    
    # Plot results
    fig = pyplot.figure()
    ax = fig.add_subplot(111)
    ax.plot(dates, freqs, color='limegreen')
    fig.autofmt_xdate()
   
    pyplot.xlabel('Time')
    pyplot.ylabel('Number of Images (per day)')
    
    title = r'$\mathrm{%s\ Coverage:}\ n=%d,\ \bar{x}=%.5f,\ x_{1/2}=%.5f,\ \hat{\sigma}=%.5f$' % (name, numDays, avg, med, sigma)
    pyplot.title(title)
    #pyplot.axis([0, 0.05, 0, 1])
    pyplot.grid(True)

    #pyplot.show()
    pyplot.savefig(filename, format="svg")
    
def printGreeting():
    """Displays a greeting message"""
    print("""
        Helioviewer Database Coverage Plotter
        
        This script scans a Helioviewer image database and creates histograms
        depicting the data coverage across the different datasource lifetimes.
        Each column in the graph shows the number of images that were found for
        that day.
        
        Author : Keith Hughitt <keith.hughitt@nasa.gov>
        Last update: Feb 18, 2011
    """)
    
if __name__ == '__main__':
    sys.exit(main(sys.argv))


