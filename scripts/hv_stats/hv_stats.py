#!/usr/bin/env python
# coding: utf-8

# In[1]:


# %load_ext autoreload
# %autoreload 2
import os
import sys
import numpy as np
from scipy import stats
import time
import datetime
import pandas as pd
import csv

import MySQLdb as mysqldb

from bokeh.plotting import *
from bokeh.layouts import gridplot
from bokeh.models import *# Span, ColumnDataSource, LogColorMapper, ColorMapper, LogTicker, ColorBar, BasicTicker, LinearColorMapper, PrintfTickFormatter, HoverTool, CategoricalColorMapper, Range1d, Title
from bokeh.models.widgets import Tabs, Panel
from bokeh.io import show, output_notebook, reset_output
# output_notebook()
from bokeh.models.glyphs import Text
import bokeh.palettes as bp
from bokeh.transform import factor_cmap

import json
import urllib

import matplotlib
matplotlib.use('Agg')

import matplotlib.pyplot as plt

from matplotlib.ticker import (MultipleLocator, FormatStrFormatter, AutoMinorLocator)

from pathlib import Path
from joblib import Parallel, delayed

import warnings
warnings.filterwarnings("ignore")
import configparser
# from hv_setup import *

weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']


# In[2]:


master_time = time.time()


# In[3]:


# get credentials for accessing sql database
cred={}
exists = os.path.exists("/var/www/api.helioviewer.org/install/settings/settings.cfg")
if exists:
    configFilePath = "/var/www/api.helioviewer.org/install/settings/settings.cfg"
    configParser = configparser.RawConfigParser()
    configParser.read(configFilePath)
elif os.path.exists("/home/varun/cred.cfg"):
    configFilePath = "/home/varun/cred.cfg"
    configParser = configparser.RawConfigParser()
    configParser.read(configFilePath)
else:
    print("ERROR: Please provide a config file with database credentials.")
    sys.exit()

cred['dbhost'] = configParser.get('database', 'dbhost')
cred['dbname'] = configParser.get('database', 'dbname')
cred['dbuser'] = configParser.get('database', 'dbuser')
cred['dbpass'] = configParser.get('database', 'dbpass')

def skip_empty_table(table, title):
    """
    Prints a message to indicate this table is empty and returns
    true if it is empty. This is for handling newer servers that don't
    have any data yet.
    """
    if table.empty:
        print("Skipping empty table: %s" % title)
    return table.empty

# define sql_query to return results as pandas dataframes
def sql_query(sql_select_Query):

    '''Return data frame obtained with SQL query in the database hv
    '''
    try: # connection
        records = []
        column_names= ['name']
        connection = mysqldb.connect(host=cred['dbhost'],
                                     database=cred['dbname'],
                                     user=cred['dbuser'],
                                     password=cred['dbpass'])
        cursor = connection.cursor()
        cursor.execute(sql_select_Query)
        records = cursor.fetchall()
        column_names = [i[0] for i in cursor.description] # extract columns
    except mysqldb.Error as e:
        print("Error reading data from MySQL table", e)
    finally:
        connection.close()
        return pd.DataFrame(records, columns=column_names)


# preapring hv dataframe
def hv_prepare(df, sourceId, obs=None):
    if(df.empty):
        df['SOURCE_ID']=[]
        return df
    df = df.sort_values('date').reset_index(drop=True)
    df['date'] = pd.to_datetime(df['date']) # convert string dates to datetime
    df = df.set_index('date')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='D').to_period('D').to_timestamp(),
                fill_value=0) # fill date gaps with 0
    df = df.reindex(pd.date_range(df.index.min().replace(day=1), (df.index.max() + pd.tseries.offsets.MonthEnd(1)), freq='D').to_period('D').to_timestamp(),
                fill_value=-1) # fill with dummy dates to complete first and last month and fill with -1
    df['count'] = df['count'].astype(int)
    df['date'] = df.index
    df = df.reset_index(drop=True)
    df.loc[df['count']<0, 'count'] = np.nan # change count of dummy dates to nan
    df['Year'] = df['date'].dt.year.astype(str) + ' ' + df['date'].dt.month_name() # Year column with year month
    df['Day'] = df['date'].dt.day.astype(str) # day of the month
    df['SOURCE_ID'] = sourceId
    df['OBS'] = obs
    return df

# major solar features
def major_features(pp, df):
    t1 = pd.Timestamp('2011/06/07')
    if((t1 >= df['date'].min()) & (t1 <= df['date'].max())):
        pp.line(y=np.arange(0, np.nanmax(df['count'])+1), x=t1, line_width=1.5, line_dash='dotdash', color='red', alpha=1, legend_label= "Failed eruption (2011/06/07)")
    t1 = pd.Timestamp('2013/11/28')
    if((t1 >= df['date'].min()) & (t1 <= df['date'].max())):
        pp.line(y=np.arange(0, np.nanmax(df['count'])+1), x=t1, line_width=1.5, line_dash='dotdash', color='purple', alpha=1, legend_label= "Comet ISON (2013/11/28)")
    t1 = pd.Timestamp('2017/09/06')
    t2 = pd.Timestamp('2017/09/10')
    if((t1 >= df['date'].min()) & (t2 <= df['date'].max())):
        pp.harea(y=np.arange(0, np.nanmax(df['count'])+1), x1=t1, x2=t2, fill_color='teal', fill_alpha=1, legend_label= "large flares (2017/09/06-09)")
    return pp

# service interruptions in helioviewer
def service_pause(pp, df):
    t1 = pd.Timestamp('2011/08/11')
    t2 = pd.Timestamp('2011/09/18')
    if((t1 >= df['date'].min()) & (t2 <=df['date'].max())):
        pp.harea(y=np.arange(0, np.nanmax(df['count'])+1), x1=t1, x2=t2, fill_color='gray', fill_alpha=0.3, legend_label= "GSFC server repair (2011/08/11 - 2011/09/18)")
    t1 = pd.Timestamp('2013/10/01')
    t2 = pd.Timestamp('2013/10/16')
    if((t1 >= df['date'].min()) & (t2 <=df['date'].max())):
        pp.harea(y=np.arange(0, np.nanmax(df['count'])+1), x1=t1, x2=t2, fill_color='green', fill_alpha=0.3, legend_label= "U.S. Fed. Gov. shutdown (2013/10/01 - 2013/10/16)")
    t1 = pd.Timestamp('2015/02/04')
    t2 = pd.Timestamp('2015/09/23')
    if((t1 >= df['date'].min()) & (t2 <=df['date'].max())):
        pp.harea(y=np.arange(0, np.nanmax(df['count'])+1), x1=t1, x2=t2, fill_color='red', fill_alpha=0.1, legend_label= "GSFC server down (2015/02/04 - 2015/09/23)")
    return pp

# auto bin width such that the total number of bins are no less than ~36
def bin_width(m):
    n = np.int(np.log10(m+1))
    n = 10**(n-1)
    q = np.ceil(m/(n*(36))).astype(int)
    bw = max(q*n,1)
    return bw#, m//n+1


# In[4]:


json_url = urllib.request.urlopen('https://api.helioviewer.org/?action=getDataSources')
hv_keys = json.loads(json_url.read())

hv_sid = pd.DataFrame(columns=['OBS','SOURCE_ID'])

# while sid=='sourceId':
#     key=hv_keys.keys()

for key1 in hv_keys.keys():
    for key2 in hv_keys[key1].keys():
        if 'sourceId' in hv_keys[key1][key2].keys():
            hv_sid.loc[len(hv_sid)] = " ".join([key1, key2]), hv_keys[key1][key2]['sourceId']
        else:
            for key3 in hv_keys[key1][key2].keys():
                if 'sourceId' in hv_keys[key1][key2][key3].keys():
                    hv_sid.loc[len(hv_sid)] = " ".join([key1, key2, key3]), hv_keys[key1][key2][key3]['sourceId']
                else:
                    for key4 in hv_keys[key1][key2][key3].keys():
                        if 'sourceId' in hv_keys[key1][key2][key3][key4].keys():
                            hv_sid.loc[len(hv_sid)] = " ".join([key1, key2, key3, key4]), hv_keys[key1][key2][key3][key4]['sourceId']
                        else:
                            for key5 in hv_keys[key1][key2][key3][key4].keys():
                                if 'sourceId' in hv_keys[key1][key2][key3][key4][key5].keys():
                                    hv_sid.loc[len(hv_sid)] = " ".join([key1, key2, key3, key4, key5]), hv_keys[key1][key2][key3][key4][key5]['sourceId']
                                else:
                                    for key6 in hv_keys[key1][key2][key3][key4][key5].keys():
                                        if 'sourceId' in hv_keys[key1][key2][key3][key4][key5][key6].keys():
                                            hv_sid.loc[len(hv_sid)] = " ".join([key1, key2, key3, key4, key5,key6]), hv_keys[key1][key2][key3][key4][key5][key6]['sourceId']

hv_sid = hv_sid.sort_values(['SOURCE_ID']).reset_index(drop=True)


# In[5]:


# add data freq column to hv_sid; data_freq is total seconds between two hv jp2 images
hv_sid['DATA_FREQ'] = 0

hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['94', '131', '171', '193', '211', '304', '335']))), 'DATA_FREQ'] = 36
hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['1600', '1700']))), 'DATA_FREQ'] = 48
hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['4500']))), 'DATA_FREQ'] = 3600
hv_sid.loc[(hv_sid['OBS'].str.contains('SDO HMI')), 'DATA_FREQ'] = 45

# hv_sid = hv_sid.set_index('SOURCE_ID')
# hv_sid = hv_sid.reindex(range(hv_sid.index.min(), hv_sid.index.max()+1), fill_value='Empty').reset_index()
# hv_sid


# In[6]:


# add columns for date of last observation to hv_sid in onder to stop data gap filling after mission has ended
# it is also used to calculate expected files in the middle of the day
hv_sid['LAST_DATE'] = pd.Timestamp('now')

for ind, df in hv_sid.iterrows():
    hv_sid['LAST_DATE'].iloc[ind] = pd.to_datetime(sql_query("SELECT MAX(date) FROM data WHERE sourceId={}".format(df['SOURCE_ID'])).values[0][0])


# # DATA VISUALIZATION

# In[ ]:


print("Starting SQL query for table data in hv database...")
def sql_hv(ind, sourceId, obs=None):
    query = "SELECT date_format(date, '%Y-%m-%d 00:00:00') as date, count(*) as count FROM data FORCE INDEX (date_index) WHERE sourceId={} GROUP BY date_format(date, '%Y-%m-%d 00:00:00');".format(sourceId)
    df_query = sql_query(query)
    return hv_prepare(df_query, sourceId, obs)

par = Parallel(n_jobs=20)
start_time=time.time()
# results = par(delayed(sql_hv)(ind, df_obs['SOURCE_ID'], df_obs['OBS']) for ind, df_obs in hv_sid.iloc[:].iterrows())
results=[]
for ind, df in hv_sid.iloc[:].iterrows():
#     print(ind, df['SOURCE_ID'])
    results.append(sql_hv(ind, df['SOURCE_ID'], df['OBS']))
print("Querying completed in %d seconds."%(time.time()-start_time))


# In[ ]:


hv = {}
for (i, df), (ind, sid) in zip(enumerate(results), hv_sid.iterrows()):
    if df.empty:
        hv_sid = hv_sid.drop(index = ind)
        continue
    hv[df['SOURCE_ID'].unique()[0]] = df


# ## Data Gaps

# In[ ]:


# hv = {}
# hv_sid = hv_sid.copy()
# for i in range(len(hv_sid)):
#     if results[i].empty:
#         hv_sid = hv_sid.drop(index = hv_sid.index[hv_sid.index == i])
#         continue
#     hv[hv_sid.iloc[i]['SOURCE_ID']] = results[i]
# # ind = np.random.choice(list(hv.keys())),
# hv[np.random.choice(list(hv.keys()))]


# ### All AIA instrument gaps combined

# In[ ]:


gapfill_percentage = 80


# In[ ]:


df_gaps=pd.DataFrame()
h = hv_sid.loc[hv_sid['OBS'].str.match('SDO AIA')].iloc[:].reset_index(drop=True)
for ind, df_obs in h.iterrows():
    sid = df_obs['SOURCE_ID']
    df = hv[sid].copy()#.dropna()

    name = df_obs['OBS']
    name_ = name.replace(" ", "_")

    df = df.dropna().reset_index(drop=True)
    index_latest = df.loc[df['date']==df_obs['LAST_DATE'].to_period('D').to_timestamp()].index[0]
    df_latest = df.iloc[index_latest]
    df = df.drop(index=index_latest)
    freq_perday = (pd.Timedelta(days=1)/pd.Timedelta(seconds=df_obs['DATA_FREQ']))
    gap_threshold = gapfill_percentage / 100 * freq_perday
    df = df.loc[df['count'] <= gap_threshold].reset_index(drop=True)
    df_gaps = pd.concat([df_gaps, df]).reset_index(drop=True)

    if not df_latest.empty:
        gap_threshold = (df_obs['LAST_DATE'] - df_latest['date']) / pd.Timedelta(days=1) * freq_perday * gapfill_percentage / 100
        if ((df_latest['count'] < gap_threshold).any()):
            df_gaps = pd.concat([df_gaps, df_latest]).reset_index(drop=True)


# In[ ]:


df_gaps = pd.DataFrame(df_gaps['date'].unique(), columns=['date'])

df_gaps['end'] = df_gaps['date'] + pd.Timedelta(days=1)

df_gaps = df_gaps.sort_values('date', ascending=False)

df_gaps.to_csv('AIA_data_gaps.csv', columns=["date", "end"], index=False, sep=',', header=False, date_format='"%Y-%m-%d %H:%M:%S"',quoting=csv.QUOTE_NONE, quotechar='',  escapechar='')


# ### All AIA instrument gaps individually

# In[ ]:


# hv_sid['DATA_FREQ'] = 0

# hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['94', '131', '171', '193', '211', '304', '335']))), 'DATA_FREQ'] = 36
# hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['1600', '1700']))), 'DATA_FREQ'] = 48
# hv_sid.loc[(hv_sid['OBS'].str.contains('SDO AIA')) & (hv_sid['OBS'].str.contains("|".join(['4500']))), 'DATA_FREQ'] = 3600

# gapfill_percentage = 80

# directory = 'data_gaps'
# if not os.path.exists(directory):
#     os.makedirs(directory)

# h = hv_sid.loc[hv_sid['OBS'].str.match('SDO AIA')].iloc[:].reset_index(drop=True)
# for ind, df_obs in h.iterrows():
#     sid = df_obs['SOURCE_ID']
#     df = hv[sid].copy()
#     name = df_obs['OBS']
#     name_ = name.replace(" ", "_")
#     df = df.dropna().reset_index(drop=True)

#     freq_perday = (pd.Timedelta(days=1)/pd.Timedelta(seconds=df_obs['DATA_FREQ']))
#     gap_threshold = gapfill_percentage / 100 * freq_perday
#     df = df.loc[df['count'] <= gap_threshold].reset_index(drop=True)
#     df['startDate'] = df['date']
#     df['endDate'] = df['startDate'] + pd.Timedelta(days=1)

