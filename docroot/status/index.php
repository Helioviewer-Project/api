<?php
    date_default_timezone_set('UTC');
    $dt = new DateTime();
    $now = $dt->format('Y-m-d H:i:s');

    function _pretty_time($time) {
        $seconds = intval($time);
        // Convert seconds to minutes:seconds
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;

        // Convert minutes to hours:minutes
        $hours = floor($minutes / 60);
        $minutes -= $hours * 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Helioviewer.org - Data Monitor</title>
    <link rel="stylesheet" href="status.css" />
    <script src="//code.jquery.com/jquery.min.js" type="text/javascript"></script>
    <script src="status.js" type="text/javascript"></script>
</head>
<body>
    <div id='main'>
	<div id="header">
        <a href='http://www.helioviewer.org'><img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" /></a>
        <div id='headerText'>The Helioviewer Project - Data Monitor</div>
        <div id='currentTime'>Current time: <?php echo $now;?></div>
    </div>

    <!-- Legend -->
    <div id='legend-container'>
        <div id='legend'>
            <img class='status-icon' src='icons/status_icon_green.png' alt='green status icon' />
            <span class='status-text'>Up to date</span>
            <img class='status-icon' src='icons/status_icon_yellow.png' alt='yellow status icon' />
            <span class='status-text'>Lagging</span>
            <img class='status-icon' src='icons/status_icon_orange.png' alt='orange status icon' />
            <span class='status-text'>Lagging a lot</span>
            <img class='status-icon' src='icons/status_icon_red.png' alt='red status icon' />
            <span class='status-text'>Uh oh!</span>
            <img class='status-icon' src='icons/status_icon_gray.png' alt='gray status icon' />
            <span>Inactive</span>
        </div>
    </div>

    <table id='statuses'>
    <tr id='status-headers'>
        <th width='100'>Image</th>
        <th width='150'>Latest Image</th>
        <th width='150'>Source</th>
        <th width='150'>Mission Dates</th>
        <th width='150'>Home Page</th>
        <th width='75' align='center'>Status <span id='info'>(?)</span></th>
    </tr>
    <?php
        include_once "../../src/Database/ImgIndex.php";
        include_once "../../src/Config.php";

        /**
         * computeStatusLevel
         *
         * @param {int}    $elapsed
         * @param {string} $inst
         */
        function computeStatusLevel($elapsed, $inst) {
            // Default values
            $t1 = 7200;  // 2 hrs
            $t2 = 14400; // 4 hrs
            $t3 = 43200; // 12 hrs
            $t4 = 604800; // 1 week

            if ($inst == "EIT") {
                $t1 = 14 * 3600;
                $t2 = 24 * 3600;
                $t3 = 48 * 3600;
            } else if ($inst == "HMI") {
                $t1 = 4 * 3600;
                $t2 = 8 * 3600;
                $t3 = 24 * 3600;
            } else if ($inst == "LASCO") {
                $t1 = 8 * 3600;
                $t2 = 12 * 3600;
                $t3 = 24 * 3600;
            } else if ($inst == "SECCHI") {
                $t1 = 84  * 3600;  // 3 days 12 hours
                $t2 = 120  * 3600; // 5 days
                $t3 = 144 * 3600;  // 6 days
            } else if ($inst == "SWAP") {
                $t1 = 4  * 3600;
                $t2 = 8  * 3600;
                $t3 = 12 * 3600;
            } else if ($inst == "XRT") {
                $t1 = 4 * 7 * 24 * 3600;
                $t2 = 5 * 7 * 24  * 3600;
                $t3 = 6 * 7 * 24 * 3600;
                $t4 = 7 * 7 * 24 * 3600;
            } else if ($inst == "COSMO") {
		$t1 = 24 * 3600;
		$t2 = 72 * 3600;
		$t3 = 168 * 3600;
	    }

            if ($elapsed <= $t1) {
                return 1;
            } else if ($elapsed <= $t2) {
                return 2;
            } else if ($elapsed <= $t3) {
                return 3;
            } else if ($elapsed <= $t4){
                return 4;
            } else {
                return 5;
            }
        }

        /**
         * getStatusIcon
         *
         * @var unknown_type
         */
        function getStatusIcon($level) {
            $levels = array(
                1 => "green",
                2 => "yellow",
                3 => "orange",
                4 => "red",
                5 => "gray"
            );

            $icon = "<img class='status-icon' src='icons/status_icon_%s.png' alt='%s status icon' />";

            return sprintf($icon, $levels[$level], $levels[$level]);
        }

        $config = new Config("../../settings/Config.ini");

        // Current time
        $now = time();

        $imgIndex = new Database_ImgIndex();

        // Get a list of the datasources grouped by instrument
        $instruments = $imgIndex->getDataSourcesByInstrument();

        $tableRow = "<tr class='%s'><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href='%s'>%s</a></td><td align='center'>%s</td></tr>";

        // Create table of datasource statuses
        foreach($instruments as $name => $datasources) {
            $newest = array(
                "level"    => 0,
                // Use a sufficiently old date as the starting point
                "datetime" => new DateTime("1950-01-01"),
                "icon"     => null
            );
            $maxElapsed = 0;
            $newestDate = null;
            $subTableHTML = "";

            // Create table row for a single datasource
            foreach($datasources as $ds) {

				if($ds['id'] >= 10000){
					continue;
				}

                // Determine status icon to use
                $date = $imgIndex->getNewestData($ds['id']);
                $elapsed = $now - strtotime($date);
                $level = computeStatusLevel($elapsed, $name);

                // Create status icon
                $icon = getStatusIcon($level);

                // Convert to human-readable date
                $timestamp = strtotime($date);

                $datetime = new DateTime();
                $datetime->setTimestamp($timestamp);

                // CSS classes for row
                $classes = "datasource $name";

                // create HTML for subtable row
                $subTableHTML .= sprintf($tableRow, $classes, "&nbsp;&nbsp;&nbsp;&nbsp;" . $ds['name'], $datetime->format('M j Y H:i:s'), "", "", "", $icon);

                // If the elapsed time is greater than previous max store it
                if ($datetime > $newest['datetime']) {
                    $newest = array(
                        'level'   => $level,
                        'datetime' => $datetime,
                        'icon'     => $icon
                    );
                }
            }

            // Data providers
            $providers = array(
                "lmsal"    => "<a class='provider-link' href='http://www.lmsal.com/' target='_blank'>LMSAL</a>",
                "stanford" => "<a class='provider-link' href='http://jsoc.stanford.edu/' target='_blank'>Stanford</a>",
                "sdac"     => "<a class='provider-link' href='http://umbra.nascom.nasa.gov/' target='_blank'>SDAC</a>"
            );

            // Attribution
            $attributions = array(
                "AIA"    => $providers['lmsal'] . " / " . $providers['stanford'],
                "HMI"    => $providers['lmsal'] . " / " . $providers['stanford'],
                "EIT"    => $providers['sdac'],
                "MDI"    => $providers['sdac'],
                "LASCO"  => $providers['sdac'],
                "SECCHI" => $providers['sdac']
            );

            $mission_dates = array (
                "AIA" => "2020/01/01 - Today",
                "COSMO" => "2020/01/01 - Today",
                "EIT" => "2020/01/01 - Today",
                "EUI" => "2020/01/01 - Today",
                "HMI" => "2020/01/01 - Today",
                "LASCO" => "2020/01/01 - Today",
                "SECCHI" => "2020/01/01 - Today",
                "SWAP" => "2020/01/01 - Today",
                "SXT" => "2020/01/01 - Today",
                "XRT" => "2020/01/01 - Today"
            );

            $mission_urls = array (
                "AIA"    => array("url" => "#", "text" => "AIA"),
                "COSMO"  => array("url" => "#", "text" => "COSMO"),
                "EIT"    => array("url" => "#", "text" => "EIT"),
                "EUI"    => array("url" => "#", "text" => "EUI"),
                "HMI"    => array("url" => "#", "text" => "HMI"),
                "LASCO"  => array("url" => "#", "text" => "LASCO"),
                "SECCHI" => array("url" => "#", "text" => "SECCHI"),
                "SWAP"   => array("url" => "#", "text" => "SWAP"),
                "SXT"    => array("url" => "#", "text" => "SXT"),
                "XRT"    => array("url" => "#", "text" => ""),
            );

            // Only include datasources with data
            if ($newest['datetime'] and $name !=="MDI") {
                $attribution = $attributions[$name] ?? "";
                $mission_range = $mission_dates[$name] ?? "";
                $url = $mission_urls[$name] ?? "";

                $datetime = $newest['datetime'];
                printf($tableRow, "instrument", $name, $datetime->format('M j Y H:i:s'), $attribution, $mission_range, $url["url"], $url["text"], $newest['icon']);
                print($subTableHTML);
            }
        }
    ?>
    </table>

    <br /><br />

    <h3>Data Injection</h3>
    <table id='statuses'>
    <tr id='status-headers'>
        <th width='120'>Source</th>
        <th width='50' align='center'>Status <span id='info'>(?)</span></th>
    </tr>
    <?php
	    $commands = unserialize(TERMINAL_COMMANDS);
		$output = shell_exec('ps -ef | grep python');

		foreach($commands as $cmd => $name){
	        if (strpos($output, $cmd) !== false) {
		        echo '<tr><td>'.$name.'</td><td align="center"><img class="status-icon" src="icons/status_icon_green.png" alt="Data Injection script running" /></td></tr>';
	        }else{
		        echo '<tr><td>'.$name.'</td><td align="center""><img class="status-icon" src="icons/status_icon_red.png" alt="Data Injection script not running" /></td></tr>';
	        }
	    }

    ?>

    <?php
    $SDO_weekly = date("M d Y H:i:s", filectime(HV_SDO_WEEKLY_LOG));
    $SDO_monthly = date("M d Y H:i:s", filectime(HV_SDO_MONTHLY_LOG));
    $SWAP_weekly = date("M d Y H:i:s", filectime(HV_SWAP_WEEKLY_LOG));
    $SWAP_monthly = date("M d Y H:i:s", filectime(HV_SWAP_MONTHLY_LOG));
    $SOHO_weekly = date("M d Y H:i:s", filectime(HV_SOHO_WEEKLY_LOG));
    $SOHO_monthly = date("M d Y H:i:s", filectime(HV_SOHO_MONTHLY_LOG));
    $STEREO_weekly = date("M d Y H:i:s", filectime(HV_STEREO_WEEKLY_LOG));
    $STEREO_monthly = date("M d Y H:i:s", filectime(HV_STEREO_MONTHLY_LOG));
    ?>
    </table>
    <h3>Alert Thresholds</h3>
    <table id='statuses'>
        <tr id='status-headers'>
            <th width='120'>Sources</th>
            <th width='120'>Lag Threshold (HH:mm:ss)</th>
        </tr>
        <?php
        $thresholds = parse_ini_file("../../scripts/availability_feed/thresholds.example.ini");
        $user_thresholds = parse_ini_file("../../scripts/availability_feed/thresholds.ini");
        // Get final thresholds by applying user overrides to the defaults.
        foreach (array_keys($user_thresholds) as $override) {
            $thresholds[$override] = $user_thresholds[$override];
        }

        // Render HTML for these thresholds
        foreach (array_keys($thresholds) as $source) {
            echo "<tr>";
            echo "<td>$source</td>";
            echo "<td>" . _pretty_time($thresholds[$source]) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    <h3>Data Backfill</h3>
    <table id='statuses'>
    <tr id='status-headers'>
        <th width='120'>Type</th>
        <th width='120'>Latest Backfill</th>
    </tr>
    <tr>
        <td><div class="tooltip">Weekly (?)
            <span class="tooltiptext">"Weekly" means "The backfill script is run once per day looking for data with observation times in a defined time range that is available for download, but is not on the Helioviewer server according to Helioviewer image database.  The time range is defined as the script execution time in UTC minus 7 days to minus 1 day."</span>
        </div></td>
    </tr>
        <tr class="weekly SDO"><td>&nbsp&nbsp&nbsp&nbsp SDO</td><td><?php echo $SDO_weekly ?></td></tr>
        <tr class="weekly SWAP"><td>&nbsp&nbsp&nbsp&nbsp SWAP</td><td><?php echo $SWAP_weekly ?></td></tr>
        <tr class="weekly SOHO"><td>&nbsp&nbsp&nbsp&nbsp SOHO</td><td><?php echo $SOHO_weekly ?></td></tr>
        <tr class="weekly STEREO"><td>&nbsp&nbsp&nbsp&nbsp STEREO</td><td><?php echo $STEREO_weekly ?></td></tr>
    <tr>
        <td><div class="tooltip">Monthly (?)
            <span class="tooltiptext">"Monthly" means "The backfill script is run on the 1st of the month looking for data with observation times in a defined time range that is available for download, but is not on the Helioviewer server according to Helioviewer image database.  The time range is defined as the script execution time in UTC minus 31 days to minus 1 day."</span>
        </div></td>
    </tr>
        <tr class="monthly SDO"><td>&nbsp&nbsp&nbsp&nbsp SDO</td><td><?php echo $SDO_monthly ?></td></tr>
        <tr class="monthly SWAP"><td>&nbsp&nbsp&nbsp&nbsp SWAP</td><td><?php echo $SWAP_monthly ?></td></tr>
        <tr class="monthly SOHO"><td>&nbsp&nbsp&nbsp&nbsp SOHO</td><td><?php echo $SOHO_monthly ?></td></tr>
        <tr class="monthly STEREO"><td>&nbsp&nbsp&nbsp&nbsp STEREO</td><td><?php echo $STEREO_monthly ?></td></tr>
    </table>


    <br />
    <div id='footer'><strong>Upstream: </strong>
        <a class='provider-link' href='http://aia.lmsal.com/public/SDOcalendar.html'>SDO Calendar</a>,
        <a class='provider-link' href='http://sdowww.lmsal.com/hek_monitor/sdo_pipeline_status.html'>SDO Pipeline Monitor</a>,
        <a class='provider-link' href='http://sdowww.lmsal.com/sdomedia/hek_monitor/status.html'>HEK Status</a>
    </div>
    </div>
</body>
</html>
