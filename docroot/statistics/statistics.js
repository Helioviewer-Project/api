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
var colors = ["#D32F2F", "#9bd927", "#27d9be", "#6527d9", "#0091EA", "#FF6F00", "#F06292", "#BA68C8", "#558B2F", "#FFD600", "#333"];

var heirarchy = {
    "Total":["total","rate_limit_exceeded"],
    "Client Sites":["standard","embed","minimal"],
    "Images":["takeScreenshot","postScreenshot","getTile","getClosestImage","getJP2Image-web","getJP2Image-jpip","getJP2Image","downloadScreenshot","getJPX","getJPXClosestToMidPoint", "downloadImage"],
    "Movies":["buildMovie","getMovieStatus","queueMovie","postMovie","reQueueMovie","playMovie","downloadMovie","getUserVideos","getObservationDateVideos","uploadMovieToYouTube","checkYouTubeAuth","getYouTubeAuth"],
    "Events":["getEventGlossary", "events", "getEvents","getFRMs","getEvent","getEventFRMs","getDefaultEventTypes","getEventsByEventLayers","importEvents"],
    "Data":["getRandomSeed","getDataSources","getJP2Header","getDataCoverage","getStatus","getNewsFeed","getDataCoverageTimeline","getClosestData","getSolarBodiesGlossary","getSolarBodies","getTrajectoryTime","sciScript-SSWIDL","sciScript-SunPy","getSciDataScript","updateDataCoverage","getEclipseImage","getClosestImageDatesForSources"],
    "Other":["shortenURL", "goto", "getUsageStatistics","movie-notifications-granted","movie-notifications-denied","logNotificationStatistics","launchJHelioviewer", "saveWebClientState", "getWebClientState"],
    "WebGL":["getTexture","getGeometryServiceData"]
};

var initialTime = new Date();
var temporalResolution;
var pieHeightScale = 0.65;
var maxVisualizationSizePx = 900;
var refreshIntervalMinutes;
var refreshHandler;
var request;
var dateStart,dateEnd;

