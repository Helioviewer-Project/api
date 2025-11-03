<?php declare(strict_types=1);
/**
 * Test cases for legacy event string validation
 *
 * Tests the checkLegacyEventString validator which validates strings in the format:
 * [AR,SPoCA,1],[CH,all,1],[SS,EGSO_SFC,1],[FP,AMOS;ASAP,1]
 */

use PHPUnit\Framework\TestCase;

include_once HV_ROOT_DIR.'/../src/Validation/InputValidator.php';

final class LegacyEventStringTest extends TestCase
{
    /**
     * Test data provider with valid and invalid legacy event strings
     * Format: [test_name, input_string, should_pass]
     */
    public static function legacyEventStringProvider(): array
    {
        return [
            // Valid cases
            'valid_single_simple' => [
                '[AR,SPoCA,1]',
                true,
                'Single event with simple source'
            ],
            'valid_multiple_simple' => [
                '[AR,SPoCA,1],[CH,all,1]',
                true,
                'Multiple events with simple sources'
            ],
            'valid_semicolon_source' => [
                '[FP,AMOS;ASAP,1]',
                true,
                'Single event with semicolon-separated sources'
            ],
            'valid_multiple_semicolon' => [
                '[FP,AMOS;ASAP,1],[SS,EGSO_SFC,0]',
                true,
                'Multiple events with mixed sources'
            ],
            'valid_visibility_zero' => [
                '[AR,SPoCA,0]',
                true,
                'Event with visibility 0'
            ],
            'valid_visibility_one' => [
                '[AR,SPoCA,1]',
                true,
                'Event with visibility 1'
            ],
            'valid_visibility_quoted_zero' => [
                '[AR,SPoCA,"0"]',
                true,
                'Event with quoted visibility "0"'
            ],
            'valid_visibility_quoted_one' => [
                '[AR,SPoCA,"1"]',
                true,
                'Event with quoted visibility "1"'
            ],
            'valid_complex' => [
                '[AR,SPoCA,1],[CH,all,1],[SS,EGSO_SFC,1],[FP,AMOS;ASAP;NOAA,1]',
                true,
                'Complex multi-event string'
            ],
            'valid_underscore_in_source' => [
                '[SS,EGSO_SFC,1]',
                true,
                'Source with underscore'
            ],
            'valid_multiple_semicolons' => [
                '[FP,AMOS;ASAP;NOAA;MAG4,1]',
                true,
                'Multiple semicolon-separated sources'
            ],
            'valid_empty_string' => [
                '',
                true,
                'Empty string (no events selected)'
            ],
            'valid_all_uppercase_letters' => [
                '[FL,SWPC,1]',
                true,
                'Different two-letter event type'
            ],
            'valid_three_letter_event_type' => [
                '[C3P,DONKI,1]',
                true,
                'Three-letter event type'
            ],
            'valid_event_type_with_digit' => [
                '[C3,DONKI,1]',
                true,
                'Event type with digit'
            ],
            'valid_event_type_all_digits' => [
                '[F2,RHESSI,1]',
                true,
                'Event type with all digits'
            ],
            'valid_source_with_spaces' => [
                '[FL,NOAA SWPC Observer,1]',
                true,
                'Source with spaces'
            ],
            'valid_source_with_multiple_spaces' => [
                '[AR,Space Weather Prediction Center,1]',
                true,
                'Source with multiple spaces'
            ],
            'valid_source_with_parentheses' => [
                '[AR,SPoCA(enhanced),1]',
                true,
                'Source with parentheses'
            ],
            'valid_source_with_plus' => [
                '[FL,NOAA+SWPC,1]',
                true,
                'Source with plus sign'
            ],
            'valid_source_with_minus' => [
                '[AR,Model-A,1]',
                true,
                'Source with minus/hyphen'
            ],
            'valid_source_with_backslash' => [
                '[AR,Path\\Test,1]',
                true,
                'Source with backslash'
            ],
            'valid_semicolon_sources_with_spaces' => [
                '[FP,NOAA SWPC;ASAP Model,1]',
                true,
                'Semicolon-separated sources with spaces'
            ],
            'valid_visibility_true' => [
                '[AR,SPoCA,true]',
                true,
                'Visibility as boolean true'
            ],
            'valid_visibility_false' => [
                '[AR,SPoCA,false]',
                true,
                'Visibility as boolean false'
            ],

            // Invalid cases - Wrong number of parts
            'invalid_too_few_parts' => [
                '[AR,SPoCA]',
                false,
                'Only 2 parts instead of 3'
            ],
            'invalid_too_many_parts' => [
                '[AR,SPoCA,1,extra]',
                false,
                'More than 3 parts'
            ],
            'invalid_single_part' => [
                '[AR]',
                false,
                'Only 1 part'
            ],

            // Invalid cases - Event type (Part 1)
            'invalid_event_type_lowercase' => [
                '[ar,SPoCA,1]',
                false,
                'Event type with lowercase letters'
            ],
            'invalid_event_type_mixed_case' => [
                '[Ar,SPoCA,1]',
                false,
                'Event type with mixed case'
            ],
            'invalid_event_type_too_short' => [
                '[A,SPoCA,1]',
                false,
                'Event type with only 1 character'
            ],
            'invalid_event_type_too_long' => [
                '[ARRR,SPoCA,1]',
                false,
                'Event type with 4 characters'
            ],
            'invalid_event_type_special_char' => [
                '[A@,SPoCA,1]',
                false,
                'Event type with special character'
            ],
            'invalid_event_type_with_space' => [
                '[A R,SPoCA,1]',
                false,
                'Event type with space'
            ],

            // Invalid cases - Source (Part 2)
            'invalid_empty_source' => [
                '[AR,,1]',
                false,
                'Empty source'
            ],
            'invalid_source_trailing_semicolon' => [
                '[FP,AMOS;,1]',
                false,
                'Source with trailing semicolon (creates empty part)'
            ],
            'invalid_source_leading_semicolon' => [
                '[FP,;AMOS,1]',
                false,
                'Source with leading semicolon (creates empty part)'
            ],
            'invalid_source_double_semicolon' => [
                '[FP,AMOS;;ASAP,1]',
                false,
                'Source with double semicolon (creates empty part)'
            ],
            'invalid_source_special_chars' => [
                '[AR,SPoC@,1]',
                false,
                'Source with @ special character'
            ],
            'invalid_source_hash' => [
                '[AR,Model#1,1]',
                false,
                'Source with hash character'
            ],
            'invalid_source_asterisk' => [
                '[AR,Test*,1]',
                false,
                'Source with asterisk'
            ],
            'invalid_source_equals' => [
                '[AR,Test=Value,1]',
                false,
                'Source with equals sign'
            ],

            // Invalid cases - Visibility (Part 3)
            'invalid_visibility_two' => [
                '[AR,SPoCA,2]',
                false,
                'Visibility value 2'
            ],
            'invalid_visibility_negative' => [
                '[AR,SPoCA,-1]',
                false,
                'Negative visibility value'
            ],
            'invalid_visibility_empty' => [
                '[AR,SPoCA,]',
                false,
                'Empty visibility'
            ],
            'invalid_visibility_quoted_two' => [
                '[AR,SPoCA,"2"]',
                false,
                'Quoted visibility "2"'
            ],
            'invalid_visibility_yes' => [
                '[AR,SPoCA,yes]',
                false,
                'Visibility as "yes"'
            ],
            'invalid_visibility_no' => [
                '[AR,SPoCA,no]',
                false,
                'Visibility as "no"'
            ],
            'invalid_visibility_True' => [
                '[AR,SPoCA,True]',
                false,
                'Visibility as "True" (capitalized)'
            ],
            'invalid_visibility_FALSE' => [
                '[AR,SPoCA,FALSE]',
                false,
                'Visibility as "FALSE" (uppercase)'
            ],

            // Invalid cases - Format issues
            'invalid_missing_opening_bracket' => [
                'AR,SPoCA,1]',
                false,
                'Missing opening bracket'
            ],
            'invalid_missing_closing_bracket' => [
                '[AR,SPoCA,1',
                false,
                'Missing closing bracket'
            ],
            'invalid_wrong_separator' => [
                '[AR,SPoCA,1];[CH,all,1]',
                false,
                'Using semicolon instead of comma between groups'
            ],
            'invalid_space_between_groups' => [
                '[AR,SPoCA,1], [CH,all,1]',
                false,
                'Space between groups'
            ],
            'invalid_nested_brackets' => [
                '[[AR,SPoCA,1]]',
                false,
                'Nested brackets'
            ],
            'invalid_only_brackets' => [
                '[]',
                false,
                'Empty brackets'
            ],
        ];
    }

