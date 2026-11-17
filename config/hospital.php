<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Hospital ID Constraints
    |--------------------------------------------------------------------------
    |
    | Maximum column length for external hospital-provided doctor and patient
    | identifiers. Stored centrally to easily adjust once formats are finalized.
    |
    */
    'doctor_id_max_length' => (int) env('HOSPITAL_DOCTOR_ID_MAX_LENGTH', 64),
    'patient_id_max_length' => (int) env('HOSPITAL_PATIENT_ID_MAX_LENGTH', 64),

    /*
    |--------------------------------------------------------------------------
    | Allowed Genders
    |--------------------------------------------------------------------------
    |
    | Whitelist of valid gender values accepted across forms, models, and imports.
    |
    */
    'genders' => [
        'male',
        'female',
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Administrator Credentials
    |--------------------------------------------------------------------------
    |
    | Used by AdminSeeder to bootstrap the initial hospital administrator.
    |
    */
    'admin_name' => env('ADMIN_NAME', 'Hospital Admin'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@hospital.local'),
    'admin_password' => env('ADMIN_PASSWORD', ''),
];
