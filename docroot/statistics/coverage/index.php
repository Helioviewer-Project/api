<?php declare(strict_types=1);
require_once __DIR__.'/../../../src/Config.php';
$config = new Config(__DIR__.'/../../../settings/Config.ini');
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Helioviewer Data Coverage</title>
    <style>
      body {
          padding: 0;
          margin: 0;
          background-color: #f7f7f7;
      }

      html.fullscreen {
          overflow-y: hidden;
      }

      .hidden { display: none; }
      #main iframe {
          border: none;
      }

      #container {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2);
        grid-auto-rows: 1fr;
      }

      #container .dashboard {
          min-height: 700px;
      }

      #container .dashboard.fullscreen {
          height: 100vh;
      }

      .dashboard iframe {
          width: 100%;
          height: 100%;
      }
    </style>
  </head>
  <body>
    <main id="main">
      <div id="container"></div>
      <div id="error" class="error hidden">
        Something went wrong... Please try again later.
      </div>
    </main>

    <script src="https://unpkg.com/@superset-ui/embedded-sdk"></script>
    <!--<script type="text/javascript" src="https://cdn.jsdelivr.net/gh/vanjs-org/van/public/van-1.6.0.nomodule.min.js"></script>-->
    <script src="js/guest_token.js"></script>
    <script src="js/query.js"></script>

    <script>
    (async function () {
      try {
        // Acquire a guest token
        const token = await fetchGuestToken("<?=HV_SUPERSET_SIDECAR_URL?>/guest_token.php");
        const chart = (new URLSearchParams(window.location.search)).get("chart") ?? "Helioviewer Data Coverage";
        // Find the main data coverage dashboard
        const queryResult = await findDashboards("<?=HV_SUPERSET_URL?>", token, {
            columns: ["id"],
            filters: [
              {
                  col: "dashboard_title",
                  opr: "title_or_slug",
                  value: chart
              }
            ]
        })
        if (queryResult.count == 0) {
            throw `No ${chart} dashboard available.`
        } else {
            const responses = await Promise.all(queryResult.result.map((dashboard) => getDashboardUUID("<?=HV_SUPERSET_URL?>", token, dashboard.id)));
            const uuids = responses.map((response) => response.result.uuid);
            const dashboardToken = await fetchGuestToken("<?=HV_SUPERSET_SIDECAR_URL?>/guest_token.php", {"dashboard_id": uuids});
            const makeFullscreen = uuids.length == 1;
            uuids.map((uuid) => renderDashboard(uuid, dashboardToken, makeFullscreen));
            if (makeFullscreen) {
                document.body.parentElement.classList.add('fullscreen');
            }
        }
      } catch (e) {
          console.error(e);
          const error = document.getElementById('error');
          error.textContent = e.toString();
          error.classList.remove('hidden');
      }
    })()

    function renderDashboard(uuid, token, isFullscreen) {
        // Get a guest token for this dashboard
        const container = document.getElementById('container');
        const dashboard = document.createElement('div');
        dashboard.classList.add('dashboard');
        if (isFullscreen) {
            dashboard.classList.add('fullscreen');
        }
        container.appendChild(dashboard);
        supersetEmbeddedSdk.embedDashboard({
            id: uuid,
            supersetDomain: "<?=HV_SUPERSET_URL?>",
            mountPoint: dashboard,
            fetchGuestToken: () => token,
            dashboardUiConfig: {
                hideTitle: true,
                hideChartControls: false,
                hideTab: false
            },
        });
    }
    </script>
  </body>
</html>