    /**
     * @dataProvider legacyEventStringProvider
     */
    public function testLegacyEventStringValidation(string $input, bool $shouldPass, string $description): void
    {
        $params = ['eventTypes' => $input];
        $expected = ['legacy_event_string' => ['eventTypes']];
        $optional = [];

        if ($shouldPass) {
            // Should not throw exception
            try {
                Validation_InputValidator::checkInput($expected, $params, $optional);
                $this->assertTrue(true, $description);
            } catch (InvalidArgumentException $e) {
                $this->fail("Expected to pass but failed: $description. Input: '$input'. Error: " . $e->getMessage());
            }
        } else {
            // Should throw exception
            $this->expectException(InvalidArgumentException::class);
            Validation_InputValidator::checkInput($expected, $params, $optional);
        }
    }

    /**
     * Test that the validator allows empty strings (no events selected)
     */
    public function testEmptyStringIsValid(): void
    {
        $params = ['eventTypes' => ''];
        $expected = ['legacy_event_string' => ['eventTypes']];
        $optional = [];

        Validation_InputValidator::checkInput($expected, $params, $optional);
        $this->assertTrue(true);
    }

    /**
     * Test real-world examples from the requirements
     */
    public function testRealWorldExample(): void
    {
        $params = ['eventTypes' => '[AR,SPoCA,1],[CH,all,1],[SS,EGSO_SFC,1],[FP,AMOS;ASAP,1]'];
        $expected = ['legacy_event_string' => ['eventTypes']];
        $optional = [];

        Validation_InputValidator::checkInput($expected, $params, $optional);
        $this->assertTrue(true, 'Real-world example should pass validation');
    }

    /**
     * Test complex real-world example with new features
     */
    public function testComplexRealWorldExample(): void
    {
        $params = ['eventTypes' => '[C3,DONKI,true],[F2,RHESSI,false],[FL,NOAA SWPC Observer,1],[FP,Model-A;AMOS+ASAP,0]'];
        $expected = ['legacy_event_string' => ['eventTypes']];
        $optional = [];

        Validation_InputValidator::checkInput($expected, $params, $optional);
        $this->assertTrue(true, 'Complex real-world example with digits, booleans, and special chars should pass');
    }

    /**
     * Test that missing parameter is handled correctly
     */
    public function testMissingParameterIsAllowed(): void
    {
        $params = [];
        $expected = ['legacy_event_string' => ['eventTypes']];
        $optional = [];

        // Should not throw exception when parameter is not set
        Validation_InputValidator::checkInput($expected, $params, $optional);
        $this->assertTrue(true);
    }
}
