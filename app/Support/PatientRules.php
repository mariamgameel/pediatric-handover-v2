<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

class PatientRules
{
    /**
     * Common rules for patient demographic attributes.
     *
     * @return array<string, list<mixed>>
     */
    public static function baseRules(): array
    {
        /** @var list<string> $allowedGenders */
        $allowedGenders = config('hospital.genders', ['male', 'female']);

        $hospitalTimezone = (string) config('hospital.timezone', 'Africa/Cairo');
        $todayHospital = now($hospitalTimezone)->toDateString();

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . $todayHospital,
                'after_or_equal:1900-01-01',
            ],
            'gender' => [
                'required',
                'string',
                Rule::in($allowedGenders),
            ],
        ];
    }

    /**
     * Rules applied when creating a new patient record.
     *
     * @return array<string, list<mixed>>
     */
    public static function rulesForCreate(): array
    {
        $patientIdMaxLength = (int) config('hospital.patient_id_max_length', 64);
        $patientIdPattern = (string) config('hospital.patient_id_pattern', '/^[A-Za-z0-9\-_]+$/');

        return array_merge([
            'patient_id' => [
                'required',
                'string',
                'max:' . $patientIdMaxLength,
                'regex:' . $patientIdPattern,
                'unique:patients,patient_id',
            ],
        ], self::baseRules());
    }

    /**
     * Rules applied when updating an existing patient record.
     *
     * @return array<string, list<mixed>>
     */
    public static function rulesForUpdate(): array
    {
        return array_merge([
            'patient_id' => ['prohibited'],
        ], self::baseRules());
    }

    /**
     * Rules applied during bulk CSV patient import (upsert semantics).
     *
     * @return array<string, list<mixed>>
     */
    public static function rulesForImport(): array
    {
        $patientIdMaxLength = (int) config('hospital.patient_id_max_length', 64);
        $patientIdPattern = (string) config('hospital.patient_id_pattern', '/^[A-Za-z0-9\-_]+$/');

        return array_merge([
            'patient_id' => [
                'required',
                'string',
                'max:' . $patientIdMaxLength,
                'regex:' . $patientIdPattern,
            ],
        ], self::baseRules());
    }
}