#     f = open("./%s/%d_%s.csv"%(directory, sid, name_), mode='w')
#     f.write("# %s\n"%name)
#     f.write("# Source Id %d\n"%sid)
#     f.write("# Gap to fill for <= %.2f%% (%d)\n"%(gapfill_percentage, gap_threshold))
#     df.to_csv(f, columns=["startDate", "endDate", "count",], index=False)
#     f.close()

# for sid in hv.keys():
#     df = hv[sid].copy()
#     name = df['OBS'].unique()[0]
#     name_ = name.replace(" ", "_")

#     df = df.dropna().reset_index(drop=True)
#     f = open("./csv_files/%d_%s.csv"%(sid, name_), mode='w')
#     f.write("# %s\n"%name)
#     f.write("# %d\n"%sid)
#     df.to_csv(f, columns=["date", "count", "SOURCE_ID", "OBS"], index=False)
#     f.close()


# # Coverages

# In[ ]:


print("Preparing coverage plots...")
directory = './coverages'
if not os.path.exists(directory):
    os.makedirs(directory)

for observatory in hv_keys.keys():
    panels_obs=[]
    for ind, df_obs in hv_sid[hv_sid['OBS'].str.match(observatory)].iterrows():

        df = hv[df_obs['SOURCE_ID']].copy()
        df['index'] = (df['date'].dt.year - df['date'].min().year)*12 + (df['date'].dt.month - df['date'].min().month)
        sid = df['SOURCE_ID'].unique()[0]
        name = df['OBS'][0]
        name_ = name.replace(" ", "_")

        years = np.array(df['Year'].unique()).astype(str)# hv_cov.index.values#.astype(str)
        days = df['Day'].unique().astype(str) # np.arange(1,32).astype(str)

        colors = bp.Viridis[256]# ["#75968f", "#a5bab7", "#c9d9d3", "#e2e2e2", "#dfccce", "#ddb7b1", "#cc7878", "#933b41", "#550b1d"]

        TOOLS = "hover,save,pan,box_zoom,reset,wheel_zoom"

        # output_file('AIA1600_coverage.html')
        panels = []
        for mapper_type, mapper, ticker in zip(["log", "linear"],
                                               [LogColorMapper, LinearColorMapper],
                                               [LogTicker, BasicTicker]):
            p = figure(y_range=list(reversed(days)),
                       x_axis_location="above",
                       sizing_mode='stretch_both',# width_policy='max', height_policy='max',#, plot_width=1400,
#                        match_aspect=True,
                       x_axis_label="Year Month", y_axis_label="Date",
                       tools=TOOLS, output_backend="webgl", toolbar_location='above',
                       tooltips=[('Date', '@Year @Day'), ('#Data Files', '@count{0,0}')])


            total_days = (hv[sid]['count']>=0).sum()
            total_files = (hv[sid]['count']).sum()

            p.add_layout(Title(text="%s Coverage"%(name), text_font_size='14pt'), 'above')
            p.add_layout(Title(text="Date Range: %s - %s"%(df.dropna()['date'].min().strftime("%Y, %b %d"), df.dropna()['date'].max().strftime("%Y, %b %d"))), 'above')
            p.add_layout(Title(text="Total Files: {:,.0f} | Total Days: {:,.0f} | Source ID: {:,.0f}".format(total_files, total_days, df['SOURCE_ID'].unique()[0]), text_font_style="italic"), 'above')
#             p.add_layout(Title(text="Total Days: %d"%total_days, text_font_style="italic"), 'above')
#             p.add_layout(Title(text="Source ID: %d"%df['SOURCE_ID'].unique()[0], text_font_style="italic"), 'above')

            # p.grid.grid_line_color = None
            p.axis.axis_line_color = None
            p.axis.major_tick_line_color = None
            p.axis.major_label_text_font_size = "7px"
            p.axis.major_label_standoff = 0
            p.xaxis.major_label_orientation = np.pi / 3
            p.xaxis.axis_label_text_font_size = "12pt"
            p.yaxis.axis_label_text_font_size = "12pt"
            p.xaxis.visible = True
            p.xgrid.visible = True
            p.ygrid.visible = False

            p.xaxis.major_label_text_font_size = "7pt"
            p.yaxis.major_label_text_font_size = "8pt"


            p.rect(x="index", y="Day", width=1, height=1,
                   source=df,
                   hover_alpha=0.3,
                   hover_color="navy",#{'field': 'count', 'transform': mapper(palette=colors, low=0.1, high=np.nanmax(df['count']))},
                   color={'field': 'count', 'transform': mapper(palette=colors, low=0.1, high=np.nanmax(df['count']))},
                   line_color=None,
                   dilate=True)

            num_ticks=10
            if (len(df[df['count']>0]['count'].unique()) <= 10):
                num_ticks = len(df[df['count']>0]['count'].unique())
            color_bar = ColorBar(color_mapper = mapper(palette=colors, low=0.1, high=np.nanmax(df['count'])),
                                 major_label_text_font_size="10px",
                                 ticker=ticker(desired_num_ticks=num_ticks),
                                 formatter=NumeralTickFormatter(format="0,0"),
                                 label_standoff=6, border_line_color=None, location=(0, 0))
            p.add_layout(color_bar, 'right')
            interval_months = 3
            inter_thresh = 12
            if(len(years)<inter_thresh):
                interval_months = 1
            p.xaxis.ticker = df['index'].unique()[::interval_months]
            p.xaxis.major_tick_line_color = 'black'
            p.xaxis.major_label_overrides = {i*interval_months: date for i, date in enumerate(years[::interval_months])}
#             p.width_policy = 'fit'
#             p.height_policy = 'fit'
            p.border_fill_color = "whitesmoke"
            p.x_range.range_padding = 0.0
            p.y_range.range_padding = 0.0
            panel = Panel(child=p, title=mapper_type)
            panels.append(panel)
        tabs = Tabs(tabs=panels)
#         show(tabs)
        panel_obs = Panel(child=tabs, title=name.replace(observatory+' ',''))
        panels_obs.append(panel_obs)

    tabs_obs = Tabs(tabs=panels_obs)
#     show(tabs_obs)
#     export_png(tabs_obs, filename='test.png')
#     break
    save(tabs_obs, filename='./coverages/%s_coverage.html'%observatory, title="Coverage plot for %s"%observatory)
print("Coverage plots completed.")


# # HISTOGRAMS

# In[ ]:


print("Preparing histogram and cumulative distribution plots...")
directory = './histograms'
if not os.path.exists(directory):
    os.makedirs(directory)

for observatory in hv_keys.keys():
    panels_obs=[]
    for ind, df_obs in hv_sid[hv_sid['OBS'].str.match(observatory)].iloc[:].iterrows():

        df = hv[df_obs['SOURCE_ID']].copy()
        sid = df['SOURCE_ID'].unique()[0]
        name = df['OBS'][0]
        name_ = name.replace(" ", "_")

        df = hv[sid].copy()
        df = df.dropna().reset_index(drop=True)

        name = df['OBS'].unique()[0]
        name_ = name.replace(" ", "_")

        bin_size = bin_width(df['count'].max())# np.arange(0,count.max(),) 30#.astype(int)#100
