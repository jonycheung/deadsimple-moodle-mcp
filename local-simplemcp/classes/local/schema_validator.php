<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_simplemcp\local;

/**
 * Minimal JSON-schema-subset validator for MCP tool input. Deliberately
 * small: this plugin's tool schemas only use object/string/integer/boolean
 * properties with required/default/min/max/length constraints and
 * additionalProperties:false. Not a general-purpose JSON Schema
 * implementation.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema_validator {
    /**
     * Validates $arguments against $schema, applying declared defaults for
     * missing optional properties.
     *
     * @return array Validated arguments, with defaults applied.
     * @throws mcp_exception with INVALID_PARAMS on any violation.
     */
    public static function validate(array $schema, array $arguments): array {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $additionalallowed = $schema['additionalProperties'] ?? true;

        foreach (array_keys($arguments) as $key) {
            if (!$additionalallowed && !array_key_exists($key, $properties)) {
                throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
            }
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $arguments)) {
                throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
            }
        }

        $result = [];
        foreach ($properties as $key => $rule) {
            if (array_key_exists($key, $arguments)) {
                $result[$key] = self::validate_value($rule, $arguments[$key]);
            } else if (array_key_exists('default', $rule)) {
                $result[$key] = $rule['default'];
            }
        }

        return $result;
    }

    /**
     * Validates and coerces one argument against its schema rule.
     *
     * @param array $rule The property's schema fragment.
     * @param mixed $value The raw client-supplied value.
     * @return mixed The value, coerced to the declared type.
     * @throws mcp_exception INVALID_PARAMS if the value does not match the rule.
     */
    private static function validate_value(array $rule, $value) {
        $type = $rule['type'] ?? null;

        switch ($type) {
            case 'integer':
                if (!is_int($value)) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                if (isset($rule['minimum']) && $value < $rule['minimum']) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                if (isset($rule['maximum']) && $value > $rule['maximum']) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                $length = \core_text::strlen($value);
                if (isset($rule['minLength']) && $length < $rule['minLength']) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                if (isset($rule['maxLength']) && $length > $rule['maxLength']) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                break;

            case 'boolean':
                if (!is_bool($value)) {
                    throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
                }
                break;

            default:
                // Unknown/unsupported type in schema: reject rather than pass through silently.
                throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
        }

        return $value;
    }
}
