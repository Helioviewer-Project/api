<?php
require_once '../../src/Config.php';
$config = new Config('../../settings/Config.ini');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Helioviewer.org - Data Coverage Statistics</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        #main {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background-color: #1a1a1a;
        }
        #header {
            flex-shrink: 0;
            background-color: #2a2a2a;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #header img {
            height: 30px;
            width: auto;
        }
        #headerText {
            color: #e0e0e0;
            font-size: 14px;
            margin: 0;
        }
        #dashboard-container {
            flex: 1;
            width: 100vw;
            min-height: 0;
            margin: 0;
            padding: 0;
        }
        #dashboard-container iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        #error-message {
            display: none;
            padding: 10px;
            margin: 10px;
            background-color: #5c1c1c;
            color: #ff8a80;
            border-radius: 4px;
        }
        #loading-message {
            padding: 10px;
            margin: 10px;
            text-align: center;
            font-size: 14px;
            color: #e0e0e0;
        }
    </style>
</head>

<body>
    <div id="main">
        <div id="header">
            <img src="../resources/images/logos/hvlogo1s_transparent_logo.png" alt="Helioviewer logo" />
            <div id='headerText'>The Helioviewer Project - Data Coverage</div>
        </div>

        <div id="loading-message">Loading dashboard...</div>
        <div id="error-message"></div>
        <div id="dashboard-container"></div>
    </div>

    <script src="https://unpkg.com/@superset-ui/embedded-sdk"></script>
    <script type="text/javascript">
        const DASHBOARD_ID = '<?= HV_SUPERSET_COVERAGE_DASHBOARD_ID ?>';
        const GUEST_TOKEN_URL = '<?= HV_SUPERSET_SIDECAR_URL ?>/guest_token.php';
        const SUPERSET_URL = '<?= HV_SUPERSET_URL ?>';

        async function fetchGuestToken() {
            try {
                const response = await fetch(GUEST_TOKEN_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        dashboard_id: DASHBOARD_ID
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data.success || !data.token) {
                    throw new Error('Failed to retrieve guest token from response');
                }

                return data.token;
            } catch (error) {
                console.error('Error fetching guest token:', error);
                throw error;
            }
        }

        async function embedDashboard() {
            try {
                const guestToken = await fetchGuestToken();

                // Hide loading message
                document.getElementById('loading-message').style.display = 'none';

                // Embed the dashboard
                supersetEmbeddedSdk.embedDashboard({
                    id: DASHBOARD_ID,
                    supersetDomain: SUPERSET_URL,
                    mountPoint: document.getElementById('dashboard-container'),
                    fetchGuestToken: () => guestToken,
                    dashboardUiConfig: {
                        hideTitle: true,
                        hideChartControls: false,
                        hideTab: false,
                    },
                });
            } catch (error) {
                console.error('Error embedding dashboard:', error);
                document.getElementById('loading-message').style.display = 'none';
                const errorDiv = document.getElementById('error-message');
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Failed to load dashboard: ' + error.message;
            }
        }

        // Initialize dashboard when page loads
        window.addEventListener('load', embedDashboard);
    </script>
</body>
</html>
