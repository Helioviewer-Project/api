<?php
    date_default_timezone_set('UTC');
    const NO_ATTRIBUTION = "";

    // Data providers
    $PROVIDERS = array(
        "lmsal"    => genProviderLink("LMSAL", "https://www.lmsal.com"),
        "stanford" => genProviderLink("Stanford", "http://jsoc.stanford.edu"),
        "sdac"     => genProviderLink("SDAC", "https://umbra.nascom.nasa.gov"),
        "proba2"   => genProviderLink("PROBA2", "https://proba2.sidc.be"),
        "solo"     => genProviderLink("EUI", "https://www.sidc.be/EUI/intro"),
        "suvi"     => genRobLink("SUVI", "https://www.ncei.noaa.gov/products/goes-r-solar-ultraviolet-imagers-suvi"),
        "ncar"     => genProviderLink("NCAR", "https://www2.hao.ucar.edu/mlso/instruments/cosmo-k-coronagraph-k-cor"),
        "harvard"  => genProviderLink("Harvard", "https://xrt.cfa.harvard.edu/"),
        "MSU"      => genProviderLink("MSU", "http://ylstone.physics.montana.edu/ylegacy/"),
        "RHESSI"   => genProviderLink("RHESSI", "https://hesperia.gsfc.nasa.gov/rhessi3/")
    );

    // Attribution
    $ATTRIBUTIONS = array(
        "AIA"    => $PROVIDERS['lmsal'] . " / " . $PROVIDERS['stanford'],
        "HMI"    => $PROVIDERS['lmsal'] . " / " . $PROVIDERS['stanford'],
        "EIT"    => $PROVIDERS['sdac'],
        "MDI"    => $PROVIDERS['sdac'],
        "LASCO"  => $PROVIDERS['sdac'],
        "SECCHI" => $PROVIDERS['sdac'],
        "SWAP"   => $PROVIDERS['proba2'],
        "EUI"    => $PROVIDERS['solo'],
        "SUVI"   => $PROVIDERS['suvi'],
        "COSMO"  => $PROVIDERS['ncar'],
        "XRT"    => $PROVIDERS['harvard'],
        "SXT"    => $PROVIDERS['MSU'],
        "RHESSI" => $PROVIDERS['RHESSI']
    );

    const TABLE_ROW_TEMPLATE = "<tr class='%s'><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td align='center'>%s</td></tr>";

    function formatDate(?DateTime $date) {
        if ($date == null) {
            return "N/A";
        }

        $str = $date->format('M j Y H:i:s');
        return $str;
    }

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
    function starts_with(string $str, $start) {
        return strpos($str, $start) === 0;
    }

    function MissionStatusMessage(string $instrument) {
        if (starts_with($instrument, "EUVI-B") ||
            starts_with($instrument, "COR1-B") ||
            starts_with($instrument, "COR2-B") ||
            starts_with($instrument, "TRACE")  ||
            starts_with($instrument, "SXT")) {
            return "Inactive";
        }
        if (starts_with($instrument, "COSMO")) {
            return '<a href="https://www2.hao.ucar.edu/mlso">Inactive</a>';
        }
        return "Active";
    }

    function genProviderLink($name, $url) {
        return "<a class='provider-link' href='$url' target='_blank'>$name</a>";
    }

    function genRobLink($name, $url) {
        return genProviderLink("ROB", "http://swhv.oma.be") . " / " . genProviderLink($name, $url);
    }

    function genCoverageLink($source) {
        $coverage_page = "/statistics/coverage.php";
        return "<a href=".$coverage_page.">$source</a>";
    }

    function safe_max($arr) {
        if (count($arr) >= 1) {
            return max($arr);
        }
    }

    function safe_min($arr) {
        if (count($arr) >= 1) {
            return min($arr);
        }
    }

    $dt = new DateTime();
    $now = formatDate($dt);
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
        <th width='150'>Image</th>
        <th width='140'>Oldest</th>
        <th width='140'>Latest</th>
        <th width='120'>Source</th>
        <th width='70'>Mission</th>
        <th width='60' align='center'>Status <span id='info'>(?)</span></th>
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

        function genTableHeaderRow($classes, $datasource, $oldestDate, $newestDate, $attribution, $icon, $mission) {
            return sprintf(TABLE_ROW_TEMPLATE, $classes, $datasource, $oldestDate, $newestDate, $attribution, $mission, $icon);
        }

        function genTableRow($classes, $datasource, $oldestDate, $newestDate, $attribution, $icon, $mission) {
            return genTableHeaderRow($classes, genCoverageLink($datasource), $oldestDate, $newestDate, $attribution, $icon, $mission);
        }


        function DateTimeFromString($date) {
            $timestamp = strtotime($date ?? "");
            $datetime = new DateTime();
            $datetime->setTimestamp($timestamp);
            return $datetime;
        }

        function _getDate($sourceId, $getDateFn) {
            $imgIndex = new Database_ImgIndex();
            $date = $imgIndex->$getDateFn($sourceId);
            if ($date != null) {
                return DateTimeFromString($date);
            }
        }

        function getNewestDate($sourceId) {
            return _getDate($sourceId, "getNewestData");
        }

        function getOldestDate($sourceId) {
            return _getDate($sourceId, "getOldestData");
        }

        function array_push_if_not_null(&$arr, $value) {
            if (!is_null($value)) {
                array_push($arr, $value);
            }
        }

        $config = new Config("../../settings/Config.ini");

        // Current time
        $now = time();

        $imgIndex = new Database_ImgIndex();

        // Get a list of the datasources grouped by instrument
        $instruments = $imgIndex->getDataSourcesByInstrument();
        // Create table of datasource statuses
        foreach($instruments as $name => $datasources) {
            $oldest = array(
                "level"    => 0,
                "datetime" => new DateTime(),
                "icon"     => null
            );
            $newestForInstrument = array();
            $oldestForInstrument = array();
            $maxElapsed = 0;
            $oldestDate = null;
            $subTableHTML = "";

            // Create table row for a single datasource
            foreach($datasources as $ds) {

				if($ds['id'] >= 10000){
					continue;
				}
                $missionActive = MissionStatusMessage($ds['name']);

                // Determine status icon to use
                $date = $imgIndex->getNewestData($ds['id']);
                $elapsed = $now - strtotime($date ?? "");
                $level = computeStatusLevel($elapsed, $name);

                // Create status icon
                $icon = getStatusIcon($level);

                // Convert to human-readable date
                $datetime = DateTimeFromString($date);

                // CSS classes for row
                $classes = "datasource $name";

                $oldestDate = getOldestDate($ds['id']);
                $newestDate = getNewestDate($ds['id']);
                // create HTML for subtable row
                $subTableHTML .= genTableRow($classes, $ds['name'], formatDate($oldestDate), formatDate($newestDate), NO_ATTRIBUTION, $icon, $missionActive);

                // If the elapsed time is greater than previous max store it
                if ($datetime < $oldest['datetime']) {
                    $oldest = array(
                        'level'   => $level,
                        'datetime' => $datetime,
                        'icon'     => $icon
                    );
                }

                array_push_if_not_null($newestForInstrument, $newestDate);
                array_push_if_not_null($oldestForInstrument, $oldestDate);
            }


            // Only include datasources with data
            if ($oldest['datetime'] and $name !=="MDI") {
                if (isset($ATTRIBUTIONS[$name])) {
                    $attribution = $ATTRIBUTIONS[$name];
                } else {
                    $attribution = "";
                }

                $datetime = $oldest['datetime'];
                $missionActive = MissionStatusMessage($name);
                $newestDateStr = formatDate(safe_max($newestForInstrument));
                $oldestDateStr = formatDate(safe_min($oldestForInstrument));
                $observatory = $imgIndex->getObservatoryForInstrument($name);

                $row = genTableHeaderRow("instrument", "$observatory / $name", $oldestDateStr, $newestDateStr, $attribution, $oldest['icon'], $missionActive);
                echo $row;
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
