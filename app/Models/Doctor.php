<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;

class Doctor extends Authenticatable
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'doctors';

    protected $primaryKey = 'doctor_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'doctor_id',
        'full_name',
        'email',
        'password',
        'google_id',
        'phone_number',
        'profile_image',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $doctor): void {
            if ($doctor->isDirty('doctor_id')) {
                throw new InvalidArgumentException('doctor_id is immutable and cannot be updated.');
            }
        });
    }

    /**
     * @return HasMany<ClinicalUpdate, $this>
     */
    public function clinicalUpdates(): HasMany
    {
        return $this->hasMany(ClinicalUpdate::class, 'doctor_id', 'doctor_id');
    }

    /**
     * Get the doctor's display name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->trashed()
                ? "{$this->full_name} (deleted)"
                : (string) $this->full_name,
        );
    }

    /**
     * Determine whether the doctor has administrator privileges.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Explicitly grant administrator privileges to the doctor.
     */
    public function makeAdmin(): self
    {
        $this->forceFill(['is_admin' => true])->save();

        return $this;
    }
}
