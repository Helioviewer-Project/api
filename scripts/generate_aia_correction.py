#!/usr/bin/env python3
import os, sys, datetime, csv, json, numpy
from PIL import Image, ImageStat

def main():
    """Main Application"""
    waves = ['304']
    images = []

    root_path = input("Path to AIA folder:")
    #root_path = '/var/www/jp2/AIA'
    numFiles = 0
    for wave in waves: # for each wave
        for root, dirs, files in os.walk(root_path): # traverse the root_path
            for file_ in files:
                if wave in file_:
                    if file_.endswith('.jp2'):
                        images.append(os.path.join(root, file_))
                        numFiles = numFiles + 1
                        print 'Found '+str(numFiles) +' files'
                        sys.stdout.write("\033[F")
                        break
                else:
                    break

    print 'Found '+str(numFiles) +' files'
    write_paths( images )
    write_csv( images )
    
    # currently smoothing is done manually
    #smooth_csv( waves )

    #run this function alone after smoothing csv values
    #make_json( waves )

def running_mean(x, N):
    cumsum = numpy.cumsum(numpy.insert(x, 0, 0)) 
    return (cumsum[N:] - cumsum[:-N]) / float(N)

def smooth_csv( images, waves )
    csvInput = open('aia_correction_data.csv', 'r')
    csvOutput = open('aia_correction_data_smoothed.csv', 'w')


def make_json( waves ):
    csvfile = open('aia_correction_data_smoothed.csv', 'r')
    jsonfile = open('aia_correction_data.json', 'w')

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

def write_csv( data ):
    f=open("aia_correction_data.csv","a")
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

def write_paths( data ):
    p=open("aia_correction_paths.txt","w")
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
