<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Validate optional primary-passenger details at both form and checkout. */
function passenger_detail_errors(array $passenger): array
{
    $errors = [];
    $age = $passenger['age'] ?? '';

    if (!is_string($age) || ($age !== '' && (!preg_match('/^(0|[1-9][0-9]{0,2})$/D', $age) || (int) $age > 120))) {
        $errors['age'] = 'Enter a whole-number age from 0 to 120, or leave it blank.';
    }
    $gender = $passenger['gender'] ?? '';

    if (!is_string($gender) || !in_array($gender, ['', 'Female', 'Male', 'Non-binary', 'Prefer not to say'], true)) {
        $errors['gender'] = 'Choose one of the listed options, or leave it blank.';
    }

    return $errors;
}
