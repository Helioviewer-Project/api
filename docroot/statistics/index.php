<?php
    $validResolutions = array("hourly", "daily", "weekly", "monthly", "yearly", "custom");
    if (isset($_GET['resolution']) && in_array($_GET['resolution'], $validResolutions)) {
        $resolution = $_GET['resolution'];
    } else {
        $resolution = "daily";
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="utf-8" />
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src="statistics.js?v=<?php echo md5_file(__DIR__ . '/statistics.js'); ?>"></script>
    <title>Helioviewer.org - Usage Statistics</title>
    <link rel='stylesheet' href='statistics.css' />
    <script type="text/javascript">
        google.load("jquery", "1.5");
        google.load("visualization", "1", {packages:["corechart"]});
        google.setOnLoadCallback(function (e) {
            temporalResolution = "<?php echo $resolution;?>";
            dateStart = "<?php echo $_GET['dateStart'] ?? ''; ?>";
            dateEnd = "<?php echo $_GET['dateEnd'] ?? ''; ?>";
            setRefreshIntervalFromTemporalResolution();
            getUsageStatistics(temporalResolution,dateStart,dateEnd);
            setInterval(checkSessionTimeout, 1000);
        });
    </script>
</head>

<body>
    <div id="loadingDiv">
        <span id="loading">Loading...</span>
        <div id="refresh"></div>
    </div>
	<div id="main">
		<div id="header">
            <img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" />
            <div id='headerText'>The Helioviewer Project - Recent Activity</div>
        </div>
        <div id="overview"></div>
        <div id="visualizations">
            <div id="pieChartsGroup">
                <div id="versionsChart"></div>
                <div id="requestsChart"></div>
                <div id="screenshotSourcesChart"></div>
                <div id="screenshotLayerCountChart"></div>
                <div id="movieSourcesChart"></div>
                <div id="movieLayerCountChart"></div>
                <div id="notificationChart"></div>
                <div id="deviceChart"></div>
            </div>
            <div id="barCharts"></div>
        </div>
        <div id="footer">
            Note: Helioviewer.org only collects information about types of queries made.  Helioviewer.org does not collect or store any information that could be used to identify users.
        </div>

	</div>
</body>
</html>