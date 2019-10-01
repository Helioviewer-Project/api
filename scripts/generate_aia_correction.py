#!/usr/bin/env python3
import os, sys, datetime, csv, json, numpy as np
from PIL import Image, ImageStat
from datetime import date, timedelta

def main():
    """Main Application"""
    waves = ['94','131','171','193','211','304','335','1600','1700']
    images = dict()

    root_path = input("Path to AIA folder:")
    totalFiles = 0
    for wave in waves: # for each wave
        numFiles = 0
        wave_path = root_path + wave
        for root, dirs, files in os.walk(wave_path): # traverse the root_path
            for file_ in files:
                if wave in file_:
                    if file_.endswith('.jp2'):
                        try:
                            images[wave].append(os.path.join(root, file_))
                        except KeyError:
                            images[wave] = []
                            images[wave].append(os.path.join(root, file_))
                        numFiles = numFiles + 1
                        # print 'Found '+str(numFiles) +' files for wave '+ wave
                        # sys.stdout.write("\033[F")
                        break
                else:
                    break
        totalFiles = totalFiles + numFiles
        print 'Found '+str(numFiles) +' files for wave '+ wave

    print 'Found '+str(totalFiles) +' total files'
    #print images
    for wave in waves:
        print 'Processing '+str(wave)
        #write_paths( images[wave], wave )
        try:
            write_csv( images[wave], wave )
        except KeyError:
            print "No files for wave "+ wave

    # for wave in waves:
        # currently smoothing is done manually
        # sort_csv( wave )
        # smooth_csv( wave )
        
    #run this function alone after smoothing csv values
    #make_json( waves )

def running_mean(x, N):
    cumsum = np.convolve(x, np.ones((N,))/N, mode='same')
    return cumsum

def smooth_csv( wave ):
    csvInput = open("./aia_correction_data/aia_correction_data_"+str(wave)+"_sorted.csv", "r")
    csvOutput = open("./aia_correction_data/aia_correction_data_"+str(wave)+"_smoothed.csv", "w")

    print "smoothing "+str(wave)
    reader = csv.reader(csvInput)
    # sort using lambda ( row[1] is the date field )
    # sorted_csv = sorted(reader, key=lambda row: row[1], reverse=False)
    sorted_csv = reader

    # isolate the data to be smoothed
    input_data = []
    for row in sorted_csv:
        input_data.append(float(row[2]))
    
    running_average_data = running_mean(input_data,60)

    for x in range(len(running_average_data)):
        sorted_csv[x][2] = str(running_average_data[x])

    csv_writer = csv.writer(csvOutput,delimiter=',')
    csv_writer.writerows(sorted_csv)

def make_json( waves ):
    csvfile = open('./aia_correction_data/aia_correction_data_smoothed.csv', 'r')
    jsonfile = open('./aia_correction_data/aia_correction_data.json', 'w')

    data = dict({})
    data_max = dict({})
    for wave in waves:
        data_max[wave] = 0.0

    fieldnames = ("path","date","rms")
    reader = csv.DictReader( csvfile, fieldnames)
    for row in reader:
        #determine current wave from path info in csv
        current_wave = waves[0]
        current_date = str(row["date"])
        for wave in waves:
            if wave in row["path"]:
                current_wave = wave
            
        rms = row["rms"]

        data_max_rms = data_max[current_wave]

        if rms > data_max_rms:
            data_max[current_wave] = rms
        
        try:
            data[current_date][current_wave] = row["rms"]
        except KeyError:
            data[current_date] = {}
            data[current_date][current_wave] = row["rms"]
        
    data["max"] = data_max
    print( data )
    json.dump(data, jsonfile)
    jsonfile.write('\n')
    jsonfile.close()

