<?php

namespace ArtPulse\Core;

/**
 * Compute progress for profile builders based on required fields.
 */
final class ProfileProgress
{
    /**
     * Compute overall completion percent and per-step states.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string>   $required_fields
     * @param array<int, array<string, mixed>> $steps
     *
     * @return array<string, mixed>
     */
    public static function compute(array $payload, array $required_fields, array $steps): array
    {
        $completed_required = 0;
        $total_required     = count($required_fields);

        foreach ($required_fields as $field) {
            if (self::is_field_complete($payload, $field)) {
                $completed_required++;
            }
        }

        $percent = $total_required > 0
            ? (int) round(($completed_required / $total_required) * 100)
            : 0;

        $step_states = [];

        foreach ($steps as $step) {
            $fields = isset($step['fields']) && is_array($step['fields']) ? $step['fields'] : [];
            $complete = true;

            foreach ($fields as $field) {
                if (!self::is_field_complete($payload, (string) $field)) {
                    $complete = false;
                    break;
                }
            }

            $step_states[] = [
                'slug'     => (string) ($step['slug'] ?? ''),
                'label'    => (string) ($step['label'] ?? ''),
                'complete' => $complete,
            ];
        }

        return [
            'percent' => max(0, min(100, $percent)),
            'steps'   => $step_states,
        ];
    }

    /**
     * Determine if a field is considered complete.
     *
     * @param array<string, mixed> $payload
     */
    private static function is_field_complete(array $payload, string $field): bool
    {
        if (!array_key_exists($field, $payload)) {
            return false;
        }

        $value = $payload[$field];

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value > 0;
        }

        if (is_bool($value)) {
            return $value;
        }

        return !empty($value);
    }
}
