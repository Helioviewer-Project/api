<?php
require_once '../../src/Config.php';
$config = new Config('../../settings/Config.ini');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Helioviewer.org - Data Coverage</title>
    <link rel="stylesheet" href="superset.css" />
</head>

<body>
    <div id="main">
        <div id="header">
            <img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" />
            <div id='headerText'>The Helioviewer Project - Data Coverage</div>
        </div>

        <div class="loader" id="loading-message">Loading dashboard...</div>
        <div class="error" id="error-message"></div>
        <div class="dashboard" id="dashboard-container"></div>
    </div>

    <script src="https://unpkg.com/@superset-ui/embedded-sdk"></script>
    <script type="text/javascript" src="superset.js"></script>
    <script type="text/javascript">
        const DASHBOARD_ID = '<?= HV_SUPERSET_COVERAGE_DASHBOARD_ID ?>';
        const GUEST_TOKEN_URL = '<?= HV_SUPERSET_SIDECAR_URL ?>/guest_token.php';
        const SUPERSET_URL = '<?= HV_SUPERSET_URL ?>';
        window.addEventListener('load', () => embedDashboard(
          DASHBOARD_ID,
          document.getElementById('dashboard-container'),
          document.getElementById('loading-message'),
          document.getElementById('error-message'),
          SUPERSET_URL,
          () => fetchGuestToken(GUEST_TOKEN_URL, DASHBOARD_ID)));
    </script>
</body>
</html>
