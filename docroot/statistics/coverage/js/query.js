/**
 * Finds dashboards from the Superset API using a query filter.
 *
 * @async
 * @param {string} url - The base URL of the Superset instance
 * @param {string} token - The guest token for authentication
 * @param {Object} query - The query object to filter dashboards
 * @returns {Promise<Object>} A promise that resolves to the dashboard data
 * @throws {Error} If the fetch request fails or the response is invalid
 */
async function findDashboards(url, token, query) {
    const queryString = encodeURIComponent(JSON.stringify(query));
    const response = await fetch(`${url}/api/v1/dashboard/?q=${queryString}`, {
        method: 'GET',
        headers: headers(token)
    });
    const data = await response.json();
    return data;
}

async function getDashboardUUID(url, token, id) {
    const response = await fetch(`${url}/api/v1/dashboard/${id}/embedded`, {
        method: 'GET',
        headers: headers(token)
    });
    return await response.json();
}

function headers(token) {
  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  };
}
