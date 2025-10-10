<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

include_once HV_ROOT_DIR.'/../src/Actions.php';
include_once HV_ROOT_DIR.'/../src/Validation/InputValidator.php';

final class ValidateParametersTest extends TestCase
{
  /**
   * Test validation rules against all available actions.
   */
  public function test_ValidateInputRules(): void {
    // Go through each API endpoint that we have
    foreach(VALID_ACTIONS as $action => $module) {
      // Load the module containing the code for that endpoint
      include_once HV_ROOT_DIR."/../src/Module/$module.php";
      $ModuleClass  = 'Module_'.$module;
      // Create an instance of the module to retrieve its validation rules
      $params = ['action' => $action];
      $api = new $ModuleClass($params);
      // Get the validation rules for this API endpoint (called action in code.)
      $rules = $api->getValidationRules();
      // Validate that all required/optional parameters are validated
      $this->checkRules($rules, $action, $module);
    }
  }

  /**
   * Asserts that the given validation rules validate all required and
   * optional parameters. These validation rules are specified in each module's
   * getValidationRules function. See there for reference.
   */
  private function checkRules(array $rules, string $action, string $module): void {
    $requiredParameters = $rules['required'] ?? [];
    $optionalParameters = $rules['optional'] ?? [];
    // Get a list of all parameters accepted by these rules.
    $parameters = array_merge($requiredParameters, $optionalParameters);
    // Get a list of all parameters with a validation rule.
    $validated_parameters = $this->getValidatedParameters($rules);
    // Get a list of parameters which are not validated.
    $unchecked_parameters = array_diff($parameters, $validated_parameters);
    // Assert that there are no unchecked parameters.
    $this->assertEquals(0, count($unchecked_parameters), "$module::$action has unchecked parameters: " . implode(", ", $unchecked_parameters));
  }

  /**
   * Returns a list of parameters with validation rules assigned to them.
   */
  private function getValidatedParameters(array $rules): array {
    // Retain a list of parameter names that are checked.
    $checked_parameters = [];
    // Go through all of our validation rules
    foreach (Validation_InputValidator::AVAILABLE_RULES as $check => $_) {
      // Ignore required and optional checks as they are not real validators.
      // required and optional just check for the existence of parameters, not
      // their actual values.
      if ($check === "required" || $check === "optional") continue;
      // If the validation check is in the rule list...
      if (array_key_exists($check, $rules)) {
        // Add the checked parameters to our list

        // Some rules like "choices" require an associative array instead of
        // a plain list. If this is the case, then the parameter name is the
        // key. Otherwise the parameter name is the value.
        $isAssociative = array_keys($rules[$check]) !== range(0, count($rules[$check]) - 1);
        if ($isAssociative) {
          $checked_parameters = array_merge($checked_parameters, array_keys($rules[$check]));
        } else {
          $checked_parameters = array_merge($checked_parameters, $rules[$check]);
        }
      }
    }
    return $checked_parameters;
  }
}
