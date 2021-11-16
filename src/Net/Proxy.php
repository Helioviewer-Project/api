<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Helioviewer Net_Proxy Class Definition
 * Provides proxy functionality to enable remote AJAX requests and to assist
 * in distributed server-mode.
 *
 * There are two ways for the request to be redirected: Using
 * file_get_contents to mirror the contents
 * of the remote page, or with cURL. cURL should be used for requests which
 * may take longer since it provides
 * more flexibility with regard to the request timeout length.
 *
 * @category Net
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Net_Proxy {

    private $_baseURL;

    /**
     * Net_Proxy constructor
     *
     * @param string $baseURL Base URL to query
     *
     * @return void
     */
    public function __construct($baseURL) {
        $this->_baseURL = $baseURL;
    }

    /**
     * Queries remote site and displays results
     *
     * @param array $params Query parameters
     * @param bool  $curl   If true then cURL will be used to send request
     *
     * @return mixed Contents of mirrored page
     */
    public function query($params=array(), $curl=false) {

        $url = $this->_baseURL.http_build_query($params);

        if ($curl) {
            // Fetch Results
            $curl_handle=curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $url);
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
			
			if(!empty(HV_PROXY_HOST)){
				curl_setopt($curl_handle, CURLOPT_PROXY, HV_PROXY_HOST);
				if(!empty(HV_PROXY_USER_PASSWORD)){
					curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, HV_PROXY_USER_PASSWORD);
				}
			}

            $results = curl_exec($curl_handle);
            curl_close($curl_handle);
            return $results;
        }
        else {
            return @file_get_contents($url);
        }
    }

    /**
     * Performs a POST request
     *
     *  @param array $params Query parameters
     *  @param bool  $curl   Whether or not to use cURL to perform the query
     *
     *  @return $string query response text
     */
    public function post($params=array(), $curl=false) {
        $url = $this->_baseURL;

        if ($curl) {
            // Fetch Results
            $curl_handle=curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $url);
            curl_setopt($curl_handle, CURLOPT_POST, 1);
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $params);
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
			
			if(!empty(HV_PROXY_HOST)){
				curl_setopt($curl_handle, CURLOPT_PROXY, HV_PROXY_HOST);
				if(!empty(HV_PROXY_USER_PASSWORD)){
					curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, HV_PROXY_USER_PASSWORD);
				}
			}

            $results = curl_exec($curl_handle);
            curl_close($curl_handle);
            return $results;
        }
        else {
            return @file_get_contents($url);
        }
    }
}
?>