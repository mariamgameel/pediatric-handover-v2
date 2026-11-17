<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $table = 'patients';

    protected $primaryKey = 'patient_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'full_name',
        'date_of_birth',
        'gender',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $patient): void {
            if ($patient->isDirty('patient_id')) {
                throw new InvalidArgumentException('patient_id is immutable and cannot be updated.');
            }
        });
    }

    /**
     * @return HasMany<ClinicalUpdate, $this>
     */
    public function clinicalUpdates(): HasMany
    {
        return $this->hasMany(ClinicalUpdate::class, 'patient_id', 'patient_id');
    }
}
