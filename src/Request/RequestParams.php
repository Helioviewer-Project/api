<?php declare(strict_types=1);

namespace Helioviewer\Api\Request;
/**
 * Collect our request parameters and merge them 
 */
class RequestParams
{
    private array $params = [];

    public function __construct()
    {
        // Initialize with GET parameters
        $this->params = $_GET; 

        // Handle POST PUT PATCH requests
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {

            // Merge POST parameters
            $this->params = array_merge($this->params, $_POST);
            
            // Determine the content type of the request
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';

            // If the content type is JSON, handle JSON input
            if('application/json' === $content_type) {
                $this->params['json'] = $this->handleJsonInput();
            }

        }

    }

    /**
     * A static function to collect request params
     */ 
    public static function collect(): array 
    {
        $request_params = new RequestParams();
        return $request_params->getParams(); 
    }


    /**
     * Handle JSON input from the request body
     */ 
    protected function handleJsonInput() : mixed
    {
        $json = file_get_contents('php://input');
        $json_data = json_decode($json, true);

        // Check for JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RequestException(json_last_error_msg(), 400);
        }

        return $json_data;
    }

    /**
     * Get params from request params
     */ 
    public function getParams(): array 
    {
        return $this->params;
    }

}
