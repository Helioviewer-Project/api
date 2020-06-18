/**
 * Helioviewer Usage Statistics Functions
 * 2011/02/09
 */

/**
 *  Fetches the usage statistics for the specified time interval and displays summary text and graphs
 *
 *  @param timeInterval The time interval or resolution for which the usage statistics should be displayed, e.g.
 *                      hourly, daily, weekly, etc.
 *  @return void
 */
colors = ["#D32F2F", "#9bd927", "#27d9be", "#6527d9", "#0091EA", "#FF6F00", "#F06292", "#BA68C8", "#558B2F", "#FFD600"];

notificationKeys = ["movie-notifications-granted", "movie-notifications-denied"];

var initialTime = new Date();
var temporalResolution;
var pieHeightScale = 0.65;
var maxVisualizationSizePx = 800;
var refreshIntervalMinutes;
var refreshHandler;
var request;

var getUsageStatistics = function(timeInterval) {
    if(request){
        request.abort();
    }
    request = $.getJSON("../index.php", {action: "getUsageStatistics", resolution: timeInterval}, function (response) {
        initialTime = new Date();
        if(responseNoError(response)){//only continue if the server returned a payload without error
            setRefreshIntervalFromTemporalResolution();
            displayUsageStatistics(response, timeInterval);
            updateLastRefreshHeader();
        }else{
            refreshIntervalMinutes = 1; // shorten the retry period to 1 minute if error
            refreshHandler = setInterval(computeTimeTilAutoRefresh,1000,true);//invoke countdown with error
        }
    });
};

var responseNoError = function(response) {
    var responseKeys = Object.keys(response);
    return !responseKeys.includes("error");
}

var checkSessionTimeout = function () {
    var minutes = Math.abs((initialTime - new Date()) / 1000 / 60);
    if (minutes > refreshIntervalMinutes) {
        loadNewStatistics();
    } 
};

var loadNewStatistics = function(){
    clearInterval(refreshHandler);
    initialTime = new Date();
    if(temporalResolution == 'hourly' || temporalResolution == 'daily'){
        $('#loading').text("Refreshing...");
    }else{
        $('#loading').text("Refreshing... This will take a while...");
    }
    getUsageStatistics(temporalResolution); 
}

var setRefreshIntervalFromTemporalResolution = function (){
    //set auto-refresh intervals here
    refreshIntervals = {
	    "hourly" : 5,
        "daily"  : 60,
        "weekly" : 180,
        "monthly": 540,
        "yearly" : 1440
    };
    refreshIntervalMinutes =  refreshIntervals[temporalResolution];
}

var updateLastRefreshHeader = function() {
    $('#refresh').text("Data Displayed From: "+initialTime.toString());
}

/**
 * Computes Auto-refresh countdown
 */
var computeTimeTilAutoRefresh = function (error=false) {
    var currentTime = new Date();
    var refreshTime = (refreshIntervalMinutes * 60) - Math.abs((initialTime - currentTime) / 1000);
    if(refreshTime < 1){
        clearInterval(refreshHandler);
        refreshTimeFormatted = "0";
    }
    /*else if(refreshTime < 10){
        //remove leading hours, minutes, and tens of seconds (00:00:0)
        var refreshTimeFormatted = new Date(1000 * refreshTime).toISOString().substr(18, 1);
    }
    else if(refreshTime < 60){
        //remove leading hours and minutes (00:00)
        var refreshTimeFormatted = new Date(1000 * refreshTime).toISOString().substr(17, 2);
    }
    else if(refreshTime < 3600){
        //remove leading hours (00)
        var refreshTimeFormatted = new Date(1000 * refreshTime).toISOString().substr(14, 5);
    }*/else{
        //include hours
        var refreshTimeFormatted = new Date(1000 * refreshTime).toISOString().substr(11, 8);
        while(refreshTimeFormatted.startsWith("0") || refreshTimeFormatted.startsWith(":")){
            refreshTimeFormatted = refreshTimeFormatted.substring(1);
        }
    }
    if(error){
        $('#loading').text( "Connection Error! Retrying in " + refreshTimeFormatted);
    }else{
        $('#loading').text( "Auto-refresh in " + refreshTimeFormatted);
    }
}

