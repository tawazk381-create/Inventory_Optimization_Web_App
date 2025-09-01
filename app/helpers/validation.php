<?php // File: app/helpers/validation.php
declare(strict_types=1);

/**
 * Simple validation helpers used across forms.
 */

function validate_required($value): bool
{
    return isset($value) && trim((string)$value) !== '';
}

function validate_email($email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_integer($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

function validate_decimal($value): bool
{
    return is_numeric($value);
}
