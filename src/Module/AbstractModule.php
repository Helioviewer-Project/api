<?php
/**
 * Helioviewer Abstract Module class definition.
 * Base class for all API modules providing common functionality.
 *
 * @category Application
 * @package  Helioviewer
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Module;

abstract class AbstractModule {

    /**
     * Send a JSON response with status code and message
     *
     * @param int    $code    HTTP status code
     * @param string $message Status message
     * @param mixed  $data    Response data
     *
     * @return void
     */
    protected function _sendResponse(int $code, string $message, mixed $data): void
    {
        http_response_code($code);
        $this->_printJSON(json_encode([
            'status_code' => $code,
            'status_txt' => $message,
            'data' => $data,
        ]));
    }

    /**
     * Helper function to output result as either JSON or JSONP
     *
     * @param string $json JSON object string
     * @param bool   $xml  Whether to wrap an XML response as JSONP
     * @param bool   $utf  Whether to return result as UTF-8
     *
     * @return void
     */
    protected function _printJSON($json, $xml=false, $utf=false)
    {
        // Wrap JSONP requests with callback
        if (isset($this->_params['callback'])) {
            // For XML responses, surround with quotes and remove newlines to
            // make a valid JavaScript string
            if ($xml) {
                $xmlStr = str_replace("\n", '', str_replace("'", "\'", $json));
                $json = sprintf("%s('%s')", $this->_params['callback'], $xmlStr);
            }
            else {
                $json = sprintf("%s(%s)", $this->_params['callback'], $json);
            }
        }

        // Set Content-type HTTP header
        if ($utf) {
            header('Content-type: application/json;charset=UTF-8');
        }
        else {
            header('Content-Type: application/json');
        }

        // Print result
        echo $json;
    }
}