/**
 * Handles the actual rendering of the usage statistics for the requested period
 *
 * @param data          Usage statistics
 * @param timeInterval  Query time interval
 *
 * @return void
 */
var displayUsageStatistics = function (data, timeInterval) {

    clearInterval(computeTimeTilAutoRefresh);
    refreshHandler = setInterval(computeTimeTilAutoRefresh,1000,false);

    var pieChartHeight, barChartHeight, barChartMargin, summaryRaw, max;
    var hvTypePieSummary = {};
    var notificationPieSummary = {};
    var movieSourcesSummary = data['movieCommonSources'];
    var movieLayerCountSummary = data['movieLayerCount'];
    var screenshotSourcesSummary = data['screenshotCommonSources'];
    var screenshotLayerCountSummary = data['screenshotLayerCount'];

    // Determine how much space is available to plot charts
    pieChartHeight = Math.min(0.42 * $(window).width(), $(window).height() , maxVisualizationSizePx);
    barChartHeight = Math.min( pieChartHeight / 3, maxVisualizationSizePx);

    // Overview text
    summaryRaw = data['summary'];
    summaryKeys = Object.keys(summaryRaw);
    for(var key of summaryKeys){
        if(notificationKeys.indexOf(key) == -1){//if key is not about notifications
            hvTypePieSummary[key] = summaryRaw[key];
        }else{//if key is about notifications
            notificationPieSummary[key] = summaryRaw[key];
        }
    }

    $('#barCharts').empty();

    displaySummaryText(timeInterval, summaryRaw);
 
    // Create summary pie chart
    createVersionChart('versionsChart', hvTypePieSummary, pieChartHeight);
    createRequestsChart('requestsChart', hvTypePieSummary, pieChartHeight);
    createNotificationChart('notificationChart', notificationPieSummary, pieChartHeight);
    createScreenshotSourcesChart('screenshotSourcesChart', screenshotSourcesSummary, pieChartHeight);
    createScreenshotLayerCountChart('screenshotLayerCountChart',screenshotLayerCountSummary, pieChartHeight);
    createMovieSourcesChart('movieSourcesChart', movieSourcesSummary, pieChartHeight);
    createMovieLayerCountChart('movieLayerCountChart',movieLayerCountSummary, pieChartHeight);


    // Create bar graphs for each request type
    createColumnChart('visitors', data['standard'], 'Helioviewer.org', barChartHeight, colors[8]);
    createColumnChart('getClosestImage', data['getClosestImage'], 'Observation', barChartHeight, colors[4]);
    createColumnChart('takeScreenshot', data['takeScreenshot'], 'Screenshot', barChartHeight, colors[0]);
    createColumnChart('buildMovie', data['buildMovie'], 'Movie', barChartHeight, colors[1]);
    createColumnChart('getJP2Image-web', data['getJP2Image-web'], 'JP2 Download', barChartHeight, colors[9]);
    createColumnChart('getJPX', data['getJPX'], 'JPX', barChartHeight, colors[2]);
    createColumnChart('embed', data['embed'], 'Embed', barChartHeight, colors[3]);
    createColumnChart('minimal', data['minimal'], 'Student', barChartHeight, colors[5]);
    createColumnChart('sciScript-SSWIDL', data['sciScript-SSWIDL'], 'SciScript SSWIDL', barChartHeight, colors[6]);
    createColumnChart('sciScript-SunPy', data['sciScript-SunPy'], 'SciScript SunPy', barChartHeight, colors[7]);
    createColumnChart('getRandomSeed', data['getRandomSeed'], 'getRandomSeed', barChartHeight, colors[4]);

    // Spreads bar graphs out a bit if space permits
    //barChartMargin = Math.round(($("#visualizations").height() - $("#barCharts").height()) / 7);
    //$(".columnChart").css("margin", barChartMargin + "px 0");
}

/**
 * Displays summary text to be shown at the top of the usage statistics page
 *
 * @param timeInterval  Time interval for which the statistics are shown
 * @param summary       Total counts for each type of request
 *
 * @return string HTML for the overview text block
 */
