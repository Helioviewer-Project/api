<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Helioviewer InputValidator Class Definition
 * Helioviewer InputValidator Class
 *
 * A class which helps to validate and type-case input
 *
 * @category Helper
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Validation_InputValidator
{
    const DATE_MESSAGE = "Please enter a date of the form 2003-10-06T00:00:00.000Z";

    /**
     * Validates and type-casts API Request parameters
     *
     * TODO 02/09/2009: Create more informative InvalidArgumentException classes:
     *  InvalidInputException, MissingRequiredParameterException, etc.
     *
     * @param array &$expected Types of checks required for request
     * @param array &$input    Actual request parameters
     * @param array &$optional Array to store optional parameters in
     *
     * @return void
     */
    public static function checkInput(&$expected, &$input, &$optional)
    {
        // Validation checks
        $checks = array(
            "required"   => "checkForMissingParams",
            "alphanum"   => "checkAlphaNumericStrings",
            "ints"       => "checkInts",
            "array_ints" => "checkOfArrayInts",
            "floats"     => "checkFloats",
            "bools"      => "checkBools",
            "dates"      => "checkDates",
            "encoded"    => "checkURLEncodedStrings",
            "urls"       => "checkURLs",
            "files"      => "checkFilePaths",
            "uuids"      => "checkUUIDs",
            "layer"      => "checkLayerValidity",
            "choices"    => "checkChoices"
        );

        // Run validation checks
        foreach ($checks as $name => $method) {
            if (isset($expected[$name])) {
                Validation_InputValidator::$method($expected[$name], $input);
            }
        }

        // Create array of optional parameters
        if (isset($expected["optional"])) {
            Validation_InputValidator::checkOptionalParams($expected["optional"], $input, $optional);
        }

        // Check for unknown parameters
        $allowed = array("action", "_", "XDEBUG_PROFILE");

        if(isset($expected["required"])) {
            $allowed = array_merge($allowed, array_values($expected["required"]));
        }
        if(isset($expected["optional"])) {
            $allowed = array_merge($allowed, array_values($expected["optional"]));
        }

        // Unset any unexpected request parameters
        foreach(array_keys($_REQUEST) as $param) {
            if (!in_array($param, $allowed)) {
                /*
                throw new InvalidArgumentException(
                    'Unrecognized parameter <b>'.$param.'</b>.', 27);
                */
                unset($_REQUEST[$param]);
                unset($_GET[$param]);
                unset($_POST[$param]);
            }
        }
    }

    /**
     * Checks to make sure all required parameters were passed in.
     *
     * @param array $required A list of the required parameters for a given action
     * @param array &$params  The parameters that were passed in
     *
     * @return void
     */
    public static function checkForMissingParams($required, &$params)
    {
        foreach ($required as $req) {
            if (!isset($params[$req])) {
                throw new InvalidArgumentException("No value set for required parameter \"$req\".", 28);
            }
        }
    }

    /**
     * Checks optional parameters and sets those which were not included to null
     *
     * Note that optional boolean parameters are set to "false" if they are not
     * found by the checkBools method.
     *
     * @param array $optional A list of the optional parameters for a given action
     * @param array &$params  The parameters that were passed in
     * @param array &$options Array to store any specified optional parameters in
     *
     * @return void
     */
    public static function checkOptionalParams($optional, &$params, &$options)
    {
        foreach ($optional as $opt) {
            if (isset($params[$opt])) {
                $options[$opt] = $params[$opt];
            }
        }
    }

    /**
     * Checks alphanumeric entries to make sure they do not include invalid characters
     *
     * @param array $strings A list of alphanumeric parameters which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkAlphaNumericStrings($strings, &$params)
    {
        foreach ($strings as $str) {
            if (isset($params[$str])) {
                if (!preg_match('/^[a-zA-Z0-9_]*$/', $params[$str])) {
                    throw new InvalidArgumentException(
                        "Invalid value for $str. Valid strings must consist of only letters, numbers, and underscores.", 25
                    );
                }
            }
        }
    }

    /**
     * Typecasts boolean strings or unset optional params to booleans
     *
     * @param array $bools   A list of boolean parameters which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkBools($bools, &$params)
    {
        foreach ($bools as $bool) {
            if (isset($params[$bool])) {
                if ((strtolower($params[$bool]) === "true") || $params[$bool] === "1") {
                    $params[$bool] = true;
                } elseif ((strtolower($params[$bool]) === "false") || $params[$bool] === "0") {
                    $params[$bool] = false;
                } else {
                    throw new InvalidArgumentException("Invalid value for $bool. Please specify a boolean value.", 25);
                }
            }
        }
    }

    /**
     * Checks filepaths to check for attempts to access parent directories
     *
     * @param array $files   Filepaths that should be validated
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkFilePaths($files, &$params)
    {
        foreach ($files as $file) {
            if (isset($params[$file])) {
                if (strpos($params[$file], '..')) {
                    throw new InvalidArgumentException("Invalid file requested: .. not allowed in filenames.", 25);
                } elseif (preg_match('/[^\/.-\w]/', $params[$file])) {
                    throw new InvalidArgumentException(
                        "Invalid file requested. Valid characters for filenames include letters, " .
                        "digits, underscores, hyphens and periods.", 25
                    );
                }
            }
        }
    }

    /**
     * Typecasts validates and fixes types for integer parameters
     *
     * @param array $ints    A list of integer parameters which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkInts($ints, &$params)
    {
        foreach ($ints as $int) {
            if (isset($params[$int])) {
                if (filter_var($params[$int], FILTER_VALIDATE_INT) === false) {
                    throw new InvalidArgumentException("Invalid value for $int. Please specify an integer value.", 25);
                } else {
                    $params[$int] = (int) $params[$int];
                }
            }
        }
    }


    /**
     * Typecasts validates and fixes types for array integer parameters
     *
     * @param array $ints    A list of integer array parameters which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkOfArrayInts($ints, &$params)
    {
        foreach ($ints as $int) {
            if (isset($params[$int])) {


                $integers_to_check = explode(',',$params[$int]);
                $validated_ints = [];

                foreach($integers_to_check as $itc) {
                    if (filter_var(trim($itc), FILTER_VALIDATE_INT) === false) {
                        throw new InvalidArgumentException("Invalid value for $int. Please specify an integer array value, as ex:1,2,3", 25);
                    }
                    $validated_ints[] = (int) trim($itc);
                }

                $params[$int] = $validated_ints;
            }
        }
    }

    /**
     * Typecasts validates and fixes types for float parameters
     *
     * @param array $floats  A list of float parameters which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkFloats($floats, &$params)
    {
        foreach ($floats as $float) {
            if (isset($params[$float])) {
                if (filter_var($params[$float], FILTER_VALIDATE_FLOAT) === false) {
                    throw new InvalidArgumentException("Invalid value for $float. Please specify an float value.", 25);
                } else {
                    $params[$float] = (float) $params[$float];
                }
            }
        }
    }

    /**
     * Validates UUIDs
     *
     * @param array $uuids   A list of uuids which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkUUIDs($uuids, &$params)
    {
        foreach ($uuids as $uuid) {
            if (isset($params[$uuid])) {
                if (!preg_match('/^[a-z0-9]{8}-?[a-z0-9]{4}-?[a-z0-9]{4}-?[a-z0-9]{4}-?[a-z0-9]{12}$/', $params[$uuid])) {
                    throw new InvalidArgumentException(
                        "Invalid identifier. Valid characters for UUIDs include " .
                        "lowercase letters, digits, and hyphens.", 25
                    );
                }
            }
        }
    }

    /**
     * Validates Layer Strings
     *
     * @param array list of fields that should be checked as layerstrings
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkLayerValidity($fields, &$params)
    {
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';
        foreach($fields as $field) {
            $layerString = $params[$field];
            // Attempt to parse the layer string
            try {
                $layerHelper = new Helper_HelioviewerLayers($layerString);
                Validation_InputValidator::checkLayers($layerHelper->toArray());
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new InvalidArgumentException("Couldn't parse layer string.");
            }
        }
    }


    /**
     * Typecasts validates URL parameters
     *
     * @param array $urls    A list of URLs which are used by an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkURLs($urls, &$params)
    {
        foreach ($urls as $url) {
            if (isset($params[$url])) {
                if (!filter_var($params[$url], FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException("Invalid value for $url. Please specify an URL.", 25);
                }
            }
        }
    }

    /**
     * Typecasts validates URL-encoded parameters
     *
     * @param array $urls    A list of URL-encoded strings which are used by
     *                       an action.
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkURLEncodedStrings($strings, &$params)
    {
        foreach ($strings as $str) {
            if (isset($params[$str])) {
                if (!preg_match('/[a-zA-Z0-9_\.\%\-]*/', $params[$str])) {
                    throw new InvalidArgumentException("Invalid URL-encoded string.", 25);
                }
            }
        }
    }

    /**
     * Checks an array of UTC dates
     *
     * @param array $dates   dates to check
     * @param array &$params The parameters that were passed in
     *
     * @return void
     */
    public static function checkDates($dates, &$params)
    {
        foreach ($dates as $date) {
            if (isset($params[$date])) {
                Validation_InputValidator::checkUTCDate($params[$date]);
            }
        }
    }

    /**
     * Checks to see if a string is a valid ISO 8601 UTC date string of the form
     * "2003-10-05T00:00:00.000Z" (milliseconds and ending "Z" are optional).
     *
     * @param string $date A datestring
     *
     * @return void
     */
    public static function checkUTCDate($date)
    {
        if (!preg_match("/^\d{4}[\/-]\d{2}[\/-]\d{2}T\d{2}:\d{2}:\d{2}\.?\d{0,6}?Z$/i", $date)) {
            throw new InvalidArgumentException("Invalid date string. " . self::DATE_MESSAGE, 25);
        }
    }

    /**
     * Checks that the values for the specified parameters have been picked from a set of choices
     * $choices is in the format {parameter_name => [possible, choices], other_parameter_name => [other, choices]}
     * The special value Null in possible choices means the parameter is also optional, i.e. doesn't need a value.
     *
     * @param array $choices Set of possible values for the given parameters
     * @param array &$args The values that were passed in.
     *
     * @return void
     */
    public static function checkChoices($choices, &$args) {
        // Get the names of the parameters that have a specific set of possible values
        foreach (array_keys($choices) as $parameter) {
            // If the value doesn't exist in $args, then skip it. It may not exist because the parameter is optional so it's allowed to be blank.
            // If the parameter should not be blank, then the "required" validator will catch it.
            if (!array_key_exists($parameter, $args)) {
                continue;
            }
            // Options are the specific set of values that the argument must be
            $options = $choices[$parameter];
            // The value passed in by the user
            $value = $args[$parameter];
            if (!in_array($value, $options)) {
                throw new InvalidArgumentException("Invalid argument provided for $parameter, must be one of " . implode(', ', $options));
            }
        }
    }

    /**
     * Checks that the given list of layers are valid
     * @param array $layers Layers parsed by Helper_HelioviewerLayers
     */
    public static function checkLayers(array $layers) {
        foreach ($layers as $layer) {
            if (array_key_exists("baseDiffTime", $layer)) {
                try {
                    Validation_InputValidator::checkUTCDate($layer["baseDiffTime"]);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("Invalid baseDiffTime in layer string: " . $layer["baseDiffTime"] . "<br/>" . self::DATE_MESSAGE);
                }
            }
        }
    }
}
?>
