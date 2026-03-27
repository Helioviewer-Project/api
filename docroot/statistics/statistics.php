<?php
require_once '../../src/Config.php';
$config = new Config('../../settings/Config.ini');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Helioviewer.org - Statistics</title>
    <link rel="stylesheet" href="superset.css?v=2" />
</head>

<body>
    <div id="main">
        <div id="header">
            <img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" />
            <div id='headerText'>The Helioviewer Project - Data Coverage</div>
        </div>

        <div class="dashboard-box">
            <div id="endpoints-loader" class="loader">Loading endpoints dashboard...</div>
            <div id="endpoints-error" class="error"></div>
            <div id="endpoints-container" class="dashboard"></div>

            <div id="movies-loader" class="loader">Loading movies dashboard...</div>
            <div id="movies-error" class="error"></div>
            <div id="movies-container" class="dashboard"></div>

            <div id="movies-jpx-loader" class="loader">Loading jhelioviewer dashboard...</div>
            <div id="movies-jpx-error" class="error"></div>
            <div id="movies-jpx-container" class="dashboard"></div>
        </div>
    </div>

    <script src="https://unpkg.com/@superset-ui/embedded-sdk"></script>
    <script type="text/javascript" src="superset.js?v=1"></script>
    <script type="text/javascript">
        const GUEST_TOKEN_URL = '<?= HV_SUPERSET_SIDECAR_URL ?>/guest_token.php';
        const SUPERSET_URL = '<?= HV_SUPERSET_URL ?>';
        const dashboards = [{
            'container': document.getElementById('endpoints-container'),
            'loader': document.getElementById('endpoints-loader'),
            'error': document.getElementById('endpoints-error'),
            'id': '<?=  HV_SUPERSET_STATISTICS_DASHBOARD_ID ?>',
        }, {
            'container': document.getElementById('movies-container'),
            'loader': document.getElementById('movies-loader'),
            'error': document.getElementById('movies-error'),
            'id': '<?= HV_SUPERSET_MOVIES_DASHBOARD_ID ?>'
        }, {
            'container': document.getElementById('movies-jpx-container'),
            'loader': document.getElementById('movies-jpx-loader'),
            'error': document.getElementById('movies-jpx-error'),
            'id': '<?= HV_SUPERSET_MOVIES_JPX_DASHBOARD_ID ?>'
        }]

        dashboards.forEach((dashboard) => {
            embedDashboard(
                dashboard.id,
                dashboard.container,
                dashboard.loader,
                dashboard.error,
                SUPERSET_URL,
                () => fetchGuestToken(GUEST_TOKEN_URL, dashboard.id)
            )
        });
    </script>
</body>
</html>