var getUsageStatistics = function(timeInterval,dateStartInput,dateEndInput) {
    if(request){
        request.abort();
    }
    //initialize dateStart and dateEnd end if empty
    if(dateStartInput == "" || dateEndInput == ""){
        todayString = new Date().toISOString().split(".")[0] + "Z";//remove millis
        yesterdayUnix = Date.now() - 86400000;
        yesterday = new Date();
        yesterday.setTime(yesterdayUnix);
        yesterdayString = yesterday.toISOString().split(".")[0] + "Z";//remove millis

        dateStartInput = yesterdayString;
        dateEndInput = todayString;
        dateStart = yesterdayString;
        dateEnd = todayString;
    }
    request = $.getJSON("../index.php", {action: "getUsageStatistics", resolution: timeInterval, dateStart: dateStartInput, dateEnd: dateEndInput}, function (response) {
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

    if(temporalResolution != "custom"){
        getUsageStatistics(temporalResolution,dateStart,dateEnd);
    }else{
        getUsageStatistics(temporalResolution,dateStart,dateEnd);
    }
}

var setRefreshIntervalFromTemporalResolution = function (){
    //set auto-refresh intervals here
    refreshIntervals = {
	    "hourly" : 5,
        "daily"  : 60,
        "weekly" : 180,
        "monthly": 540,
        "yearly" : 1440,
        "custom" : 60
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
 * For new API endpoints, the data may not have values from 0 to <date API was introduced>.
 * This range from 0 to the date must be backfilled with 0's for the rest of the script to behave properly.
 * Not starting from 0 makes the object get treated like a JSON Object rather than an array,
 * the script expects an array, so this function will return the resulting array.
 */
function _zero_fill_missing_dates(data) {
    // If the data is an array, then return it. It's already the correct format
    if (Array.isArray(data)) {
        return data;
    }
    // Otherwise, convert the object into an array.
    let indices = Object.keys(data).map((val) => parseInt(val));
    // Sort the list to be safe, then reverse it so the highest value is first.
    indices.sort().reverse();
    // Get the final index
    let end = Math.max.apply(Math, indices);
    // Iterate from 0 to the end index, if the index doesn't exist, create a data point
    // with 0 counts. If the data does exist, then use it. The result here is an array
    // of data with all the correct statistical values.
    let out = [];
    for (let i = 0; i <= end; i++) {
        if (data.hasOwnProperty(i)) {
            out.push(data[i]);
        } else {
            out.push({"N/A": 0});
        }
    }
    return out;
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
    let deviceSummary = data['device_summary'];
    var movieSourcesSummary = data['movieCommonSources'];
    var movieLayerCountSummary = data['movieLayerCount'];
    var screenshotSourcesSummary = data['screenshotCommonSources'];
    var screenshotLayerCountSummary = data['screenshotLayerCount'];

    // Determine how much space is available to plot charts
    pieChartHeight = Math.min(0.42 * $(window).width(), $(window).height() , maxVisualizationSizePx);
    barChartHeight = Math.min( pieChartHeight / 3, maxVisualizationSizePx);

    // Overview text
    summaryRaw = data['summary'];

    $('#barCharts').empty();

    displaySummaryText(timeInterval, summaryRaw);

    // Create summary pie chart
    createVersionChart('versionsChart', summaryRaw, pieChartHeight);
    createRequestsChart('requestsChart', summaryRaw, pieChartHeight);
    createNotificationChart('notificationChart', summaryRaw, pieChartHeight);
    createScreenshotSourcesChart('screenshotSourcesChart', screenshotSourcesSummary, pieChartHeight);
    createScreenshotLayerCountChart('screenshotLayerCountChart',screenshotLayerCountSummary, pieChartHeight);
    createMovieSourcesChart('movieSourcesChart', movieSourcesSummary, pieChartHeight);
    createMovieLayerCountChart('movieLayerCountChart',movieLayerCountSummary, pieChartHeight);
    createDeviceChart('deviceChart', deviceSummary, pieChartHeight);

    var colorMod = 0;
    var excludeFromCharts = ['movieCommonSources','movieLayerCount','screenshotCommonSources','screenshotLayerCount','summary'];
    // Bar Charts
    var barChartsDiv = document.getElementById("barCharts");
    for( var group of Object.keys(heirarchy) ){
        var writeHeading = true;
        for(var key of heirarchy[group]){
            if(!excludeFromCharts.includes(key) && data['summary'][key] > 0){
                if(writeHeading){
                    var heading = document.createElement("button");
                    heading.innerText = group;
                    heading.id = group;
                    heading.className = "collapsible"
                    barChartsDiv.append(heading);
                    writeHeading = false;
                }
                var colorIndex = colorMod % colors.length;
                var innerContent = document.createElement("div");
                innerContent.id = key+"Chart";
                innerContent.className = "content columnChart";
                var heading = document.getElementById(group);
                barChartsDiv.append(innerContent);
                data[key] = _zero_fill_missing_dates(data[key]);
                console.log(key,data[key],key,barChartHeight,colors[colorIndex]);
                createColumnChart(key,data[key],key,barChartHeight,colors[colorIndex]);
                colorMod++;
            }
        }
    }


    var groupsElements = document.getElementsByClassName("collapsible");

    for (var groupButton of groupsElements) {
        groupButton.addEventListener("click", function() {
            var groupKey = this.id;
            this.classList.toggle("active"); //can start collapsed
            for(var contentId of heirarchy[groupKey]){
                var content = document.getElementById(contentId+"Chart");
                if(content){
                    if (content.style.maxHeight){
                        content.style.maxHeight = null;
                    } else {
                        content.style.maxHeight = content.scrollHeight + "px";
                    }
                    console.log(contentId + " " + content.style.maxHeight);
                }
            }
        });
        //start with all graphs open and showing (to start closed comment this out)
        var groupKey = groupButton.id;
        for(var contentId of heirarchy[groupKey]){
            var content = document.getElementById(contentId+"Chart");
            if(content){
                content.style.maxHeight = content.scrollHeight + "px";
            }
        }
    }

    // Create bar graphs for each request type
    /*
    createColumnChart('totalRequests', data['totalRequests'], 'Total', barChartHeight, colors[10]);
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
    */

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
        "yearly" : "eight years",
        "custom" : "time range"
    };

    possibleResolutions = Object.keys(humanTimes);

    when = '<select id=temporalResolution>';
    for( var res=0; res<possibleResolutions.length; res++){
        when += '<option value="'+possibleResolutions[res]+'">'+humanTimes[possibleResolutions[res]]+'</option>';
    }
    when += '</select>';
    //default date/times are empty and un-initialized and logic is below
    when += '<span id="timeRange"> from <input type="date" id="startDate"><input type="time" step="1" id="startTime"> to <input type="date" id="endDate"><input type="time" step="1" id="endTime"></span>'

    // Generate HTML
    html = '<span id="when">During the last ' + when + '</span><br><span>Helioviewer.org users created</span> ' +
           '<span style="color:' + colors[4] + ';" class="summaryCount">' + summary['getClosestImage'].toLocaleString() + ' observations</span>, '+
           '<span style="color:' + colors[0] + ';" class="summaryCount">' + summary['takeScreenshot'].toLocaleString() + ' screenshots</span>, ' +
           '<span style="color:' + colors[1] + ';" class="summaryCount">' + summary['buildMovie'].toLocaleString() + ' movies</span>, and ' +
           '<span style="color:' + colors[2] + ';" class="summaryCount">' + summary['getJPX'].toLocaleString() + ' JPX movies</span>. <br> ' +
           'Helioviewer.org was <span style="color:' + colors[3] + ';" class="summaryCount">embedded ' + summary['embed'].toLocaleString() + ' times </span> and '+
           '<span style="color:' + colors[5] + ';" class="summaryCount"> accessed by ' + summary['minimal'].toLocaleString() + ' students </span> <br>' +
           'Helioviewer.org users made <span style="color:'+colors[10]+';" class="summaryCount">' + summary['total'].toLocaleString() + ' total requests </span>';
    $("#overview").html(html);


    //date/time initilization logic
    if(temporalResolution == "custom"){
        //first-run empty date/time fields
        // if(dateStart == "" && dateEnd == ""){
        //     todayString = new Date().toISOString().split("T")[0];
        //     yesterdayUnix = Date.now()- 86400000;
        //     yesterday = new Date();
        //     yesterday.setTime(yesterdayUnix);
        //     yesterdayString = yesterday.toISOString().split("T")[0];
        //     document.getElementById("startDate").value = yesterdayString;
        //     document.getElementById("startTime").value = "00:00";
        //     document.getElementById("endDate").value = todayString;
        //     document.getElementById("endTime").value = "00:00";
        // }else{
            //second run use existing date/time
            document.getElementById("startDate").value = dateStart.slice(0,-1).split("T")[0];
            document.getElementById("startTime").value = dateStart.slice(0,-1).split("T")[1];
            document.getElementById("endDate").value = dateEnd.slice(0,-1).split("T")[0];
            document.getElementById("endTime").value = dateEnd.slice(0,-1).split("T")[1];
        // }
        $("#timeRange").show();
    }else{//any other temporalResolution (for example "daily")
        if(dateStart != "" && dateEnd != ""){//restore date/time even if it's hidden
            document.getElementById("startDate").value = dateStart.slice(0,-1).split("T")[0];
            document.getElementById("startTime").value = dateStart.slice(0,-1).split("T")[1];
            document.getElementById("endDate").value = dateEnd.slice(0,-1).split("T")[0];
            document.getElementById("endTime").value = dateEnd.slice(0,-1).split("T")[1];
        }
        $("#timeRange").hide();
    }

    //temporal resolution dropdown change event listener
    $("#temporalResolution").val(timeInterval).unbind().change(function(e){
        temporalResolution = $("#temporalResolution").val();
        if(temporalResolution == "custom"){
            //first-run empty date/time fields
            // if(dateStart == "" && dateEnd == ""){
            //     todayString = new Date().toISOString().split("T")[0];
            //     yesterdayUnix = Date.now()- 86400000;
            //     yesterday = new Date();
            //     yesterday.setTime(yesterdayUnix);
            //     yesterdayString = yesterday.toISOString().split("T")[0];
            //     document.getElementById("startDate").value = yesterdayString;
            //     document.getElementById("startTime").value = "00:00";
            //     document.getElementById("endDate").value = todayString;
            //     document.getElementById("endTime").value = "00:00";
            // }
            dateStart = document.getElementById("startDate").value + "T" + document.getElementById("startTime").value + "Z";
            dateEnd = document.getElementById("endDate").value + "T" + document.getElementById("endTime").value + "Z";
            $("#timeRange").show();
            loadNewStatistics();
        }else{
            $("#timeRange").hide();
            loadNewStatistics();
        }
    });

    //date/time change event listener
    $("#timeRange").children().unbind().change(function(e){
        dateStart = document.getElementById("startDate").value + "T" + document.getElementById("startTime").value + "Z";
        dateEnd = document.getElementById("endDate").value + "T" + document.getElementById("endTime").value + "Z";
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

    //$("#barCharts").append('<div id="' + id + 'Chart" class="columnChart content"></div>');

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

function createDeviceChart(id, deviceSummary, size) {
    var chart, width, data = new google.visualization.DataTable();

    data.addColumn('string', 'device');
    data.addColumn('number', 'requests');

    let devices = Object.keys(deviceSummary);

    //populate the chart
    for(let device of devices){
        data.addRows([[device, deviceSummary[device]]]);
    }

    chart = new google.visualization.PieChart(document.getElementById(id));
    chart.draw(data, {width: size, height: size*pieHeightScale, colors: colors, title: "Client Devices"});
};
