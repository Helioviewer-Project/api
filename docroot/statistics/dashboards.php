<?php
require_once '../../src/Config.php';
$config = new Config('../../settings/Config.ini');

$dashboards = [
    ['key' => 'coverage', 'label' => 'Data Coverage', 'id' => HV_SUPERSET_COVERAGE_DASHBOARD_ID],
    ['key' => 'movies', 'label' => 'Movie Statistics', 'id' => HV_SUPERSET_MOVIE_STATS_DASHBOARD_ID],
    ['key' => 'jhelioviewer', 'label' => 'JHelioviewer Statistics', 'id' => HV_SUPERSET_JHELIOVIEWER_STATS_DASHBOARD_ID],
    ['key' => 'statistics', 'label' => 'Statistics', 'id' => HV_SUPERSET_STATISTICS_DASHBOARD_ID],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Helioviewer.org - Statistics Dashboards</title>
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
            font-size: 16px;
            margin: 0;
        }
        #tabs {
            flex-shrink: 0;
            display: flex;
            gap: 4px;
            background-color: #2a2a2a;
            padding: 0 10px;
            border-bottom: 1px solid #444;
        }
        .tab-button {
            background: none;
            border: none;
            color: #a0a0a0;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .tab-button:hover {
            color: #e0e0e0;
        }
        .tab-button.active {
            color: #e0e0e0;
            border-bottom: 2px solid #4a90d9;
        }
        #dashboards {
            flex: 1;
            position: relative;
            min-height: 0;
        }
        .dashboard-pane {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
        }
        .dashboard-pane.active {
            display: flex;
            flex-direction: column;
        }
        .dashboard-container {
            flex: 1;
            min-height: 0;
            width: 100%;
        }
        .dashboard-pane iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        .error-message {
            display: none;
            padding: 10px;
            margin: 10px;
            background-color: #5c1c1c;
            color: #ff8a80;
            border-radius: 4px;
        }
        .loading-message {
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
            <div id='headerText'>The Helioviewer Project - Statistics Dashboards</div>
        </div>

        <div id="tabs">
            <?php foreach ($dashboards as $i => $d): ?>
                <button class="tab-button<?= $i === 0 ? ' active' : '' ?>" data-key="<?= htmlspecialchars($d['key']) ?>"><?= htmlspecialchars($d['label']) ?></button>
            <?php endforeach; ?>
        </div>

        <div id="dashboards">
            <?php foreach ($dashboards as $i => $d): ?>
                <div class="dashboard-pane<?= $i === 0 ? ' active' : '' ?>" id="pane-<?= htmlspecialchars($d['key']) ?>">
                    <div class="loading-message">Loading dashboard...</div>
                    <div class="error-message"></div>
                    <div class="dashboard-container"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://unpkg.com/@superset-ui/embedded-sdk"></script>
    <script type="text/javascript">
        const SUPERSET_URL = '<?= HV_SUPERSET_URL ?>';
        const GUEST_TOKEN_URL = '<?= HV_SUPERSET_SIDECAR_URL ?>/guest_token.php';
        const DASHBOARDS = <?= json_encode($dashboards) ?>;

        const loaded = {};

        async function fetchGuestToken(dashboardId) {
            const response = await fetch(GUEST_TOKEN_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    dashboard_id: dashboardId
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
        }

        async function embedDashboardIfNeeded(dashboard) {
            if (loaded[dashboard.key]) {
                return;
            }
            loaded[dashboard.key] = true;

            const pane = document.getElementById('pane-' + dashboard.key);
            const loadingEl = pane.querySelector('.loading-message');
            const errorEl = pane.querySelector('.error-message');
            const containerEl = pane.querySelector('.dashboard-container');

            try {
                const guestToken = await fetchGuestToken(dashboard.id);

                loadingEl.style.display = 'none';

                const embedded = await supersetEmbeddedSdk.embedDashboard({
                    id: dashboard.id,
                    supersetDomain: SUPERSET_URL,
                    mountPoint: containerEl,
                    fetchGuestToken: () => guestToken,
                    dashboardUiConfig: {
                        hideTitle: true,
                        hideChartControls: false,
                        hideTab: false,
                    },
                });

                if (embedded && typeof embedded.setThemeMode === 'function') {
                    try {
                        embedded.setThemeMode('dark');
                    } catch (themeError) {
                        console.warn('Could not set dark theme for dashboard:', dashboard.key, themeError);
                    }
                }
            } catch (error) {
                console.error('Error embedding dashboard:', dashboard.key, error);
                loadingEl.style.display = 'none';
                errorEl.style.display = 'block';
                errorEl.textContent = 'Failed to load dashboard: ' + error.message;
                loaded[dashboard.key] = false;
            }
        }

        function selectTab(key) {
            document.querySelectorAll('.tab-button').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.key === key);
            });
            document.querySelectorAll('.dashboard-pane').forEach((pane) => {
                pane.classList.toggle('active', pane.id === 'pane-' + key);
            });

            const dashboard = DASHBOARDS.find((d) => d.key === key);
            if (dashboard) {
                embedDashboardIfNeeded(dashboard);
            }
        }

        document.querySelectorAll('.tab-button').forEach((btn) => {
            btn.addEventListener('click', () => selectTab(btn.dataset.key));
        });

        window.addEventListener('load', () => selectTab(DASHBOARDS[0].key));
    </script>
</body>
</html>