var displaySummaryText = function(timeInterval, summary) {
    var humanTimes, when, html;

    // Human readable text for the requested time intervals
    humanTimes = {
	    "hourly" : "day",
        "daily"  : "four weeks",
        "weekly" : "six months",
        "monthly": "two years",
        "yearly" : "eight years"
    };

    possibleResolutions = Object.keys(humanTimes);

    when = '<select id=temporalResolution>';
    for( var res=0; res<possibleResolutions.length; res++){
        when += '<option value="'+possibleResolutions[res]+'">'+humanTimes[possibleResolutions[res]]+'</option>';
    }
    when += '</select>';

    // Generate HTML
    html = '<span id="when">During the last <b>' + when + '</b> Helioviewer.org users created</span> ' +
           '<span style="color:' + colors[4] + ';" class="summaryCount">' + summary['getClosestImage'].toLocaleString() + ' observations</span>, '+
           '<span style="color:' + colors[0] + ';" class="summaryCount">' + summary['takeScreenshot'].toLocaleString() + ' screenshots</span>, ' +
           '<span style="color:' + colors[1] + ';" class="summaryCount">' + summary['buildMovie'].toLocaleString() + ' movies</span>, and ' +
           '<span style="color:' + colors[2] + ';" class="summaryCount">' + summary['getJPX'].toLocaleString() + ' JPX movies</span>. <br> ' +
           'Helioviewer.org was <span style="color:' + colors[3] + ';" class="summaryCount">embedded ' + summary['embed'].toLocaleString() + ' times </span> and '+
           '<span style="color:' + colors[5] + ';" class="summaryCount"> accessed by ' + summary['minimal'].toLocaleString() + ' students </span>';
    $("#overview").html(html);

    
    $("#temporalResolution").val(timeInterval).unbind().change(function(e){
        temporalResolution = $("#temporalResolution").val();
        loadNewStatistics();
    });

};

/**
 * Creates a bar chart representing the frequency of requests for the specified type across time
 *
 * @param id        Id of the dom element where bar chart should be placed
 * @param rows      Data rows
 * @param desc      Description ...
 * @param height    Height of the bar chart
 * @param color     Color to use for the bars
 *
 * @return void
 */
var createColumnChart = function (id, rows, desc, height, color) {
    // Build datatable
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Time');
    data.addColumn('number', desc);
    data.addRows(rows.length);

    // Set datatable values
    row = 0;
    $.each(rows, function (i, pair) {
        // Grab key and value from the pair
        for (key in pair) {
            data.setValue(row, 0, key);
            data.setValue(row, 1, pair[key]);
        }
        row++;
    });

    $("#barCharts").append('<div id="' + id + 'Chart" class="columnChart"></div>');

    chart = new google.visualization.ColumnChart(document.getElementById(id + "Chart"));
    chart.draw(data, {
        height: height,
        colors: [color],
        legend: 'none',
        title: 'Number of ' + desc + ' requests',
        hAxis: {slantedText: true}
    });
};

/**
 * Creates a pie chart to show proportion of each request type made
 *
 * @param id        Id of the dom element where pie chart should be placed
 * @param totals    Array containing counts for each type of query
 * @param size      Length of each side of the graph
 *
 * @return void
 */
var createRequestsChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();

    data.addColumn('string', 'Request');
    data.addColumn('number', 'Types of requests');

    data.addRows(4);

    data.setValue(0, 0, 'Screenshots');
    data.setValue(0, 1, totals['takeScreenshot']);
    data.setValue(1, 0, 'Movies');
    data.setValue(1, 1, totals['buildMovie']);
    data.setValue(2, 0, 'JPX Movies');
    data.setValue(2, 1, totals['getJPX']);
    data.setValue(3, 0, 'Sci Scripts');
    data.setValue(3, 1, totals['sciScript-SSWIDL'] + totals['sciScript-SunPy']);

    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Types of requests"});
};




var createVersionChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();

    data.addColumn('string', 'Request');
    data.addColumn('number', 'Helioviewer Version');

    data.addRows(3);

    data.setValue(0, 0, 'Standard');
    data.setValue(0, 1, totals['standard']);
    data.setValue(1, 0, 'Embeds');
    data.setValue(1, 1, totals['embed']);
    data.setValue(2, 0, 'Students');
    data.setValue(2, 1, totals['minimal']);

    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Helioviewer Version"});
};

var createNotificationChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();

    data.addColumn('string', 'Permission');
    data.addColumn('number', 'Movie Notifications');

    data.addRows(2);

    data.setValue(0, 0, 'Denied');
    data.setValue(0, 1, totals['movie-notifications-denied']);
    data.setValue(1, 0, 'Granted');
    data.setValue(1, 1, totals['movie-notifications-granted']);

    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Movie Notifications"});
};

var createMovieSourcesChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();
    var num = 0, maxNum = 10;
    var otherCount = 0;

    data.addColumn('string', 'Source');
    data.addColumn('number', 'Instances');

    //sort the keys by value
    var keysSorted = Object.keys(totals).sort(function(a,b){return totals[b]-totals[a]})
    //add appropirate number of rows
    data.addRows(Math.min(maxNum+1,keysSorted.length));//+1 because of the 'other' aggregate category

    //populate the chart
    for(var name of keysSorted){
        if(num<maxNum){//add top 10
            data.setValue(num, 0, name);
            data.setValue(num, 1, totals[name]);
            //increment counter
            num++;
        }else{//aggregate the rest into 'other'
            otherCount += totals[name];
        }
    }
    // add 'other' group to chart
    if(otherCount > 0){
        data.setValue(num, 0, 'Other');
        data.setValue(num, 1, otherCount);
    }
    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Movie Sources Breakdown"});
};

var createScreenshotSourcesChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();
    var num = 0, maxNum = 10;
    var otherCount = 0;

    data.addColumn('string', 'Source');
    data.addColumn('number', 'Instances');

    //sort the keys by value
    var keysSorted = Object.keys(totals).sort(function(a,b){return totals[b]-totals[a]})
    //add appropirate number of rows
    data.addRows(Math.min(maxNum+1,keysSorted.length));//+1 because of the 'other' aggregate category

    //populate the chart
    for(var name of keysSorted){
        if(num<maxNum){//add top 10
            data.setValue(num, 0, name);
            data.setValue(num, 1, totals[name]);
            //increment counter
            num++;
        }else{//aggregate the rest into 'other'
            otherCount += totals[name];
        }
    }
    // add 'other' group to chart
    if(otherCount > 0){
        data.setValue(num, 0, 'Other');
        data.setValue(num, 1, otherCount);
    }
    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Screenshot Sources Breakdown"});
};

var createScreenshotLayerCountChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();
    var num = 0, maxNum = 5;
    var otherCount = 0;

    data.addColumn('string', 'Layers');
    data.addColumn('number', 'Number');

    //sort the keys by value
    var keysSorted = Object.keys(totals).sort(function(a,b){return totals[b]-totals[a]})
    //add appropirate number of rows
    data.addRows(Math.min(maxNum,keysSorted.length));

    //populate the chart
    for(var name of keysSorted){
        data.setValue(num, 0, name);
        data.setValue(num, 1, totals[name]);
        //increment counter
        num++;
    }
    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Screenshot Layer Count"});
};

var createMovieLayerCountChart = function (id, totals, size) {
    var chart, width, data = new google.visualization.DataTable();
    var num = 0, maxNum = 5;
    var otherCount = 0;

    data.addColumn('string', 'Layers');
    data.addColumn('number', 'Number');

    //sort the keys by value
    var keysSorted = Object.keys(totals).sort(function(a,b){return totals[b]-totals[a]})
    //add appropirate number of rows
    data.addRows(Math.min(maxNum,keysSorted.length));

    //populate the chart
    for(var name of keysSorted){
        data.setValue(num, 0, name);
        data.setValue(num, 1, totals[name]);
        //increment counter
        num++;
    }
    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Movie Layer Count"});
};