def write_csv( data , wave):
    f=open("./aia_correction_data/aia_correction_data_"+str(wave)+".csv","a")
    size = len(data)
    i=0
    for path in data:
        i=i+1
        percent = (float(i)/float(size)) * 100.0
        progress = " ["+ str(round(percent,2)) + "%]"
        string = path + ',' + get_date("'"+path+"'") + ',' + str(rms_brightness(path))
        f.write(string + '\n')
        print string + progress
    f.close()

def sort_csv( wave ):
    csvInput = open("./aia_correction_data/aia_correction_data_"+str(wave)+".csv", "r")
    csvOutput = open("./aia_correction_data/aia_correction_data_"+str(wave)+"_sorted.csv", "w")

    print "sorting "+str(wave)
    reader = csv.reader(csvInput)
    # sort using lambda ( row[1] is the date field )
    sorted_csv = sorted(reader, key=lambda row: row[1], reverse=False)
    # fill in missing dates
    missing = fill_missing_dates(sorted_csv , wave)
    for date in missing:
        sorted_csv.append(['-',str(date),'-'])

    sorted_csv = sorted(sorted_csv, key=lambda row: row[1], reverse=False)
    # fill missing dates with last known data
    sorted_csv = fill_last_known_data( sorted_csv )

    sorted_csv = remove_outliers( sorted_csv )

    # write sorted file
    csv_writer = csv.writer(csvOutput,delimiter=',')
    csv_writer.writerows(sorted_csv)

def fill_missing_dates( data, wave):
    sorted_csv_dates = []
    for row in data:
        row_date_list = row[1].split('-') # split ISO date format
        sorted_csv_dates.append( date( int(row_date_list[0]), int(row_date_list[1]), int(row_date_list[2]) ) )

    date_set_range = set(sorted_csv_dates[0] + timedelta(x) for x in range((sorted_csv_dates[-1] - sorted_csv_dates[0]).days))

    missing = date_set_range - set(sorted_csv_dates)

    print(missing)

    return missing

def fill_last_known_data( data ):
    # rudamentary way to fill with last known date, iterate and fill forward
    prefix = 'missing data, last known:'

    last_file = '-'
    last_brightness = '-'
    for i, row in enumerate(data):
        if row[0] == '-':
            data[i][0]=str(prefix+last_file)
        else:
            last_file = data[i][0]
        if row[2] == '-':
            data[i][2]=last_brightness
        else:
            last_brightness = data[i][2]

    return data

def remove_outliers( data ):
    dataset = []
    for row in data:
        dataset.append(float(row[2]))
    dataset.sort()
    size = len( dataset )
    size25 = int( size*0.25 )
    size75 = int( size*0.75 )
    Q1 = float( dataset[size25] )
    Q3 = float( dataset[size75] )

    IQR = abs(Q3 - Q1)
    outlier_range = IQR * 4
    outlier_range_upper = outlier_range + Q3
    outlier_range_lower = Q1 - outlier_range

    print Q1, Q3, outlier_range_upper, outlier_range_lower

    for i, row in enumerate(data):
        value = float(row[2])
        if value > outlier_range_upper or value < outlier_range_lower:
            data[i][2] = '-'

    return data

def write_paths( data , wave ):
    p=open("./aia_correction_data/aia_correction_paths_"+str(wave)+".txt","w")
    for path in data:
        p.write(path + "\n")
    p.close

def get_date ( path ):
    loc = path.find('_') - 4
    date = path[loc:loc+10]
    date = date.replace('_','-')
    return date

def rms_brightness( im_file ):
    im = Image.open(im_file).convert('L')
    stat = ImageStat.Stat(im)
    return stat.rms[0]

def median_brightness( im_file ):
    im = Image.open(im_file).convert('L')
    stat = ImageStat.Stat(im)
    return stat.median[0]

def mean_brightness( im_file ):
    im = Image.open(im_file).convert('L')
    stat = ImageStat.Stat(im)
    return stat.mean[0]

def min_max( im_file ):
    im = Image.open(im_file).convert('L')
    stat = ImageStat.Stat(im)
    return stat.extrema

if __name__ == "__main__":
    sys.exit(main())
