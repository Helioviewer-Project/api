/**
 * Fetches a guest token from the specified URL.
 *
 * @async
 * @param {string} url - The URL endpoint to fetch the guest token from
 * @param {string} body - The JSON string body to send in the POST request
 * @returns {Promise<string>} A promise that resolves to the guest token
 * @throws {Error} If the fetch request fails or the response is invalid
 */
async function fetchGuestToken(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body ?? "")
    });
    const data = await response.json();
    return data.token;
}
