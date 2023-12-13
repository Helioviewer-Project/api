<?php
    // Valid resolution selectors
    const VALID_RESOLUTIONS = ["30m", "1h", "1D", "1W", "1M", "3M", "1Y"];
    const END_DATE_FORMAT = "Y-m-d\TH:i:s\Z";

    // Verify the requested resolution is one of the known valid selectors.
    $requestedResolution = $_GET['resolution'] ?? null;
    // If it's not one of the valid selectors, redirect to a valid page.
    if (!in_array($requestedResolution, VALID_RESOLUTIONS, true)) {
        header('location: /coverage/?resolution=1D');
        exit();
    }

    date_default_timezone_set("UTC");
    $utc = date("Y/m/d H:i e", time());
    $endDate = date(END_DATE_FORMAT, time());

    if (isset($_GET['endDate'])) {
        try {
            // Parse the requested end date as a date
            $requestedEndDate = new DateTimeImmutable($_GET['endDate']);
            // If parsing succeeds, update endDate with the desired endDate
            $endDate = $requestedEndDate->format(END_DATE_FORMAT);
        } catch (Throwable) {
            // Pass, default endDate is used.
        }
    }

    $now = '/coverage/?resolution=' . $requestedResolution
         . '&endDate=' . $endDate;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="utf-8" />
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src="coverage.js"></script>
    <title>Helioviewer.org - Data Coverage<?php echo ' - '.$utc; ?></title>
    <link rel='stylesheet' href='coverage.css' />
</head>

<body>
	<div id="main">
		<div id="header">
            <a href="<?php echo $now; ?>"><img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" /></a>
            <div id='headerText'>The Helioviewer Project - Data Coverage</div>
            <div class="resolutions">
                <a href="?resolution=30m<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='30m') { echo ' class="selected"'; } ?>>30 min</a>
                <a  href="?resolution=1h<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='1h') { echo ' class="selected"'; } ?>>1 hour</a>
                <a  href="?resolution=1D<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='1D') { echo ' class="selected"'; } ?>>1 day</a>
                <a  href="?resolution=1W<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='1W') { echo ' class="selected"'; } ?>>1 week</a>
                <a  href="?resolution=1M<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='1M') { echo ' class="selected"'; } ?>>1 month</a>
                <a  href="?resolution=3M<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='3M') { echo ' class="selected"'; } ?>>3 months</a>
                <a  href="?resolution=1Y<?php echo '&endDate='.$endDate;?>"<?php if ($_GET['resolution']=='1Y') { echo ' class="selected"'; } ?>>1 year</a>
            </div>
        </div>
		<div id="datePicker">
            <select id="yyyy" name="YYYY"></select> / <select id="mm" name="MM"></select> / <select id="dd" name="DD"></select>
        </div>
        <div id="timePicker">
            <select id="hour" name="hh"></select> :
            <select id="min" name="mm"></select>
            <?php echo date("e", time()); ?>
        </div>
        <div id="visualizations">
            <!--<div id="pieChart"></div>-->
            <div id="barCharts"></div>
        </div>
        <div id="footer">
        </div>
	</div>
</body>
</html>
