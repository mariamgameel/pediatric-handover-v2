<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClinicalUpdateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ClinicalUpdate extends Model
{
    /** @use HasFactory<ClinicalUpdateFactory> */
    use HasFactory;
    use HasUlids;

    protected $table = 'clinical_updates';

    protected $primaryKey = 'update_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'update_id',
        'patient_id',
        'doctor_id',
        'content',
        'corrects_update_id',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['update_id'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $update): void {
            throw new LogicException('Clinical updates are retained permanently and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ClinicalUpdate, $this>
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_update_id', 'update_id');
    }

    /**
     * @return HasMany<ClinicalUpdate, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_update_id', 'update_id');
    }
}