#         btabs = interactive_histogram(df['count'], sid, name, bin_size)
        counts = df['count']
        title=name

        arr_hist, edges, patches = plt.hist(counts, bins=np.arange(0, counts.max()+bin_size, bin_size))
        cum_bin_size  = max(bin_size//10,1)
        cum_hist, cum_edges, patches = plt.hist(counts, bins=np.arange(0,counts.max()+cum_bin_size, cum_bin_size), cumulative=True)
        plt.close()

        # Column data source
        df_hist = pd.DataFrame({'count': arr_hist, 'left': edges[:-1], 'right': edges[1:]})
        total = df_hist['count'].sum()
        df_hist['f_count'] = ['%d' % count for count in df_hist['count']]
        df_hist['f_percent'] = ['%.2f%%' %(count/total*100) for count in df_hist['count']]
        df_hist['f_interval'] = ['[%d,%d) ' % (left, right) for left, right in zip(df_hist['left'], df_hist['right'])]
        # column data source
        hist_src = ColumnDataSource(df_hist)

        #cumulative data
        cumulative_data = cum_hist#np.cumsum(arr_hist)
        x_bins = cum_edges[1:]#edges[1:]# np.arange(0, counts.max(), bin_size)[1:]
        df_cum = pd.DataFrame({'count_cum': cumulative_data, 'x': x_bins})
        cum_src = ColumnDataSource(df_cum)
    #     df_hist['f_count'] = np.log10(df_hist['f_count']+1)
        # Set up the figure same as before
        panels = []

        for axis_type in ["log","linear"]:
            p = figure(y_axis_type = axis_type,
                       x_axis_label = 'No. of Data files', y_axis_label = 'Day count',
                       background_fill_color="#fafafa",
                       y_range = (0.9, df_hist['count'].max() + df_hist['count'].max()//10))

            # Add a quad glyph with source this time
            p.quad(bottom=0.9, top='count', left='left', right='right', source=hist_src, fill_color='navy', alpha=0.5,
                   hover_fill_color='navy', hover_fill_alpha=0.2, line_color='white', legend_label='Histogram')

            df_stats = pd.DataFrame({'height': np.linspace(0.5, df_hist['count'].max(), 2),
                                     'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
            p.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 4, legend_label="Mean (%.2f)"%(df_stats['mean'][0]), source=df_stats)
            p.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=3, legend_label="Median (%.2f)"%(df_stats['median'][0]), source=df_stats)
#             p.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f)"%(df_stats['mode'][0]), source=df_stats)

            total_days = (counts>=0).sum()
            total_files = counts.sum()

            p.add_layout(Title(text = "Histogram for %s"%title, text_font_size = "16pt", text_font_style="bold"),
                         place = 'above')
            p.add_layout(Title(text="Date range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                         place = 'above')
            p.add_layout(Title(text="Total Files: {:,.0f} | Total Days: {:,.0f} | Source ID: {}".format(total_files, total_days, sid), text_font_style="italic"),
                         place = 'above')

    #         p.grid.grid_line_color="white"

    #         text_source = ColumnDataSource(dict(x=[x_bins.max()*3/4],y=[df_hist['count'].max()*3/4],text=['Total Day Count = \n %d'%total]))
    #         glyph = Text(x="x", y="y", text="text", text_color="black")
    #         p.add_glyph(text_source, glyph)

            # Add a hover tool referring to the formatted columns
            hover = HoverTool(tooltips = [('#Data files', '@f_interval'),
                                          ('Day count', '@f_count{0,0}'),
                                          ('Day count percentage', '@f_percent')],
                              mode= 'vline')

            p.add_tools(hover)

            # Add style to the plot
            if (df_hist['count'].max() - df_hist['count'].min())<=10:
                p.yaxis.visible = False
                ticker = SingleIntervalTicker(interval=1, num_minor_ticks=10)
                yaxis = LinearAxis(ticker=ticker)
                p.add_layout(yaxis, 'left')

            if (df_hist['right'].max() - df_hist['left'].min())<=10:
                p.xaxis.visible = False
                ticker = SingleIntervalTicker(interval=1, num_minor_ticks=10)
                yaxis = LinearAxis(ticker=ticker)
                p.add_layout(yaxis, 'below')

            p.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
            p.xaxis[0].formatter = NumeralTickFormatter(format='0,0')

            p.yaxis.axis_label = "Day Count"
            p.xaxis.axis_label = "No. of Data Files"
            p.title.align = 'center'
            p.title.text_font_size = '18pt'
            p.xaxis.axis_label_text_font_size = '12pt'
            p.xaxis.major_label_text_font_size = '12pt'
            p.yaxis.axis_label_text_font_size = '12pt'
            p.yaxis.major_label_text_font_size = '12pt'
            p.legend.location = "top_right"
            p.legend.background_fill_alpha = 0.3
            p.border_fill_color = "whitesmoke"
            p.x_range.range_padding = 0.05

            ### Cumulative distribution plot
            p2 = figure(y_axis_type=axis_type,
                       background_fill_color="#fafafa")


            p2_line = p2.line(x='x', y='count_cum', line_color='#036564', line_width=3, source=cum_src, legend_label="Cumulative distribution")
            p2.add_layout(Title(text = "Cumulative Distribution for %s"%title, text_font_size = "16pt", text_font_style="bold"), place = 'above')
            p2.add_layout(Title(text="Date range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))), 'above')
            p2.add_layout(Title(text="Total Files: {:,.0f} | Total Days: {:,.0f} | Source ID: {}".format(total_files, total_days, sid), text_font_style="italic"), 'above')

            # Add the hover tool to the graph
            hover = HoverTool(line_policy='nearest',
                              tooltips = [('#Data files', '<@x{0,0}'),
                                          ('Cumulative Day count', '@count_cum{0,0}')],
                              mode='vline')
            p2.add_tools(hover)

            # Mean, median, mode statistics
            df_cumstats = pd.DataFrame({'height': np.linspace(df_cum['count_cum'].min(),df_cum['count_cum'].max(),2),
                                        'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
            p2.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 2, legend_label="Mean (%.2f)"%(df_cumstats['mean'][0]), source=df_cumstats)
            p2.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=2, legend_label="Median (%.2f)"%(df_cumstats['median'][0]), source=df_cumstats)
#             p2.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f)"%(df_cumstats['mode'][0]), source=df_cumstats)


            # Add styling to the plot
            if (df_cum['count_cum'].max() - df_cum['count_cum'].min())<=10:
                p2.yaxis.visible = False
                ticker = SingleIntervalTicker(interval=1, num_minor_ticks=10)
                yaxis = LinearAxis(ticker=ticker)
                p2.add_layout(yaxis, 'left')

            if (df_cum['x'].max() - df_cum['x'].min())<=10:
                p2.xaxis.visible = False
                ticker = SingleIntervalTicker(interval=1, num_minor_ticks=10)
                yaxis = LinearAxis(ticker=ticker)
                p2.add_layout(yaxis, 'below')

            p2.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
            p2.xaxis[0].formatter = NumeralTickFormatter(format='0,0')

            p2.yaxis.axis_label = "Day Count"
            p2.xaxis.axis_label = "No. of Data Files"
            p2.title.align = 'center'
            p2.title.text_font_size = '18pt'
            p2.xaxis.axis_label_text_font_size = '12pt'
            p2.xaxis.major_label_text_font_size = '12pt'
            p2.yaxis.axis_label_text_font_size = '12pt'
            p2.yaxis.major_label_text_font_size = '12pt'
            p2.legend.location = "bottom_right"
            p2.legend.background_fill_alpha = 0.3
            p2.border_fill_color = "whitesmoke"
            p2.x_range.range_padding = 0.05

            grid = gridplot([[p, p2]], sizing_mode='stretch_both')# width_policy='max', height_policy='max')#,plot_width=1200, plot_height=1000, sizing_mode='scale_width')#, plot_width=250, plot_height=250)
            panel = Panel(child=grid, title=axis_type)
            panels.append(panel)
        tabs = Tabs(tabs=panels)
#         show(tabs)
        panel_obs = Panel(child=tabs, title=name.replace(observatory+' ',''))
        panels_obs.append(panel_obs)
    tabs_obs = Tabs(tabs=panels_obs)
#     show(tabs_obs)
#     break
    save(tabs_obs, filename='./%s/%s_histogram.html'%(directory, observatory), title="Histogram and Cumulative distribution for %s"%observatory)
print("Histograms and cumulative distribution plots completed.")


# # POSTER PLOTS

# # Helioviewer Movie length histogram

# In[21]:


print("### Helioviewer Movies' Length histogram ###")
print("Starting SQL query in movies table of hv database...")
start_time = time.time()
hv={}
# query="SELECT dataSourceString FROM movies"
# query = "SELECT DATE_FORMAT(reqStartDate, '%1980-%m-%d %H:%i:%S') AS reqStartDate, timestamp, reqEndDate, startDate, endDate, dataSourceString FROM movies"
# date_format(reqStartDate, '%Y-%m-%d %H:%i:%s') AS REQ_START, date_format(reqEndDate, '%Y-%m-%d %H:%i:%s')
# query = "SELECT reqStartDate, reqEndDate, dataSourceString, eventSourceString, numFrames, frameRate, maxFrames, timestamp as date, TIMESTAMPDIFF(second, reqStartDate, reqEndDate) AS reqDuration, TIMESTAMPDIFF(second, startDate, endDate) AS genDuration FROM movies WHERE reqEndDate!='None' AND reqStartDate!='None' AND startDate!='None' AND endDate!='None';"
# today = datetime.datetime.now().strftime('%Y-%m-%d')
query = "SELECT reqStartDate, reqEndDate, dataSourceString, eventSourceString, numFrames, frameRate, maxFrames, timestamp as date, TIMESTAMPDIFF(second, reqStartDate, reqEndDate) AS reqDuration, TIMESTAMPDIFF(second, startDate, endDate) AS genDuration FROM movies WHERE reqStartDate IS NOT NULL AND reqEndDate IS NOT NULL AND startDate IS NOT NULL AND endDate IS NOT NULL AND timestamp IS NOT NULL;"
# query = "SELECT ROUND(TIMESTAMPDIFF(second, reqStartDate, reqEndDate)/60/60/24, 3) AS reqDuration, ROUND(TIMESTAMPDIFF(second, startDate, endDate)/60/60/24, 3) AS genDuration FROM movies;"
hv['hv_movies'] = sql_query(query)
print("Query completed in %d seconds."%(time.time()-start_time))


# In[64]:


df = hv['hv_movies'].copy()

# Timedelta.max.value is in nanoseconds. Divide by 10^9 to get the number of seconds supported
max_timedelta_seconds = pd.Timedelta.max.value / 1e9
# Set unsupported values to nan
df['reqDuration'].loc[df['reqDuration'] > max_timedelta_seconds] = np.nan

df['reqDuration'] = pd.to_timedelta(df['reqDuration'], unit='s')/pd.Timedelta(days=1)
df['reqDuration'].loc[df['reqDuration']>30] = np.nan
df['genDuration'] = pd.to_timedelta((df['numFrames']/df['frameRate']), unit='s')/pd.Timedelta(seconds=1)

outlier_count = df['genDuration'].loc[df['genDuration']>300]
if not outlier_count.empty:
    outlier_date = df['date'].loc[df['genDuration']>300].dt.strftime("%b %d %Y, %H:%M:%S").values[0]

df['genDuration'].loc[df['genDuration']>300] = np.nan
# df.sort_values('genDuration')


# In[65]:


# bin_size = 100# 0.5*24*60*60# np.arange(0,count.max(),) 30#.astype(int)#100
print("Preparing histogram for movie lengths...")

directory = 'hv_movies'
if not os.path.exists(directory):
    os.makedirs(directory)

panels_pov=[]
for pov, ref, bin_size, unit, unit2, conversion_factor in zip(['reqDuration','genDuration'],
                                                              ['requested','generated'],
                                                              [1, 10],['days','seconds'], ['years', 'days'],
                                                              [365, 60*60*24]):

    counts = df[pov]
    if (counts.empty):
        continue

    arr_hist, edges = np.histogram(counts, bins=np.arange(0, counts.max()+bin_size, bin_size))
    cum_bin_size = max(bin_size//10, 1)
    cum_hist, cum_edges, patches = plt.hist(counts, bins=np.arange(0,counts.max()+cum_bin_size, cum_bin_size), cumulative=True)
    plt.close()

    # Column data source
    df_hist = pd.DataFrame({'count': arr_hist, 'left': edges[:-1], 'right': edges[1:]})
    total = df_hist['count'].sum()
    df_hist['f_count'] = ['%d' % count for count in df_hist['count']]
    df_hist['f_percent'] = ['%.3f%%' %(count/total*100) for count in df_hist['count']]
    df_hist['f_interval'] = ['[%.1f %s,%.1f %s)' % (left, unit, right, unit) for left, right in zip(df_hist['left'], df_hist['right'])]
    hist_src = ColumnDataSource(df_hist)

    #cumulative data
    cumulative_data = cum_hist#np.cumsum(arr_hist)
    x_bins = cum_edges[1:]#edges[1:]# np.arange(0, counts.max(), bin_size)[1:]
    df_cum = pd.DataFrame({'count_cum': cumulative_data, 'x': x_bins})
    df_cum['f_percent'] = ['%.3f%%' %(count/total*100) for count in df_cum['count_cum']]
    cum_src = ColumnDataSource(df_cum)

    panels = []
    for axis_type in ["log","linear"]:
        p = figure(y_axis_type = axis_type,
                   x_axis_label = 'Length of movies (%s)'%(unit), y_axis_label = 'Movie count',
                   background_fill_color="#fafafa",
                   y_range = (0.5, df_hist['count'].max() + df_hist['count'].max()//10)
                  )

        # Add a quad glyph with source this time
        p_hist = p.quad(bottom=0.5, top='count', left='left', right='right', source=hist_src, fill_color='navy', alpha=0.5,
               hover_fill_color='navy', hover_fill_alpha=0.2, line_color='white', legend_label='Histogram')

    #         p.add_layout(Span(location=1800, dimension='height'))#, legend_label='Expected date file count'))
        df_stats = pd.DataFrame({'height': np.linspace(min(df_hist['count'].min(),0.5),df_hist['count'].max(),2),
                                 'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
        p.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 4, legend_label="Mean (%.2f %s)"%(df_stats['mean'][0], unit), source=df_stats)
        p.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=3, legend_label="Median (%.2f %s)"%(df_stats['median'][0],unit), source=df_stats)
#         p.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f %s)"%(df_stats['mode'][0],unit), source=df_stats)

        total_days = len(counts)
        total_files = counts.sum()

        p.add_layout(Title(text = "Histogram for length of movies %s"%(ref), text_font_size = "16pt", text_font_style="bold"),
                     place = 'above')
        p.add_layout(Title(text="%s during: %s - %s"%(ref, df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                     place = 'above')
        if((ref=='generated') & (not outlier_count.empty)):
            p.add_layout(Title(text="(Movie of length %d seconds %s on %s was discarded)"%(outlier_count, ref, outlier_date), text_font_style="italic"),
                          place = 'above')
        p.add_layout(Title(text="Total length of movies {}: {:,.2f} {} ({:,.2f} {}) | Total Movies: {:,} ".format(ref, total_files, unit, total_files/conversion_factor, unit2, total_days), text_font_style="italic"),
                     place = 'above')

        p.legend.location = "top_right"
        p.legend.background_fill_alpha = 0.3
        p.x_range.range_padding = 0.05

        # Add a hover tool referring to the formatted columns
        hover = HoverTool(tooltips = [('Length of movies %s'%(ref), '@f_interval'),
                                      ('Movie count', '@f_count{0,0}'),
                                      ('Movie count percentage', '@f_percent')],
#                           formatters={'f_count'      : 'printf', # use 'datetime' formatter for 'date' field
#                         'count' : 'int',   # use 'printf' formatter for 'adj close' field
#                                           use default 'numeral' formatter for other fields
#                                      },
                          mode= 'vline')

        # Add the hover tool to the graph
        p.add_tools(hover)

        # Add style to the plot
        p.title.align = 'center'
        p.title.text_font_size = '18pt'
        p.xaxis.axis_label_text_font_size = '12pt'
        p.xaxis.major_label_text_font_size = '12pt'
        p.yaxis.axis_label_text_font_size = '12pt'
        p.yaxis.major_label_text_font_size = '12pt'
#         p.yaxis[0].formatter = PrintfTickFormatter(format="%f")
        p.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
        p.border_fill_color = "whitesmoke"
        p.legend.background_fill_alpha = 0.3
        p.x_range.range_padding = 0.05


        p2 = figure(y_axis_type=axis_type,
                           x_axis_label = 'Length of movies (%s)'%(unit),
                           y_axis_label = 'Movie count',
                           background_fill_color="#fafafa")


        p2_line = p2.line(x='x', y='count_cum', line_color='#036564', line_width=3, source=cum_src, legend_label="Cumulative distribution")
    #         p2_circle = p2.circle(x='x', y='count_cum', line_color='#036564', line_width=5, source=cum_src, hover_line_alpha=0.5, legend_label="Cumulative distribution" )
        p2.add_layout(Title(text = "Cumulative distribution for length of movies %s"%(ref), text_font_size = "16pt", text_font_style="bold"),
                      place = 'above')
        p2.add_layout(Title(text="%s during: %s - %s"%(ref, df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                      place = 'above')
        if((ref=='generated') & (not outlier_count.empty)):
            p2.add_layout(Title(text="(Movie of length %d seconds %s on %s was discarded)"%(outlier_count, ref, outlier_date), text_font_style="italic"),
                          place = 'above')
        p2.add_layout(Title(text="Total length of movies {}: {:,.2f} {} ({:,.2f} {}) | Total Movies: {:,} ".format(ref, total_files, unit, total_files/conversion_factor, unit2, total_days), text_font_style="italic"),
                     place = 'above')

        hover = HoverTool(line_policy='nearest',
                          tooltips = [('Length of movies %s'%(ref), '<@x{0.2f} %s'%unit),
                                      ('Percentage of %s'%(ref), '<@f_percent'),
                                      ('Cumulative Day count', '@count_cum{0,0}')],
                          mode='vline')

        df_cumstats = pd.DataFrame({'height': np.linspace(df_cum['count_cum'].min(),df_cum['count_cum'].max(),2),
                                 'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
        p2.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 4, legend_label="Mean (%.2f %s)"%(df_cumstats['mean'][0], unit), source=df_cumstats)
        p2.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=3, legend_label="Median (%.2f %s)"%(df_cumstats['median'][0], unit), source=df_cumstats)
#         p2.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f %s)"%(df_cumstats['mode'][0], unit), source=df_cumstats)
#         p2.add_layout(Span(location=10, dimension='height', legend_label='Expected date file count'))

        # Add the hover tool to the graph
        p2.add_tools(hover)
        p2.title.align = 'center'
        p2.title.text_font_size = '18pt'
        p2.xaxis.axis_label_text_font_size = '12pt'
        p2.xaxis.major_label_text_font_size = '12pt'
        p2.yaxis.axis_label_text_font_size = '12pt'
        p2.yaxis.major_label_text_font_size = '12pt'
        p2.legend.location = "bottom_right"
#         p2.yaxis[0].formatter = PrintfTickFormatter(format="0,0%f")
        p2.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
        p2.border_fill_color = "whitesmoke"
        p2.legend.background_fill_alpha = 0.3
        p2.x_range.range_padding = 0.05


        grid = gridplot([[p, p2]], sizing_mode='stretch_both')# width_policy='max', height_policy='max')#,plot_width=1200, plot_height=1000, sizing_mode='scale_width')#, plot_width=250, plot_height=250)
        panel = Panel(child=grid, title=axis_type)
        panels.append(panel)
    tabs = Tabs(tabs=panels)
    panel_pov = Panel(child=tabs, title=ref)
    panels_pov.append(panel_pov)
#     break
tabs_pov = Tabs(tabs=panels_pov)
# show(tabs_pov)
save(tabs_pov, filename='./%s/histogram_length.html'%directory, title='Histogram for length of Helioviewer movies')
print("Histograms prepared.")


# # Stats for movies made per day

# In[7]:


print("### Stats for movies prepared per day ###")


# In[8]:


print("Starting SQL query in movies, screenshots, movies_jpx, statistics tables of hv database...")

hv={}
query = "SELECT id, date_format(timestamp, '%Y-%m-%d 00:00:00') as date, count(*) as count FROM {} GROUP BY date_format(timestamp, '%Y-%m-%d 00:00:00');"

start_time=time.time()

hv['hv_movies'] = sql_query(query.format('movies'))

hv['Jhv_movies'] = sql_query(query.format('movies_jpx'))

hv['embed_service'] = sql_query(query.format("statistics WHERE action=\'embed\'"))
hv['embed_service']['date'] = pd.to_datetime(hv['embed_service']['date'])
#df_em = pd.read_csv('embed.csv')
#df_em['timestamp'] = pd.to_datetime(df_em['timestamp'])
#df_em = pd.DataFrame(df_em.groupby(by=df_em['timestamp'].dt.date).count()['id'])
hv['embed_service'].index = hv['embed_service']['date']
#hv['embed_service'] = df_em.join(hv['embed_service'], how='outer')
hv['embed_service'] = pd.DataFrame(hv['embed_service'][['id','count']].max(axis=1), columns=['count'])
hv['embed_service']['date'] = hv['embed_service'].index
hv['embed_service'] = hv['embed_service'].reset_index(drop=True)

hv['hv_screenshots'] = sql_query(query.format('screenshots'))

hv['hv_student'] = sql_query(query.format("statistics WHERE action=\'minimal\'"))

print("Query completed in %d seconds."%(time.time()-start_time))


# In[9]:


titles = ["Helioviewer.org Movies generated", "Helioviewer.org Screenshots generated",
          "JHelioviewer Movies generated", "Times Embedded Helioviewer.org service was used",
         "Student Helioviewer Movies generated"]
services= ["Movies", "Screenshots", "Movies", "Embed usage", "Movies"]

for key in hv.keys():
    hv[key] = hv[key].dropna()
    hv[key]['date'] = pd.to_datetime(hv[key]['date'])
    hv[key] = hv[key].sort_values(['date']).reset_index(drop=True)


# In[10]:


server_shutdown_days = ((pd.Timestamp('2011/09/18') - pd.Timestamp('2011/08/11') + pd.Timedelta(days=1))+
                        (pd.Timestamp('2013/10/16') - pd.Timestamp('2013/10/01') + pd.Timedelta(days=1))+
                        (pd.Timestamp('2015/09/23') - pd.Timestamp('2015/02/04') + pd.Timedelta(days=1))).days


# # Time series

# In[ ]:


print("Making time series of movies generated per day...")
for key, title, service in zip(hv.keys(), titles, services):
    if skip_empty_table(hv[key], key):
        continue

    directory = key
    print(key)
    if not os.path.exists(directory):
        os.makedirs(directory)

    df = hv[key].copy()
    df = df.set_index('date')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='D').to_period('D').to_timestamp(),
                                  fill_value=0)
    df['date'] = df.index
    df = df.reset_index(drop=True)

    df_0 = df.loc[df['count']==0].reset_index(drop=True)

    df.loc[(df['date'] >= pd.Timestamp('2011/08/11')) & (df['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2013/10/01')) & (df['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2015/02/04')) & (df['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    df_src = ColumnDataSource(df)

    p = figure(plot_height=250, x_axis_type="datetime",
               tools=TOOLS,
               sizing_mode="scale_width", min_border_left = 0)


    p.add_layout(Title(text = "Number of %s every day"%title, text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place = 'above')
    p.add_layout(Title(text="Total {}: {:,.0f} | Total Days: {:,} (excluding {:,} days of server downtime) ".format(title, df['count'].sum(), len(df.dropna()), len(df)-len(df.dropna())), text_font_style="italic"),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date'
    p.yaxis.axis_label = 'No. of %s'%title
    p.axis.axis_line_color = None

    p.x_range.start = df['date'].min() - (df['date'].max()-df['date'].min())*0.02
    p.x_range.end = df['date'].max() + (df['date'].max()-df['date'].min())*0.02

#     p.x_range.range_padding = 0.02
    p.y_range.range_padding = 0.05

    p.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
#     p.xaxis[0].formatter = DatetimeTickFormatter(days=["%b %d, %Y %H"])
    p.xaxis.ticker = YearsTicker(desired_num_ticks=10)#, num_minor_ticks=12)
#     p.xaxis[0].ticker.desired_num_ticks = 10

    p_line = p.line(x='date', y='count', line_width=2, color='#ebbd5b', source=df_src)
    p_0 = p.circle(x='date', y='count', size=2, color='red', source = df_0, legend_label='Zero %s (%d days)'%(service, len(df_0)))

    p = service_pause(p, df)
    p = major_features(p, df)

    p.add_tools(HoverTool(renderers=[p_line],
                          tooltips=[( 'date',   '@date{%F}'),
        #               ( 'close',  '$@{adj close}{%0.2f}' ), # use @{ } for field names with spaces
                                    ( '#%s'%service, '@count{0,0}'),#{0.00 a}'      ),
                                   ],
                          formatters={'@date'      : 'datetime', # use 'datetime' formatter for 'date' field
#                                       'count' : 'int',   # use 'printf' formatter for 'adj close' field
                                      # use default 'numeral' formatter for other fields
                                     },
    #     display a tooltip whenever the cursor is vertically in line with a glyph
    #     mode='vline'
                         ))
    df_stats = pd.DataFrame({'height': pd.date_range(df['date'].min(),df['date'].max(),periods=2),
                             'mean':np.nanmean(df['count']), 'median': np.nanmedian(df['count']), 'mode':stats.mode(df['count'])[0][0]})
    p.line(y='mean', x='height', line_color="blue", line_dash='dotted', line_width = 1, legend_label="Mean ({:,.2f})".format(df_stats['mean'][0]), source=df_stats)
    p.line(y='median', x='height', line_color = "black", line_dash='dashed', line_width=1, legend_label="Median ({:,.2f})".format(df_stats['median'][0]), source=df_stats)
    p.legend.background_fill_alpha = 0.3
    p.border_fill_color = "whitesmoke"

#     show(p)
#     break
    save(p, filename='./%s/time_series.html'%key, title='%s every day'%title)
print("Time series completed.")


# # Histogram of media per day

# In[ ]:


print("Making histogram of movies generated per day...")
for key, title, service in zip(hv.keys(), titles, services):
    if skip_empty_table(hv[key], key):
        continue

    directory = key
    if not os.path.exists(directory):
        os.makedirs(directory)

    df = hv[key].copy()
    df = df.set_index('date')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='D').to_period('D').to_timestamp(),
                                  fill_value=0)
    df['date'] = df.index
    df = df.reset_index(drop=True)

    df.loc[(df['date'] >= pd.Timestamp('2011/08/11')) & (df['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2013/10/01')) & (df['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2015/02/04')) & (df['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    bin_size = bin_width(df['count'].max())# np.arange(0,count.max(),) 30#.astype(int)#100
    counts = df['count']

    arr_hist, edges = np.histogram(counts, bins=np.arange(0, counts.max()+bin_size, bin_size))
    cum_bin_size  = max(bin_size//10,1)
    cum_hist, cum_edges, patches = plt.hist(counts, bins=np.arange(0,counts.max()+cum_bin_size, cum_bin_size), cumulative=True)
    plt.close()

    # Column data source
    df_hist = pd.DataFrame({'count': arr_hist, 'left': edges[:-1], 'right': edges[1:]})
    total = df_hist['count'].sum()
    df_hist['f_count'] = ['%d' % count for count in df_hist['count']]
    df_hist['f_percent'] = ['%.2f%%' %(count/total*100) for count in df_hist['count']]
    df_hist['f_interval'] = ['[{:,.0f} - {:,.0f})'.format(left, right) for left, right in zip(df_hist['left'], df_hist['right'])]
    hist_src = ColumnDataSource(df_hist)

    #cumulative data
    cumulative_data = cum_hist#np.cumsum(arr_hist)
    x_bins = cum_edges[1:]#edges[1:]# np.arange(0, counts.max(), bin_size)[1:]
    df_cum = pd.DataFrame({'count_cum': cumulative_data, 'x': x_bins})
    df_cum['f_percent'] = ['%.2f%%' %(count/total*100) for count in df_cum['count_cum']]
    cum_src = ColumnDataSource(df_cum)

    panels = []
    for axis_type in ["log","linear"]:
        p = figure(y_axis_type = axis_type,
                   x_axis_label = 'No. of %s'%title, y_axis_label = 'Day count',
                   background_fill_color="#fafafa",
                   y_range = (0.9, df_hist['count'].max() + df_hist['count'].max()//10))

        # Add a quad glyph with source this time
        p.quad(bottom=0.9, top='count', left='left', right='right', source=hist_src, fill_color='navy', alpha=0.5,
               hover_fill_color='navy', hover_fill_alpha=0.2, line_color='white', legend_label='Histogram')
    #         p.y_range(Range1d(0.8,df_hist['count'].max()))
        # Add style to the plot
        p.title.align = 'center'
        p.title.text_font_size = '18pt'
        p.xaxis.axis_label_text_font_size = '12pt'
        p.xaxis.major_label_text_font_size = '12pt'
        p.yaxis.axis_label_text_font_size = '12pt'
        p.yaxis.major_label_text_font_size = '12pt'

        p.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
        p.xaxis[0].formatter = NumeralTickFormatter(format='0,0')

        df_stats = pd.DataFrame({'height': np.linspace(0.5, df_hist['count'].max(), 2),
                                 'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
        p.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 4, legend_label="Mean (%.2f)"%(df_stats['mean'][0]), source=df_stats)
        p.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=3, legend_label="Median (%.2f)"%(df_stats['median'][0]), source=df_stats)
#         p.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f)"%(df_stats['mode'][0]), source=df_stats)

        total_days = (counts>=0).sum()
        total_files = counts.sum()

        p.add_layout(Title(text = "Histogram for %s "%title, text_font_size = "12pt", text_font_style="bold"), place = 'above')
        p.add_layout(Title(text="Date range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))), 'above')
        p.add_layout(Title(text="Total {} generated: {:,.0f} | Total Days: {:,} (excluding {:,} days of server downtime)".format(service, total_files, total_days, len(df)-len(df.dropna())), text_font_style="italic"), 'above')

        p.legend.location = "top_right"
        p.legend.background_fill_alpha = 0.3
        p.border_fill_color = "whitesmoke"

    #     p.grid.grid_line_color="white"

    #     text_source = ColumnDataSource(dict(x=[x_bins.max()*3/4],y=[df_hist['count'].max()*3/4],text=['Total Day Count = \n %d'%total]))
    #     glyph = Text(x="x", y="y", text="text", text_color="black")
    #     p.add_glyph(text_source, glyph)

        # Add a hover tool referring to the formatted columns
        hover = HoverTool(tooltips = [('#%s generated'%service, '@f_interval'),
                                      ('Day count', '@f_count{0,0}'),
                                      ('Day count percentage', '@f_percent')],
                          mode= 'vline')

        # Add the hover tool to the graph
        p.add_tools(hover)
        p2 = figure(y_axis_type=axis_type,
                           x_axis_label = 'No. of %s'%key,
                           y_axis_label = 'Day count',
                           background_fill_color="#fafafa")


        p2_line = p2.line(x='x', y='count_cum', line_color='#036564', line_width=3, source=cum_src, legend_label="Cumulative distribution")
        p2.add_layout(Title(text = "Cumulative distribution for %s"%title, text_font_size = "12pt", text_font_style="bold"), place = 'above')
        p2.add_layout(Title(text="Date range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))), 'above')
        p2.add_layout(Title(text="Total {} generated: {:,.0f} | Total Days: {:,} (excluding {:,} days of server downtime)".format(service, total_files, total_days, len(df)-len(df.dropna())), text_font_style="italic"), 'above')

        hover = HoverTool(line_policy='nearest',
                          tooltips = [('#%s generated'%service, '<@x{0,0}'),
                                      ('Percentage of %s generated'%service, '<@f_percent'),
                                      ('Cumulative Day count', '@count_cum{0,0}')],
                          mode='vline')

    #         p2.add_layout(Span(location=1800, dimension='height'))#, legend_label='Expected date file count'))

        df_cumstats = pd.DataFrame({'height': np.linspace(df_cum['count_cum'].min(),df_cum['count_cum'].max(),2),
                                    'mean':np.nanmean(counts), 'median': np.nanmedian(counts), 'mode':stats.mode(counts)[0][0]})
        p2.line(x='mean', y='height', line_color="black", line_dash='solid', line_width = 4, legend_label="Mean ({:,.2f})".format(df_cumstats['mean'][0]), source=df_cumstats)
        p2.line(x='median', y='height', line_color = "red", line_dash='dashed', line_width=3, legend_label="Median ({:,.2f})".format(df_cumstats['median'][0]), source=df_cumstats)
#         p2.line(x='mode', y='height', line_color = "lightgreen", line_dash = 'dashdot',line_width=2,legend_label="Mode (%.2f)"%(df_cumstats['mode'][0]), source=df_cumstats)

        # Add the hover tool to the graph
        p2.add_tools(hover)
        p2.title.align = 'center'
        p2.title.text_font_size = '18pt'
        p2.xaxis.axis_label_text_font_size = '12pt'
        p2.xaxis.major_label_text_font_size = '12pt'
        p2.yaxis.axis_label_text_font_size = '12pt'
        p2.yaxis.major_label_text_font_size = '12pt'
        p2.yaxis[0].formatter = NumeralTickFormatter(format='0,0')
        p2.xaxis[0].formatter = NumeralTickFormatter(format='0,0')
        p2.border_fill_color = "whitesmoke"
        p2.legend.background_fill_alpha = 0.3
        p2.legend.location = "bottom_right"
        p2.x_range.range_padding = 0.05

        grid = gridplot([[p, p2]], sizing_mode='stretch_both')# width_policy='max', height_policy='max')#,plot_width=1200, plot_height=1000, sizing_mode='scale_width')#, plot_width=250, plot_height=250)
        panel = Panel(child=grid, title=axis_type)
        panels.append(panel)
    tabs = Tabs(tabs=panels)
#     show(tabs)
#     break
    save(tabs, filename='./%s/histogram.html'%key, title='Histogram and CDF for %s generated every day'%title)
print("Histograms completed.")


# # Weekday frequency distribution

# In[ ]:


print("Making weekday frequency distribution of movies generated per day...")
for key, title, service in zip(hv.keys(), titles, services):
    if skip_empty_table(hv[key], key):
        continue

    directory = key
    if not os.path.exists(directory):
        os.makedirs(directory)

    df = hv[key].copy()

    df_na = df.copy()
    df_na = df_na.set_index('date')
    df_na = df_na.reindex(pd.date_range(df_na.index.min(),df_na.index.max(), freq='D').to_period('D').to_timestamp(),
                                  fill_value=0)
    df_na['date'] = df_na.index
    df_na = df_na.reset_index(drop=True)

    df_na.loc[(df_na['date'] >= pd.Timestamp('2011/08/11')) & (df_na['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
    df_na.loc[(df_na['date'] >= pd.Timestamp('2013/10/01')) & (df_na['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
    df_na.loc[(df_na['date'] >= pd.Timestamp('2015/02/04')) & (df_na['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    server_downtime_days = len(df_na)-len(df_na.dropna())

    df['weekday'] = df['date'].dt.day_name()
    df = df.groupby('weekday').sum().reindex(weekdays)
    df['weekday'] = df.index
    df = df.reset_index(drop=True)
    df['index'] = df.index

    # Column data source
    df['percent'] = np.float64(["%.2f"%(count/df['count'].sum()*100) for count in df['count']])
    df['percent%'] = df['percent'].astype(str)+"%"
    df['vbar_top'] = df['count'].astype(str) + '\n' + df['percent'].astype(str)+'%'
    df_src = ColumnDataSource(df)
    panels = []
    for axis_type in ["linear","log"]:
        p = figure(#x_range = df['weekday'],
                   y_axis_type = axis_type,
                   x_axis_label = 'Weekdays', y_axis_label = '%s count'%service,
                   background_fill_color="#fafafa", aspect_ratio=16/9, plot_width=1000)

        # Add a quad glyph with source this time
        p.vbar(x='index', top='count', width=0.75, source=df_src, bottom=0.1,
               hover_fill_alpha = 0.5, line_color='white', legend_field="weekday",
               fill_color = factor_cmap('weekday', palette=bp.Spectral7, factors=df['weekday']),
               hover_fill_color=factor_cmap('weekday', palette=bp.Spectral7, factors=df['weekday']),
              )
        # Add style to the plot
        p.title.align = 'center'
        p.title.text_font_size = '18pt'
        p.xaxis.axis_label_text_font_size = '12pt'
        p.xaxis.major_label_text_font_size = '12pt'
        p.yaxis.axis_label_text_font_size = '12pt'
        p.yaxis.major_label_text_font_size = '12pt'
        p.xgrid.grid_line_color = None

        p.y_range.start = 0.1
        p.y_range.end = df['count'].max()*1.5
        p.yaxis[0].formatter = NumeralTickFormatter(format='0,0')

        if(axis_type=="log"): p.y_range.end = df['count'].max()**1.5

        p.add_layout(Title(text = "Frequency of %s per weekday"%title, text_font_size = "16pt", text_font_style="bold"), place = 'above')
        p.add_layout(Title(text="Date range: %s - %s"%(hv[key]['date'].min().strftime('%Y, %b %d'),hv[key]['date'].max().strftime('%Y, %b %d'))), 'above')
        p.add_layout(Title(text="Total {} generated: {:,} | Total Days: {:,} (excluding {:,} days of server downtime)"
                           .format(service, df['count'].sum(), len(df_na.dropna()), server_downtime_days), text_font_style="italic"), 'above')

        p.legend.orientation = "horizontal"
        p.legend.location = "top_center"
        p.grid.grid_line_color="white"

        labels = LabelSet(x='index', y='count', text='percent%', level='glyph',
                          x_offset=-30, y_offset=0, source=df_src)#, render_mode='canvas')
        p.add_layout(labels)

        # Add a hover tool referring to the formatted column

        hover = HoverTool(tooltips = [('#%s generated'%service, '@count{0,0}'),
                                      ('Percentage of %s generated'%service, '@percent%')],
                          mode= 'vline')

    #     Add the hover tool to the graph
        p.add_tools(hover)
        p.xaxis.major_label_overrides = {i: day for i, day in enumerate(df['weekday'])}
        p.xaxis.minor_tick_line_color = None

        p.border_fill_color = "whitesmoke"

        panel = Panel(child=p, title=axis_type)
        panels.append(panel)
    tabs = Tabs(tabs=panels)
#     show(tabs)
#     break
    save(tabs, filename='./%s/weekday_freq.html'%key, title='Histogram for %s generated every day'%title)
print("Weekday frequency distribution done")


# # Weekday frequency against week number

# In[13]:


df_service = pd.concat([pd.DataFrame({'date': pd.date_range('2011/08/11', '2011/09/18'), 'reason':"GSFC server repair \n (2011/08/11 - 2011/09/18)"}),
                        pd.DataFrame({'date': pd.date_range('2013/10/01', '2013/10/16'), 'reason':"U.S. Fed. Gov. shutdown \n  (2013/10/01 - 2013/10/16)"}),
                        pd.DataFrame({'date': pd.date_range('2015/02/04', '2015/09/23'), 'reason':"GSFC server down   \n (2015/02/04 - 2015/09/23)"})],
                       ignore_index=True)
df_service['weekday'] = df_service['date'].dt.day_name()
df_service


# In[ ]:


print("Making weekday frequency against weeknumber distribution of movies generated per day...")
for key, title, service in zip(hv.keys(), titles, services):
    if skip_empty_table(hv[key], key):
        continue

    directory = key
    if not os.path.exists(directory):
        os.makedirs(directory)

    df = hv[key].copy()

    df = df.set_index('date')
    df = df.reindex(pd.date_range(df.index.min(),df.index.max(), freq='D').to_period('D').to_timestamp(),
                    fill_value=0)

#     df = df.reindex(pd.date_range(df.index.min() + pd.Timedelta(days=-df.index.min().weekday()),
#                                   df.index.max() + pd.Timedelta(days=7-df.index.max().weekday())),
#                    fill_value=np.nan)

    df.loc[(df.index >= pd.Timestamp('2011/08/11')) & (df.index <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
    df.loc[(df.index >= pd.Timestamp('2013/10/01')) & (df.index <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
    df.loc[(df.index >= pd.Timestamp('2015/02/04')) & (df.index <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    server_downtime_days = len(df)-len(df.dropna())
    df_na = df.copy()
    df = df.dropna()

    df = pd.concat([df.reindex(pd.date_range(df.index.min() + pd.Timedelta(days=-df.index.min().weekday()),
                                             df.index.min() + pd.Timedelta(days=-1)),
                          fill_value=np.nan),
                    df,
                    df.reindex(pd.date_range(df.index.max() + pd.Timedelta(days=1),
                                             df.index.max() + pd.Timedelta(days=6-df.index.max().weekday())),
                          fill_value=np.nan)])

    df['date'] = df.index
    df = df.reset_index(drop=True)

    df['weekday'] = df['date'].dt.day_name()
    df['weeknumber'] = ((df['date']-df['date'][0]).dt.days/7).astype(int)
    df_service['weeknumber'] = ((df_service['date']-df['date'][0]).dt.days/7).astype(int)
    # df = df.groupby(['weeknumber','weekday']).sum().reset_index()

    weeknumber = np.array(df['weeknumber'].unique()).astype(str)# hv_cov.index.values#.astype(str)
    # weekdays = weekdays# df['weekday'].unique().astype(str) # np.arange(1,32).astype(str)

    colors = bp.Viridis[256]# ["#75968f", "#a5bab7", "#c9d9d3", "#e2e2e2", "#dfccce", "#ddb7b1", "#cc7878", "#933b41", "#550b1d"]

    TOOLS = "save,pan,box_zoom,reset,wheel_zoom"

    # output_file('AIA1600_coverage.html')
    panels = []
    for mapper_type, mapper, ticker in zip(["log", "linear"],
                                           [LogColorMapper, LinearColorMapper],
                                           [LogTicker, BasicTicker]):
        p = figure(y_range=list(reversed(weekdays)),#x_range=weeknumber,
                   x_axis_location=None, sizing_mode='stretch_both',# width_policy='max', height_policy='max',#,
    #                plot_width=2000,
                   x_axis_label="Weeks since first data", y_axis_label="Weekday",
                   tools=TOOLS)

        p_rect = p.rect(x="weeknumber", y="weekday", width=1, height=1,
                        source=df,
                        color={'field': 'count', 'transform': mapper(palette=colors, low=0.1, high=np.nanmax(df['count']))},
                        hover_fill_alpha=0.2)

        p.add_tools(HoverTool(renderers = [p_rect],
                              tooltips=[('Week Number', '@weeknumber'),
                                        ('#%s'%service, '@count{0,0}'),
                                        ('Date','@date{%F}')],
                              formatters={'@date': 'datetime'}
        ))
        xaxis = LinearAxis(ticker=SingleIntervalTicker(interval=7, num_minor_ticks= 1))
        p.add_layout(xaxis, 'above')
        p.xaxis.axis_label = "Weeks since first data"
        # p.grid.grid_line_color = None
        p.axis.axis_line_color = None
        p.axis.major_tick_line_color = None
#         p.axis.major_label_text_font_size = "300pt"
        p.axis.major_label_standoff = 0
        p.xaxis.major_label_orientation = np.pi / 3
        p.xaxis.axis_label_text_font_size = "12pt"
    #     p.xaxis.major_label_text_color = {'field': 'weeknumber', 'transform': mapper(palette=bp.Spectral6, low=0.1, high=np.nanmax(df['count']))}
        p.yaxis.axis_label_text_font_size = "12pt"
        p.xaxis.visible = True
        p.xgrid.visible = False
        p.ygrid.visible = False
        p.x_range.range_padding = 0.0
        p.y_range.range_padding = 0.0
        p.x_range.start = 0


        p.xaxis.major_label_text_font_size = "8pt"
        p.yaxis.major_label_text_font_size = "10pt"


        p.add_layout(Title(text = "Weekday frequency against week number for %s"%title, text_font_size = "16pt", text_font_style="bold"), place = 'above')
        p.add_layout(Title(text="Date range: %s - %s"%(hv[key]['date'].min().strftime('%Y, %b %d'),hv[key]['date'].max().strftime('%Y, %b %d'))), 'above')
        p.add_layout(Title(text="Total {} generated: {:,} | Total days: {:,} (excluding {:,} days of server downtime)"
                           .format(service, df['count'].sum(), len(df_na.dropna()), server_downtime_days), text_font_style="italic"), 'above')

        p_service = p.rect(x="weeknumber", y="weekday", width=1, height=1,
                           source=df_service,
                           fill_color='red', fill_alpha=0.5, hover_fill_alpha=0.2, line_color=None)
        p.add_tools(HoverTool(renderers = [p_service],
                              tooltips=[('Week Number', '@weeknumber'),
                                        ('Shutdown', '@reason'),
                                        ('Date','@date{%F}')],
                              formatters={'@date': 'datetime'}))

        num_ticks=10
        if (len(df[df['count']>0]['count'].unique()) <= 10):
            num_ticks = len(df[df['count']>0]['count'].unique())
        color_bar = ColorBar(color_mapper = mapper(palette=colors, low=0.1, high=np.nanmax(df['count'])),
                             major_label_text_font_size="10px",
                             ticker=ticker(desired_num_ticks=num_ticks),
                             formatter=NumeralTickFormatter(format="0,0"),
                             label_standoff=6, border_line_color=None, location=(0, 0))
        p.add_layout(color_bar, 'right')
        p.border_fill_color = "whitesmoke"
    #             p.width_policy = 'fit'
    #             p.height_policy = 'fit'
        panel = Panel(child=p, title=mapper_type)
        panels.append(panel)
    tabs = Tabs(tabs=panels)
#     show(tabs)
#     break
    save(tabs, filename='./%s/weeknumber_frequency.html'%key, title='Weekday frequency against weeknumber for %s'%title)
print("Weekday frequency against weeknumber distribution done.")


## Weekly weekday distribution

# In[18]:


print("------------------Making weekly weekday distribution of movies generated per day...")
TOOLS = "save, pan, box_zoom, reset, wheel_zoom"
for key, title, service in zip(["hv_movies"], titles, services):
    if skip_empty_table(hv[key], key):
        continue

    directory = key
    if not os.path.exists(directory):
        os.makedirs(directory)

    df = hv[key].copy()
    df = df.set_index('date')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='D').to_period('D').to_timestamp(),
                                  fill_value=0)
    df['date'] = df.index
    df = df.reset_index(drop=True)

    df_0 = df.loc[df['count']==0].reset_index(drop=True)

    df.loc[(df['date'] >= pd.Timestamp('2011/08/11')) & (df['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2013/10/01')) & (df['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
    df.loc[(df['date'] >= pd.Timestamp('2015/02/04')) & (df['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

#     df = df.dropna()

    df['weekday'] = df['date'].dt.day_name()
    df['weeknumber'] = ((df['date']-df['date'][0]).dt.days/7).astype(int)

    if (not df_0.empty):
        df_0['weekday'] = df_0['date'].dt.day_name()
        df_0['weeknumber'] = ((df_0['date']-df_0['date'][0]).dt.days/7).astype(int)

    df_service['weeknumber'] = ((df_service['date']-df['date'][0]).dt.days/7).astype(int)
    # df = df.groupby(['weeknumber','weekday']).sum().reset_index()
    weeknumber = np.array(df['weeknumber'].unique())# hv_cov.index.values#.astype(str)

    color = {weekdays[i]:bp.Spectral7[i] for i in range(len(weekdays))}
    p_wd=[]
    panels=[]
    for wd in weekdays:
        df_wd = df.loc[df['weekday']==wd]
        if not df_wd.empty:
            if (not df_0.empty):
                df_0_wd = df_0.loc[df_0['weekday']==wd]
            p = figure(plot_height=250, x_axis_type="datetime",
                    tools=TOOLS,
                    sizing_mode="scale_width", min_border_left = 0,
                    x_axis_label="Date",
                    y_axis_label="No. of %s"%service)

            p.background_fill_color="#f5f5f5"
            p.grid.grid_line_color="white"

            server_downtime_days = len(df_wd)-len(df_wd.dropna())

            p.add_layout(Title(text = "Weekly coverage of %s for %s"%(title, wd), text_font_size = "16pt", text_font_style="bold"), place = 'above')
            p.add_layout(Title(text="Date range: %s - %s"%(hv[key]['date'].min().strftime('%Y, %b %d'),hv[key]['date'].max().strftime('%Y, %b %d'))), 'above')
            p.add_layout(Title(text="Total {} generated on {}: {:,} | Total {}s: {:,} (excluding {:,} {}s of server downtime)"
                            .format(service, wd, df_wd['count'].sum(), wd, len(df_wd.dropna()), server_downtime_days, wd), text_font_style="italic"), 'above')

            if (not df_0.empty):
                p_0 = p.circle(x='date', y='count', size=2, color='red', source = df_0_wd, legend_label='Zero %s (%s %ss)'%(service, len(df_0_wd), wd))


            p.title.text_font_size = '16pt'

            p.x_range.start = df_wd['date'].min() - (df_wd['date'].max()-df_wd['date'].min())*0.02
            p.x_range.end = df_wd['date'].max() + (df_wd['date'].max()-df_wd['date'].min())*0.02

    #         p.x_range.range_padding = 0.02
            p.y_range.range_padding = 0.05

            p = service_pause(p, df_wd)
            p = major_features(p, df_wd)

            p.line(x='date', y='count', source=df_wd, legend_label="#%s on %s"%(service, wd), color='#ebbd5b')

            p.add_tools(HoverTool(tooltips=[('Week Number', '@weeknumber'),
                                            ('Date','@date{%F}'),
                                            ('#%s'%service,'@count')],
                                formatters={'@date': 'datetime'},
                                mode='vline'))
            p.legend.background_fill_alpha = 0.3
            p.border_fill_color = "whitesmoke"

            panel=Panel(child = p, title=wd)
            panels.append(panel)
    tabs=Tabs(tabs=panels)
#     grid = gridplot(list(np.array([p_wd]).T), sizing_mode="scale_width")
#     show(tabs)
#     break
    save(tabs, filename='%s/weekly_weekday.html'%key, title='Weekly coverage of %s per weekday'%(title))
print("Weekly weekday distribution done.")


# In[ ]:


print("### Stats for movies per day done. ###")


# # Popularity

# In[ ]:


print("### Popularity plots ###")


# ## Popularity of solar time

# In[ ]:


def obs_popularity(database, df_obs):
    hv={}
    obs = df_obs['OBS']

    sid = df_obs['SOURCE_ID']
    if(sql_query("SELECT count(*) from data WHERE sourceId=%d"%(sid)).values==0):
        return pd.DataFrame(columns=['date','count']), 0

    if(database=='movies'):
        query = "SELECT startDate, endDate FROM movies WHERE dataSourceString LIKE '%{}%' OR dataSourceString='[{}]';".format(obs.replace(' ','%'), sid)
        hv = sql_query(query)
    if(database=='movies_jpx'):
        query = "SELECT reqstartDate as startDate, reqEndDate as endDate FROM movies_jpx WHERE sourceId={};".format(df_obs['SOURCE_ID'])
        hv = sql_query(query)

    hv = hv.dropna().reset_index(drop=True)

    first_obs, last_obs = sql_query("SELECT min(date) as min_date, max(date) as max_date from data WHERE sourceId=%d"%sid).iloc[0]

    hv = hv.loc[((hv['startDate'] >= first_obs) & (hv['startDate'] <= last_obs)) & ((hv['endDate'] >=first_obs) & (hv['endDate'] <= last_obs))]

    if(hv.empty):
        return hv, len(hv)
#         df = pd.DataFrame({'date': pd.date_range(first_obs, last_obs, freq='M')})
#         df['count'] = 0
#         return df, len(hv)

    hv = hv.sort_values('startDate').reset_index(drop=True).dropna()
    hv['startDate'] = hv['startDate'].dt.to_period('H').dt.to_timestamp()
    hv['endDate'] = hv['endDate'].dt.to_period('H').dt.to_timestamp()

#     df = pd.DataFrame({'date' : pd.date_range(hv['startDate'].min(), hv['endDate'].max(), freq='H').to_period('H').to_timestamp()})
#     df['count'] = np.zeros(len(df), dtype=int)
#     for ind, h in hv.iterrows():
#         df.loc[(df['date']>=h['startDate']) & (df['date']<=h['endDate']), 'count']+=1

    df = (pd.concat([pd.Series(pd.date_range(r.startDate, r.endDate, freq='H')) for r in hv.itertuples()]).to_frame('date'))
    df = df.groupby('date').size().to_frame('count')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='H'), fill_value=0).rename_axis('date').reset_index()

    if len(df)==1:
#         return pd.DataFrame(), len(hv)
        df = df.set_index('date')
        df = df.reindex(pd.date_range(df.index.min()-pd.Timedelta(hours=2), df.index.max() + pd.Timedelta(hours=2), freq='H').to_period('H').to_timestamp(),
                                      fill_value=0)
        df['date'] = df.index
        df = df.reset_index(drop=True)
    return df, len(hv)


# In[ ]:


def popularity_plot(df_obs, df, size, service):

    key='movies'
    name = df_obs['OBS']
    name_ = name.replace(" ","_")

    df_0 = df.loc[df['count']==0].reset_index(drop=True)

#     df.loc[(df['date'] >= pd.Timestamp('2011/08/11')) & (df['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
#     df.loc[(df['date'] >= pd.Timestamp('2013/10/01')) & (df['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
#     df.loc[(df['date'] >= pd.Timestamp('2015/02/04')) & (df['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, x_axis_type="datetime",
               tools=TOOLS, output_backend='webgl',
               sizing_mode="scale_width", min_border_left = 0)

    p.add_layout(Title(text = "Solar popularity of %s in %s %s"%(name, service, key), text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place ='above')
    p.add_layout(Title(text="Total (solar hour) occurrences in {}: {:,} | Total hours of data observed: {:,} | Total number of movies generated: {:,} "
                       .format(key,df['count'].sum(), len(df.loc[df['count']!=0]), size), text_font_style="italic"),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date (hourly)'
    p.yaxis.axis_label = 'Occurences in %s'%key
    p.axis.axis_line_color = None
    p.x_range.range_padding = 0.02
    p.x_range.range_padding = 0.02
    p.y_range.range_padding = 0.02

    p.yaxis.formatter = NumeralTickFormatter(format='0,0')
#     p.xaxis.formatter = DatetimeTickFormatter(minutes=["%d %b %Y"],
#                                               hours=["%d %b %Y"],
#                                               days=["%d %b %Y"],
#                                               months=["%d %b %Y"],
#                                               years=["%d %b %Y"])
#     p.xaxis.formatter = DatetimeTickFormatter()
#     p.xaxis.ticker = YearsTicker(desired_num_ticks=10, num_minor_ticks=12)
    p = major_features(p, df)
    p = service_pause(p, df)

    p_line = p.line(x='date', line_width=2, y='count', color='#ebbd5b', source=df, legend_label="Data Popularity")
    if (not df_0.empty):
        p_0 = p.circle(x='date', y='count', size=2, color='red', source = df_0, legend_label='Zero movie occurences (%d hours)'%len(df_0))

    p.add_tools(HoverTool(renderers=[p_line],
                          tooltips=[('date', '@date{%F %T}'),
                                    #( 'close',  '$@{adj close}{%0.2f}' ), # use @{ } for field names with spaces
                                    ('#occurences in movies', '@count'),#{0.00 a}'      ),
                                   ],
                          formatters={'@date' : 'datetime', # use 'datetime' formatter for 'date' field
#                                           'count' : 'int',   # use 'printf' formatter for 'adj close' field
#                                           use default 'numeral' formatter for other fields
                                     },
#                           mode='vline'
                         ))
    df_stats = pd.DataFrame({'height': pd.date_range(df['date'].min(),df['date'].max(),periods=2),
                             'mean':np.nanmean(df['count']), 'median': np.nanmedian(df['count']), 'mode':stats.mode(df['count'])[0][0]})

    p.line(y='mean', x='height', line_color = "blue", line_dash='dotted', line_width= 1, alpha=0.5, legend_label="Mean (%.2f)"%(df_stats['mean'][0]), source=df_stats)
    p.line(y='median', x='height', line_color = "black", line_dash='dashed', line_width=1, alpha=0.5, legend_label="Median (%.2f)"%(df_stats['median'][0]), source=df_stats)

    p.x_range.start = df['date'].min() - (df['date'].max()-df['date'].min())*0.03
    p.x_range.end = df['date'].max() + (df['date'].max()-df['date'].min())*0.03

#     p.x_range.range_padding = 0.1
    p.y_range.range_padding = 0.05
    p.legend.background_fill_alpha = 0.3
    p.legend.location='top_left'
    p.border_fill_color = "whitesmoke"

    panel = Panel(child=p, title=name.replace(name.split(" ")[0]+' ', ''))
    return panel
#     show(p)


# ### Solar popularity in Helioviewer movies

# In[ ]:


par = Parallel(n_jobs=20)
directory="hv_movies"
if not os.path.exists(directory):
    os.makedirs(directory)
if not os.path.exists("./%s/popularity"%directory):
    os.makedirs("./%s/popularity"%directory)

print("Making solar time popularity plots for helioviewer.org movies...")
for observatory in hv_keys.keys():
    start_time=time.time()
    h = hv_sid.loc[hv_sid['OBS'].str.match(observatory)].iloc[:].reset_index(drop=True)
    popularity = par(delayed(obs_popularity)('movies', df_obs) for ind, df_obs in h.iterrows())
    panels=[]
    tabs=[]
    for ind, df_obs in h.iterrows():
        if(popularity[ind][0].empty):
            print("<=1 movies prepared with %s"%(df_obs['OBS']))
            continue
        panels.append(popularity_plot(df_obs, popularity[ind][0], popularity[ind][1], 'Helioviewer.org'))
        tabs = Tabs(tabs=panels)
#     show(tabs)
    if len(panels) == 0:
        print("Skipping %s because it is empty" % observatory)
        continue
    save(tabs, filename='./%s/popularity/%s_popularity.html'%(directory, observatory), title='Solar popularity of %s in Helioviewer.org movies'%(observatory))
    print("%s popularity done in %d seconds"%(observatory, time.time()-start_time))
print("Popularity plot done.")


# In[ ]:


popularity=[]


# ### Solar popularity in JHelioviewer movies

# In[ ]:


par = Parallel(n_jobs=20)
directory="Jhv_movies"
if not os.path.exists(directory):
    os.makedirs(directory)
if not os.path.exists("./%s/popularity"%directory):
    os.makedirs("./%s/popularity"%directory)

print("Making solar time popularity plots for Jhelioviewer movies...")
for observatory in hv_keys.keys():
    start_time=time.time()
    h = hv_sid.loc[hv_sid['OBS'].str.match(observatory)].iloc[:].reset_index(drop=True)
    popularity = par(delayed(obs_popularity)('movies_jpx', df_obs) for ind, df_obs in h.iterrows())
    panels=[]
    tabs=[]
    for ind, df_obs in h.iterrows():
        if(popularity[ind][0].empty):
            print("<=1 movies prepared with %s"%(df_obs['OBS']))
            continue
        panels.append(popularity_plot(df_obs, popularity[ind][0], popularity[ind][1], 'JHelioviewer'))
        tabs = Tabs(tabs=panels)
#     show(tabs)
    if len(panels) == 0:
        print("Skipping %s because it is empty" % observatory)
        continue
    save(tabs, filename='./%s/popularity/%s_popularity.html'%(directory, observatory), title='Solar popularity of %s in JHelioviewer movies'%(observatory))
    print("%s popularity done in %d seconds"%(observatory, time.time()-start_time))
print("Popularity plot done.")


# In[ ]:


popularity=[]


# ## Popularity of THE data in helioviewer.org movies

# In[ ]:


def obs_popularity(database, df_obs):
    hv={}
    obs = df_obs['OBS']
    sid = df_obs['SOURCE_ID']
    data_freq = df_obs['DATA_FREQ']
    if(sql_query("SELECT count(*) from data WHERE sourceId=%d"%(sid)).values==0):
        return pd.DataFrame(columns=['date','count']), 0

    if(database=='movies'):
        query = "SELECT startDate, endDate FROM movies WHERE dataSourceString LIKE '%{}%' OR dataSourceString='[{}]';".format(obs.replace(' ','%'), sid)
        hv = sql_query(query)
    if(database=='movies_jpx'):
        query = "SELECT reqstartDate as startDate, reqEndDate as endDate FROM movies_jpx WHERE sourceId={};".format(df_obs['SOURCE_ID'])
        hv = sql_query(query)

    hv = hv.dropna().reset_index(drop=True)

    first_obs, last_obs = sql_query("SELECT min(date) AS min_date, max(date) AS max_date FROM data WHERE sourceId=%d"%sid).iloc[0]
    hv = hv.loc[((hv['startDate'] >= first_obs) & (hv['startDate'] <= last_obs)) & ((hv['endDate'] >=first_obs) & (hv['endDate'] <= last_obs))]
    hv = hv.sort_values(['startDate', 'endDate']).reset_index(drop=True).dropna()

    if(hv.empty):
        return hv, len(hv)
    hv['duration'] = (hv['endDate']-hv['startDate']).dt.total_seconds()
    hv['frames'] = (hv['duration']/data_freq).astype(int)
    hv.loc[hv['frames']>300, 'frames'] = 300
    df = (pd.concat([pd.Series(pd.date_range(r.startDate, r.endDate, periods=r.frames+1)) for r in hv.iloc[:].itertuples()]).to_frame('date'))
    df = df['date'].dt.to_period('H').dt.to_timestamp().to_frame('date')
    df = df.groupby('date').size().to_frame('count')
    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='H'), fill_value=0).rename_axis('date').reset_index()
#     return df
    if len(df)==1:
#         return pd.DataFrame(), len(hv)
        df = df.set_index('date')
        df = df.reindex(pd.date_range(df.index.min()-pd.Timedelta(hours=2), df.index.max() + pd.Timedelta(hours=2), freq='H').to_period('H').to_timestamp(),
                                      fill_value=0)
        df['date'] = df.index
        df = df.reset_index(drop=True)
    return df, len(hv) #len(hv) is the actual number of movies created since df will also have a lot of zeros


# In[ ]:


def popularity_plot(df_obs, df, size, service):

    key='movies'
    name = df_obs['OBS']
    name_ = name.replace(" ","_")

    df_0 = df.loc[df['count']==0].reset_index(drop=True)

#     df.loc[(df['date'] >= pd.Timestamp('2011/08/11')) & (df['date'] <= pd.Timestamp('2011/09/18')), 'count'] = np.nan
#     df.loc[(df['date'] >= pd.Timestamp('2013/10/01')) & (df['date'] <= pd.Timestamp('2013/10/16')), 'count'] = np.nan
#     df.loc[(df['date'] >= pd.Timestamp('2015/02/04')) & (df['date'] <= pd.Timestamp('2015/09/23')), 'count'] = np.nan

    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, x_axis_type="datetime",
               tools=TOOLS, output_backend='webgl',
               sizing_mode="scale_width", min_border_left = 0)

    p.add_layout(Title(text = "Data popularity of %s in %s %s"%(name, service, key), text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place ='above')
    p.add_layout(Title(text="Total frames used in all {}: {:,} | Total hours of data observed: {:,} | Total number of movies generated: {:,} "
                       .format(key,df['count'].sum(), len(df.loc[df['count']!=0]), size), text_font_style="italic"),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date (hourly)'
    p.yaxis.axis_label = 'No. of frames used in %s per hour'%key
    p.axis.axis_line_color = None
    p.x_range.range_padding = 0.02
    p.x_range.range_padding = 0.02
    p.y_range.range_padding = 0.02

    p.yaxis.formatter = NumeralTickFormatter(format='0,0')
#     p.xaxis.formatter = DatetimeTickFormatter(minutes=["%d %b %Y"],
#                                               hours=["%d %b %Y"],
#                                               days=["%d %b %Y"],
#                                               months=["%d %b %Y"],
#                                               years=["%d %b %Y"])
#     p.xaxis.formatter = DatetimeTickFormatter()
#     p.xaxis.ticker = YearsTicker(desired_num_ticks=10, num_minor_ticks=12)
    p = major_features(p, df)
    p = service_pause(p, df)

    p_line = p.line(x='date', line_width=2, y='count', color='#ebbd5b', source=df, legend_label="Data Popularity")
    if (not df_0.empty):
        p_0 = p.circle(x='date', y='count', size=2, color='red', source = df_0, legend_label='Zero movie occurences (%d hours)'%len(df_0))

    p.add_tools(HoverTool(renderers=[p_line],
                          tooltips=[('date', '@date{%F %T}'),
                                    #( 'close',  '$@{adj close}{%0.2f}' ), # use @{ } for field names with spaces
                                    ('#occurences in movies', '@count'),#{0.00 a}'      ),
                                   ],
                          formatters={'@date' : 'datetime', # use 'datetime' formatter for 'date' field
#                                           'count' : 'int',   # use 'printf' formatter for 'adj close' field
#                                           use default 'numeral' formatter for other fields
                                     },
#                           mode='vline'
                         ))
    df_stats = pd.DataFrame({'height': pd.date_range(df['date'].min(),df['date'].max(),periods=2),
                             'mean':np.nanmean(df['count']), 'median': np.nanmedian(df['count']), 'mode':stats.mode(df['count'])[0][0]})

    p.line(y='mean', x='height', line_color = "blue", line_dash='dotted', line_width= 1, alpha=0.5, legend_label="Mean (%.2f)"%(df_stats['mean'][0]), source=df_stats)
    p.line(y='median', x='height', line_color = "black", line_dash='dashed', line_width=1, alpha=0.5, legend_label="Median (%.2f)"%(df_stats['median'][0]), source=df_stats)

    p.x_range.start = df['date'].min() - (df['date'].max()-df['date'].min())*0.03
    p.x_range.end = df['date'].max() + (df['date'].max()-df['date'].min())*0.03

#     p.x_range.range_padding = 0.02
    p.y_range.range_padding = 0.05
    p.legend.background_fill_alpha = 0.3
    p.legend.location='top_left'
    p.border_fill_color = "whitesmoke"

    panel = Panel(child=p, title=name.replace(name.split(" ")[0]+' ', ''))
    return panel
#     show(p)


# ### Data popularity in Helioviewer.org

# In[ ]:


par = Parallel(n_jobs=20)

directory="hv_movies"
if not os.path.exists(directory):
    os.makedirs(directory)
if not os.path.exists("./%s/popularity_data"%directory):
    os.makedirs("./%s/popularity_data"%directory)

print("Making data popularity plots for helioviewer movies...")

for observatory in ['SDO']:#hv_keys.keys():
    start_time=time.time()
    h = hv_sid.loc[hv_sid['OBS'].str.match("%s"%observatory)].iloc[:].reset_index(drop=True)
    popularity = par(delayed(obs_popularity)('movies', df_obs) for ind, df_obs in h.iterrows())
    panels=[]
    for ind, df_obs in h.iterrows():
        popularity = obs_popularity(database='movies', df_obs=df_obs)
        if(popularity[0].empty):
            print("<=1 movies prepared with %s"%(df_obs['OBS']))
            continue
        panel = popularity_plot(df_obs, popularity[0], popularity[1], 'Helioviewer.org')
        panels.append(panel)
    tabs = Tabs(tabs=panels)
#     show(tabs)
    save(tabs, filename='./%s/popularity_data/%s_popularity.html'%(directory, observatory), title='Data Popularity of %s in Helioviewer.org movies'%(observatory))
    print("%s popularity done in %d seconds"%(observatory, time.time()-start_time))
    break
print("Popularity plot done.")


# In[ ]:


popularity=[]


# ### Data popularity in Jhelioviewer

# In[ ]:


# directory="Jhv_movies"
# if not os.path.exists(directory):
#     os.makedirs(directory)
# if not os.path.exists("./%s/popularity_data"%directory):
#     os.makedirs("./%s/popularity_data"%directory)

# print("Making data popularity plots for Jhelioviewer movies...")

# for observatory in hv_keys.keys():
#     start_time=time.time()
#     h = hv_sid.loc[hv_sid['OBS'].str.match("%s AIA"%observatory)].iloc[:].reset_index(drop=True)
# #     popularity = par(delayed(obs_popularity)('movies', df_obs) for ind, df_obs in h.iterrows())
#     panels=[]
#     for ind, df_obs in h.iterrows():
#         popularity = obs_popularity(database='movies_jpx', df_obs=df_obs)
#         if(popularity[0].empty):
#             print("<=1 movies prepared with %s"%(df_obs['OBS']))
#             continue
#         panel = popularity_plot(df_obs, popularity[0], popularity[1], 'JHelioviewer')
#         panels.append(panel)
#     tabs = Tabs(tabs=panels)
# #     show(tabs)
#     save(tabs, filename='./%s/popularity_data/%s_popularity.html'%(directory, observatory), title='Data Popularity of %s in JHelioviewer movies'%(observatory))
#     print("%s popularity done in %d seconds"%(observatory, time.time()-start_time))
#     break
# print("Popularity plot done.")


# In[ ]:


print("ALL popularity plots done.")


# # Service Comparison

# In[ ]:


print("Service comparison...")


# ## HV, JHV, embed comparison

# In[ ]:


start_time=time.time()
hv={}
print("Starting SQL query in movies and statistics table of hv database...")

query = "SELECT id, date_format(timestamp, '%Y-%m-%d 00:00:00') as date, count(*) as count FROM {} GROUP BY date_format(timestamp, '%Y-%m-%d 00:00:00');"
hv['hv_movies'] = sql_query(query.format('movies'))

hv['embed'] = sql_query(query.format("statistics WHERE action=\'embed\'"))

hv['Jhv_movies'] = sql_query(query.format("statistics WHERE action=\'getJPX\'"))

for key in hv.keys():
    if skip_empty_table(hv[key], key):
        continue

    hv[key]['date'] = pd.to_datetime(hv[key]['date'])

print("Query completed in %d seconds."%(time.time()-start_time))


# In[ ]:


#df_em = pd.read_csv('embed.csv')
#df_em['timestamp'] = pd.to_datetime(df_em['timestamp'])
#df_em = pd.DataFrame(df_em.groupby(by=df_em['timestamp'].dt.date).count()['id'])
hv['embed'].index = hv['embed']['date']
#hv['embed'] = df_em.join(hv['embed'], how='outer')
hv['embed'] = pd.DataFrame(hv['embed'][['id','count']].max(axis=1), columns=['count'])

hv['embed']['date'] = hv['embed'].index
hv['embed'] = hv['embed'].reset_index(drop=True)


# In[ ]:

try:
    date_start = min(hv['hv_movies']['date'].min(), hv['embed']['date'].min(), hv['Jhv_movies']['date'].min())
    date_end = max(hv['hv_movies']['date'].max(), hv['embed']['date'].max(), hv['Jhv_movies']['date'].max())

    # In[ ]:


    for key in hv.keys():
        if skip_empty_table(hv[key], key):
            continue

    #     print(key)
        df = hv[key].copy()
        df = df.set_index('date')
        df = df.reindex(pd.date_range(date_start, date_end, freq='D').to_period('D').to_timestamp(),
                                      fill_value=0)
        df['date'] = df.index
        df = df.reset_index(drop=True)
        hv[key] = df


    # In[ ]:


    for key in hv.keys():
        hv[key].loc[(hv['Jhv_movies']['count']==0) & (hv['embed']['count']==0) & (hv['hv_movies']['count']==0), 'bottom_frac'] = np.nan
        hv[key].loc[(hv['Jhv_movies']['count']==0) & (hv['embed']['count']==0) & (hv['hv_movies']['count']==0), 'top_frac'] = np.nan
        hv[key].loc[(hv['Jhv_movies']['count']==0) & (hv['embed']['count']==0) & (hv['hv_movies']['count']==0), 'fraction'] = np.nan


    # In[ ]:


    # total_count = (hv['hv_movies']['count'] + hv['embed']['count'] + hv['Jhv_movies']['count'])

    # hv['hv_movies']['bottom_frac'] = 0
    # hv['hv_movies']['top_frac'] = hv['hv_movies']['count']/total_count
    # hv['hv_movies']['fraction'] = hv['hv_movies']['top_frac'] - hv['hv_movies']['bottom_frac']

    # hv['embed']['bottom_frac'] = hv['hv_movies']['top_frac']
    # hv['embed']['top_frac'] = hv['embed']['bottom_frac'] + hv['embed']['count']/total_count
    # hv['embed']['fraction'] = hv['embed']['top_frac'] - hv['embed']['bottom_frac']

    # hv['Jhv_movies']['bottom_frac'] = hv['embed']['top_frac']
    # hv['Jhv_movies']['top_frac'] = 1
    # hv['Jhv_movies']['fraction'] = hv['Jhv_movies']['top_frac'] - hv['Jhv_movies']['bottom_frac']


    # In[ ]:


    frac = pd.DataFrame()

    frac['date'] = pd.date_range(date_start, date_end, freq='D').to_period('D').to_timestamp()

    frac['total_count'] = (hv['hv_movies']['count'] + hv['embed']['count'] + hv['Jhv_movies']['count'])

    frac['hv_frac'] = hv['hv_movies']['count']/frac['total_count']
    frac['hv_perc'] = frac['hv_frac']*100
    frac['em_frac'] = hv['embed']['count']/frac['total_count']
    frac['em_perc'] = frac['em_frac']*100
    frac['Jhv_frac'] = hv['Jhv_movies']['count']/frac['total_count']
    frac['Jhv_perc'] = frac['Jhv_frac']*100

    frac['hv_bottom'] = 1e-6
    frac['hv_top'] = frac['hv_frac']

    frac['em_bottom'] = frac['hv_top']
    frac['em_top'] = frac['em_bottom'] + frac['em_frac']

    frac['Jhv_bottom'] = frac['em_top']
    frac['Jhv_top'] = frac['Jhv_bottom'] + frac['Jhv_frac']


    # In[ ]:


    frac['date_str'] = frac['date'].astype(str)
    frac = frac.fillna(0)
    frac['index'] = frac.index
    frac


    # In[ ]:


    frac['year_dec'] = frac['date'].dt.year + frac['date'].dt.day/pd.to_datetime(dict(year=frac['date'].dt.year, month=12, day=31)).dt.strftime('%j').astype(int)


    # In[ ]:


    print("Preparing plot for helioviewer services comparison...")

    directory='service_usage'
    if not os.path.exists(directory):
        os.makedirs(directory)

    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, output_backend='webgl',
               tools=TOOLS,
               sizing_mode="scale_width", min_border_left = 0,
    #            tooltips="$name @date: @$name"
    #            y_axis_type="log", #y_range = (1, 10**(-4))
              )

    p.add_layout(Title(text = "Daily fractional usage of helioviewer services", text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(date_start.strftime('%Y, %b %d'),date_end.strftime('%Y, %b %d'))),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date'
    p.yaxis.axis_label = 'Fractional usage'
    p.axis.axis_line_color = None

    stacks = ['hv_frac', 'em_frac', 'Jhv_frac']

    p_hv = p.vbar_stack(stackers=stacks,
                        x='index', width=0.75,
    #                     alpha = 0.5,
                        color = bp.Category10[max(3,len(stacks))][:len(stacks)],#bp.Viridis[3],
    #                     hover_color = bp.Viridis[3],
                        source=ColumnDataSource(frac),
                        legend_label=["Fraction of Helioviewer.org movie requests",
                                      "Fraction of Embedded Helioviewer.org requests",
                                      "Fraction of JHelioviewer movie requests"])

    # p_hv = p.vbar(x='index', width=0.75,
    #               bottom='hv_bottom',
    #               top='hv_top',
    # #               hover_alpha = 0.5,
    #               color = 'blue',
    # #               hover_color='blue',
    #               source=frac, legend_label="Fraction of Helioviewer.org requests")

    # p_em = p.vbar(x='index', width=0.75,
    #               bottom='em_bottom', top='em_top',
    #               hover_alpha = 0.5,
    #               color = 'orange',
    #               hover_color='orange',
    #               source=frac, legend_label="Fraction of embedded Helioviewer.org requests")

    # p_Jh = p.vbar(x='index', width=0.75,
    #               bottom='Jhv_bottom', top='Jhv_top',
    #               hover_alpha = 0.5,
    #               color = 'green',
    #               hover_color='green',
    #               source=frac, legend_label="Fraction of JHelioviewer movie requests")


    p.add_tools(HoverTool(renderers=p_hv,#, p_em, p_Jh],
                          tooltips=[('Date', '@date_str'),
                                    ('JHelioviewer', '@Jhv_perc{0.00}%'),
                                    ('Embed Helioviewer.org', '@em_perc{0.00}%'),
                                    ('Helioviewer.org', '@hv_perc{0.00}%'),
                                    ('Total hits', '@total_count')
                                   ],
    #                       formatters={'@date' : 'datetime', # use 'datetime' formatter for 'date' field
    #                                  },
                         ))

    # frac['date'].dt.year + frac['date'].dt.day/pd.to_datetime(dict(year=frac['date'].dt.year, month=12, day=31)).dt.strftime('%j').astype(int)

    def dt2ind(dt):
    #     y = dt.year + dt.day/int(pd.Timestamp(dt.year,12,31).strftime('%j'))
        return frac.loc[frac['date']==dt].index[0]

    def add_line_if_timestamp_in_range(time_str, color, label):
        timestamp = pd.Timestamp(time_str)
        if date_start < timestamp and timestamp < date_end:
            p.line(y=[0,1.1], x=dt2ind(timestamp), line_width=1.5, line_dash='dotdash', color=color, alpha=1, legend_label=label)

    def add_harea_if_timestamp_in_range(start_time, end_time, color, alpha, label):
        start_timestamp = pd.Timestamp(start_time)
        end_timestamp = pd.Timestamp(end_time)
        # make sure start and end times are in range
        if date_start < start_timestamp and start_timestamp < date_end:
            if date_start < end_timestamp and end_timestamp < date_end:
                p.harea(y=[0,1.1], x1=dt2ind(start_timestamp), x2=dt2ind(end_timestamp), fill_color=color, fill_alpha=alpha, legend_label=label)

    add_line_if_timestamp_in_range('2011/06/07', 'red', "failed eruption (2011/06/07)")
    add_line_if_timestamp_in_range('2013/11/28', 'purple', "Comet ISON (2013/11/28)")
    add_harea_if_timestamp_in_range('2017/09/06', '2017/09/10', 'black', 1, "large flares (2017/09/06-09)")

    add_harea_if_timestamp_in_range('2011/08/11', '2011/09/18', 'gray', 0.3, "GSFC server repair (2011/08/11 - 2011/09/18)")
    add_harea_if_timestamp_in_range('2013/10/01', '2013/10/16', 'green', 0.3, "U.S. Fed. Gov. shutdown (2013/10/01 - 2013/10/16)")
    add_harea_if_timestamp_in_range('2015/02/04', '2015/09/23', 'red', 0.3, "GSFC server down (2015/02/04 - 2015/09/23)")

    df_stats = pd.DataFrame({'width': np.linspace(frac['index'].min()-100, frac['index'].max()+100, 2),
                             'mean_hv':np.nanmean(frac['hv_frac']), 'mean_embed':np.nanmean(frac['em_frac']), 'mean_Jhv':np.nanmean(frac['Jhv_frac'])})

    p.line(y='mean_hv', x='width', line_color = "red", line_dash='dotted', line_width= 2, alpha=0.5,
           legend_label="Mean fraction of Helioviewer.org movie requests (%.3f)"%(df_stats['mean_hv'][0]), source=df_stats)

    p.line(y='mean_embed', x='width', line_color = "pink", line_dash='dotted', line_width= 2, alpha=0.5,
           legend_label="Mean fraction of Helioviewer.org Embed requests (%.3f)"%(df_stats['mean_embed'][0]), source=df_stats)

    p.line(y='mean_Jhv', x='width', line_color = "cyan", line_dash='dotted', line_width= 2, alpha=0.5,
           legend_label="Mean fraction of JHelioviewer movie requests (%.3f)"%(df_stats['mean_Jhv'][0]), source=df_stats)

    # p.xaxis.ticks = frac['index'].iloc[::]
    p.xaxis.major_label_overrides = {i: date.strftime('%Y %b %d') for i, date in enumerate(frac['date'])}

    p.x_range.range_padding = 0.00
    p.y_range.range_padding = 0.00

    p.legend.background_fill_alpha = 0.3
    p.legend.location='top_left'
    p.border_fill_color = "whitesmoke"

    # show(p)
    save(p, filename='%s/hv_services.html'%directory, title='Daily fractional usage of helioviewer services')
    print("Helioviewer service usage fraction plot done.")
except TypeError: # Occurs when any of the above tables are empty
    print("Skipping service comparison because some data is empty")

# ## HV endpoints' fractional usage breakdown

# In[ ]:


print("Starting SQL query in redis_stats table of hv database...")
start_time=time.time()
query = "SELECT date_format(datetime, '%Y-%m-%d 00:00:00') as date, action, count(date_format(datetime, '%Y-%m-%d 00:00:00')) as count FROM {} GROUP BY date_format(datetime, '%Y-%m-%d 00:00:00'), action"
hv = sql_query(query.format('redis_stats WHERE datetime>\'2020-07-01\''))
print("Query completed in %d seconds"%(time.time()-start_time))


# In[ ]:


heirarchy = {
    "Total":["total","rate_limit_exceeded"],
    "Client Sites":["standard","embed","minimal"],
    "Images":["takeScreenshot","postScreenshot","getTile","getClosestImage","getJP2Image-web","getJP2Image-jpip","getJP2Image","downloadScreenshot","getJPX","getJPXClosestToMidPoint"],
    "Movies":["buildMovie","postMovie","getMovieStatus","queueMovie","postMovie","reQueueMovie","playMovie","downloadMovie","getUserVideos","getObservationDateVideos","uploadMovieToYouTube","checkYouTubeAuth","getYouTubeAuth"],
    "Events":["getEventGlossary","getEvents","getFRMs","getEvent","getEventFRMs","getDefaultEventTypes","getEventsByEventLayers","importEvents"],
    "Data":["getRandomSeed","getDataSources","getJP2Header","getDataCoverage","getStatus","getNewsFeed","getDataCoverageTimeline","getClosestData","getSolarBodiesGlossary","getSolarBodies","getTrajectoryTime","sciScript-SSWIDL","sciScript-SunPy","getSciDataScript","updateDataCoverage","getEclipseImage", "getClosestImageDatesForSources"],
    "Other":["shortenURL","getUsageStatistics","movie-notifications-granted","movie-notifications-denied","logNotificationStatistics","launchJHelioviewer", "saveWebClientState", "getWebClientState"],
    "WebGL":["getTexture","getGeometryServiceData"]
};


# In[ ]:


hv['date'] = pd.to_datetime(hv['date'])

if not hv['date'].empty:
    hv = hv.pivot_table(values='count', columns='action', index = ['date'])
    hv.columns.name = None
    hv=hv.fillna(0)


# In[ ]:


for stat in heirarchy:
    for action in heirarchy[stat]:
        if action not in hv.columns:
            hv[action]=0


# In[ ]:


frac={}
for stat in heirarchy.keys():
    df = pd.DataFrame()
    if(stat=='Total'):
            df['total'] = hv.sum(axis=1)
            df['total_count'] = hv.sum(axis=1)
    else:
        df['total'] = hv[heirarchy[stat]].sum(axis=1).values
        df.index = hv.index
        df[heirarchy[stat]] = hv[heirarchy[stat]].div(df['total'], axis=0)*100
        df= df.fillna(0)

    if skip_empty_table(df, stat):
        continue

    df = df.reindex(pd.date_range(df.index.min(), df.index.max(), freq='D'), fill_value=0).reset_index().rename(columns = {'index':'date'})
    #     df['date_str'] = df['date'].astype(str)
    df.index = df['date']
    df.index.name = None
    df = df.reset_index().rename(columns={'index':'date_str'}).reset_index()
    df['date_str'] = df['date_str'].astype(str)
    frac[stat]=df.copy()
#     break


# In[ ]:


panels=[]
print("Preparing plot for Helioviewer endpoints' fractional usage breakdown...")
for stat in frac.keys():
    df=frac[stat]
    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, output_backend='webgl',
               tools=TOOLS,
               sizing_mode="scale_width", min_border_left = 0,
    #            tooltips="$name @date: @$name"
              )

    p.add_layout(Title(text = "Helioviewer endpoints' fractional usage breakdown", text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date'
    p.yaxis.axis_label = 'Fractional usage (%)'
    p.axis.axis_line_color = None



    stacks = df.columns.values[4:]

    p_hv = p.vbar_stack(stackers=stacks,
                        x='index', width=0.75,
                        color = bp.Category20[max(3,len(stacks))][:len(stacks)],
                        source=ColumnDataSource(df),
                        legend_label=["%s"%string for string in stacks])

    p.add_tools(HoverTool(renderers=p_hv,
                          tooltips=[('Date', '@date_str'), ('Total', '@total')] + [(string, '@{%s}{0.00}%%'%string) for string in stacks],
                          formatters={'@date_str' : 'datetime', # use 'datetime' formatter for 'date' field
                                     },
                         ))

    # def dt2ind(dt):
    # #     y = dt.year + dt.day/int(pd.Timestamp(dt.year,12,31).strftime('%j'))
    #     return df.loc[df['date']==dt].index[0]

    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2011/06/07')), line_width=1.5, line_dash='dotdash', color='red', alpha=1, legend_label= "failed eruption (2011/06/07)")
    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2013/11/28')), line_width=1.5, line_dash='dotdash', color='purple', alpha=1, legend_label= "Comet ISON (2013/11/28)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2017/09/06')), x2=dt2ind(pd.Timestamp('2017/09/10')), fill_color='teal', fill_alpha=1, legend_label= "large flares (2017/09/06-09)")

    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2011/08/11')), x2=dt2ind(pd.Timestamp('2011/09/18')), fill_color='gray', fill_alpha=0.3, legend_label= "GSFC server repair (2011/08/11 - 2011/09/18)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2013/10/01')), x2=dt2ind(pd.Timestamp('2013/10/16')), fill_color='green', fill_alpha=0.3, legend_label= "U.S. Fed. Gov. shutdown (2013/10/01 - 2013/10/16)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2015/02/04')), x2=dt2ind(pd.Timestamp('2015/09/23')), fill_color='red', fill_alpha=0.3, legend_label= "GSFC server down (2015/02/04 - 2015/09/23)")


    # df_stats = pd.DataFrame({'width': np.linspace(df['index'].min(), df['index'].max(), 2),
    #                          'mean_hv':np.nanmean(df['hv_frac']), 'mean_embed':np.nanmean(df['em_frac']), 'mean_Jhv':np.nanmean(df['Jhv_frac'])})

    # p.line(y='mean_hv', x='width', line_color = "red", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org movie requests (%.3f)"%(df_stats['mean_hv'][0]), source=df_stats)

    # p.line(y='mean_embed', x='width', line_color = "pink", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org Embed requests (%.3f)"%(df_stats['mean_embed'][0]), source=df_stats)

    # p.line(y='mean_Jhv', x='width', line_color = "cyan", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of JHelioviewer movie requests (%.3f)"%(df_stats['mean_Jhv'][0]), source=df_stats)

    p.xaxis.major_label_overrides = {i: date.strftime('%Y %b %d') for i, date in enumerate(df['date'])}

    p.x_range.range_padding = 0.06
    p.y_range.range_padding = 0.02

    p.legend.background_fill_alpha = 0.6
    p.border_fill_color = "whitesmoke"
    # p.y_range.start = 0
    # p.y_range.end=200
    p.legend.location='top_left'
    # p.legend.orientation = 'horizontal'
    panel = Panel(child=p, title=stat)
    panels.append(panel)
tabs = Tabs(tabs=panels)
# show(tabs)
save(tabs, filename='%s/hv_endpoints_breakdown.html'%directory, title="Helioviewer endpoints' fractional usage breakdown")
print("Helioviewer endpoints' fractional usage breakdown plot done.")


# ## HV usage of end point categories

# In[ ]:

tot=pd.DataFrame()
frac=pd.DataFrame()
for stat in heirarchy.keys():
    if(stat=='Total'): continue
    tot[stat] = hv[heirarchy[stat]].sum(axis=1).values
    frac[stat] = tot[stat]/hv.sum(axis=1).values * 100
#     break

tot.insert(0, 'date', hv.index)
frac.insert(0, 'date', hv.index)
tot.insert(1, 'total', hv.sum(axis=1).values)
frac.insert(1, 'total', hv.sum(axis=1).values)
tot.index = hv.index
frac.index = hv.index
tot.index.name = None
frac.index.name = None
tot = tot.reset_index().rename(columns={'index':'date_str'}).reset_index()
frac = frac.reset_index().rename(columns={'index':'date_str'}).reset_index()
tot['date_str'] = tot['date_str'].astype(str)
frac['date_str'] = frac['date_str'].astype(str)
# tot.fillna(0)
# frac.fillna(0)
# tot = tot.reindex(pd.date_range(tot['date'].min(), tot['date'].max(), freq='D'), fill_value=0).reset_index().rename(columns = {'index':'date'})


# In[ ]:


print("Preparing plot for HV usage comparison of endpoint categories...")
panels=[]
df=tot
if not df.empty:
    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, output_backend='webgl',
               tools=TOOLS,
               sizing_mode="scale_width", min_border_left = 0,
    #            tooltips="$name @date: @$name"
              )

    p.add_layout(Title(text = "Helioviewer total usage comparison of endpoint categories", text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date'
    p.yaxis.axis_label = 'Total usage'
    p.axis.axis_line_color = None



    stacks = df.columns.values[4:]

    p_hv = p.vbar_stack(stackers=stacks,
                        x='index', width=0.75,
                        color = bp.Category20[max(3,len(stacks))][:len(stacks)],
                        source=ColumnDataSource(df),
                        legend_label=["%s"%string for string in stacks])

    p.add_tools(HoverTool(renderers=p_hv,
                          tooltips=[('Date', '@date_str'), ('Total', '@total')] + [(string, '@{%s}{0}'%string) for string in stacks],
    #                       formatters={'@date_str' : 'datetime', # use 'datetime' formatter for 'date' field
    #                                  },
                         ))

    # def dt2ind(dt):
    # #     y = dt.year + dt.day/int(pd.Timestamp(dt.year,12,31).strftime('%j'))
    #     return df.loc[df['date']==dt].index[0]

    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2011/06/07')), line_width=1.5, line_dash='dotdash', color='red', alpha=1, legend_label= "failed eruption (2011/06/07)")
    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2013/11/28')), line_width=1.5, line_dash='dotdash', color='purple', alpha=1, legend_label= "Comet ISON (2013/11/28)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2017/09/06')), x2=dt2ind(pd.Timestamp('2017/09/10')), fill_color='teal', fill_alpha=1, legend_label= "large flares (2017/09/06-09)")

    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2011/08/11')), x2=dt2ind(pd.Timestamp('2011/09/18')), fill_color='gray', fill_alpha=0.3, legend_label= "GSFC server repair (2011/08/11 - 2011/09/18)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2013/10/01')), x2=dt2ind(pd.Timestamp('2013/10/16')), fill_color='green', fill_alpha=0.3, legend_label= "U.S. Fed. Gov. shutdown (2013/10/01 - 2013/10/16)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2015/02/04')), x2=dt2ind(pd.Timestamp('2015/09/23')), fill_color='red', fill_alpha=0.3, legend_label= "GSFC server down (2015/02/04 - 2015/09/23)")


    # df_stats = pd.DataFrame({'width': np.linspace(df['index'].min(), df['index'].max(), 2),
    #                          'mean_hv':np.nanmean(df['hv_frac']), 'mean_embed':np.nanmean(df['em_frac']), 'mean_Jhv':np.nanmean(df['Jhv_frac'])})

    # p.line(y='mean_hv', x='width', line_color = "red", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org movie requests (%.3f)"%(df_stats['mean_hv'][0]), source=df_stats)

    # p.line(y='mean_embed', x='width', line_color = "pink", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org Embed requests (%.3f)"%(df_stats['mean_embed'][0]), source=df_stats)

    # p.line(y='mean_Jhv', x='width', line_color = "cyan", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of JHelioviewer movie requests (%.3f)"%(df_stats['mean_Jhv'][0]), source=df_stats)

    p.xaxis.major_label_overrides = {i: date.strftime('%Y %b %d') for i, date in enumerate(df['date'])}

    p.x_range.range_padding = 0.06
    p.y_range.range_padding = 0.02

    p.legend.background_fill_alpha = 0.6
    p.border_fill_color = "whitesmoke"
    p.legend.location='top_left'
    # show(p)
    panel = Panel(child=p, title='Total')
    panels.append(panel)


# In[ ]:


df = frac
if not df.empty:
    TOOLS = "save, pan, box_zoom, reset, wheel_zoom"

    p = figure(plot_height=250, output_backend='webgl',
               tools=TOOLS,
               sizing_mode="scale_width", min_border_left = 0,
    #            tooltips="$name @date: @$name"
              )

    p.add_layout(Title(text = "Helioviewer fractional usage comparison of endpoint categories", text_font_size = "16pt", text_font_style="bold"),
                 place = 'above')
    p.add_layout(Title(text = "Date Range: %s - %s"%(df['date'].min().strftime('%Y, %b %d'),df['date'].max().strftime('%Y, %b %d'))),
                 place = 'above')

    p.background_fill_color="#f5f5f5"
    p.grid.grid_line_color="white"
    p.xaxis.axis_label = 'Date'
    p.yaxis.axis_label = 'Fractional usage (%)'
    p.axis.axis_line_color = None



    stacks = df.columns.values[4:]
    color_palette = bp.Category20[max(3,len(stacks))][:len(stacks)]

    p_hv = p.vbar_stack(stackers=stacks,
                        x='index', width=0.75,
                        color = color_palette,
                        source=ColumnDataSource(df),
                        legend_label=["%s"%string for string in stacks])

    p.add_tools(HoverTool(renderers=p_hv,
                          tooltips=[('Date', '@date_str'), ('Total', '@total')] + [(string, '@{%s}{0.00}%%'%string) for string in stacks],
    #                       formatters={'@date_str' : 'datetime', # use 'datetime' formatter for 'date' field
    #                                  },
                         ))

    # def dt2ind(dt):
    # #     y = dt.year + dt.day/int(pd.Timestamp(dt.year,12,31).strftime('%j'))
    #     return df.loc[df['date']==dt].index[0]

    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2011/06/07')), line_width=1.5, line_dash='dotdash', color='red', alpha=1, legend_label= "failed eruption (2011/06/07)")
    # p.line(y=[0,1], x=dt2ind(pd.Timestamp('2013/11/28')), line_width=1.5, line_dash='dotdash', color='purple', alpha=1, legend_label= "Comet ISON (2013/11/28)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2017/09/06')), x2=dt2ind(pd.Timestamp('2017/09/10')), fill_color='teal', fill_alpha=1, legend_label= "large flares (2017/09/06-09)")

    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2011/08/11')), x2=dt2ind(pd.Timestamp('2011/09/18')), fill_color='gray', fill_alpha=0.3, legend_label= "GSFC server repair (2011/08/11 - 2011/09/18)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2013/10/01')), x2=dt2ind(pd.Timestamp('2013/10/16')), fill_color='green', fill_alpha=0.3, legend_label= "U.S. Fed. Gov. shutdown (2013/10/01 - 2013/10/16)")
    # p.harea(y=[0,1], x1=dt2ind(pd.Timestamp('2015/02/04')), x2=dt2ind(pd.Timestamp('2015/09/23')), fill_color='red', fill_alpha=0.3, legend_label= "GSFC server down (2015/02/04 - 2015/09/23)")


    # df_stats = pd.DataFrame({'width': np.linspace(df['index'].min(), df['index'].max(), 2),
    #                          'mean_hv':np.nanmean(df['hv_frac']), 'mean_embed':np.nanmean(df['em_frac']), 'mean_Jhv':np.nanmean(df['Jhv_frac'])})

    # p.line(y='mean_hv', x='width', line_color = "red", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org movie requests (%.3f)"%(df_stats['mean_hv'][0]), source=df_stats)

    # p.line(y='mean_embed', x='width', line_color = "pink", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of Helioviewer.org Embed requests (%.3f)"%(df_stats['mean_embed'][0]), source=df_stats)

    # p.line(y='mean_Jhv', x='width', line_color = "cyan", line_dash='dotted', line_width= 2, alpha=0.5,
    #        legend_label="Mean fraction of JHelioviewer movie requests (%.3f)"%(df_stats['mean_Jhv'][0]), source=df_stats)

    p.xaxis.major_label_overrides = {i: date.strftime('%Y %b %d') for i, date in enumerate(df['date'])}

    p.x_range.range_padding = 0.06
    p.y_range.range_padding = 0.02

    p.legend.background_fill_alpha = 0.6
    p.border_fill_color = "whitesmoke"
    p.legend.location='top_left'

    panel = Panel(child=p, title='Fractional')
    panels.append(panel)
    # panels[1] = panel
    tabs = Tabs(tabs=panels)
    # show(tabs)


    # In[ ]:


    save(tabs, filename='%s/hv_endpoints_categorical.html'%directory, title='Helioviewer usage comparison of endpoint categories')
    print("Helioviewer usage comparison of endpoint categories completed")


# In[ ]:


print("ALL PROCSESSES COMPLETED in %d minutes" %((time.time()-master_time)/60))
