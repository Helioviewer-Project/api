async function fetchGuestToken(guest_token_url, dashboard_id) {
    try {
        const response = await fetch(guest_token_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                dashboard_id: dashboard_id
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

async function embedDashboard(dashboard_container, loading_element, error_div, superset_url, getGuestToken) {
    try {
        const guestToken = await getGuestToken();

        // Hide loading message
        loading_element.style.display = 'none';

        // Embed the dashboard
        supersetEmbeddedSdk.embedDashboard({
            id: DASHBOARD_ID,
            supersetDomain: superset_url,
            mountPoint: dashboard_container,
            fetchGuestToken: () => guestToken,
            dashboardUiConfig: {
                hideTitle: true,
                hideChartControls: false,
                hideTab: false,
            },
        });
    } catch (error) {
        console.error('Error embedding dashboard:', error);
        loading_element.style.display = 'none';
        error_div.style.display = 'block';
        error_div.textContent = 'Failed to load dashboard: ' + error.message;
    }
}
