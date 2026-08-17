<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceShift extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'break_minutes',
        'expected_hours',
        'is_overnight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'is_active' => 'boolean',
            'grace_minutes' => 'integer',
            'break_minutes' => 'integer',
            'expected_hours' => 'integer',
        ];
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'shift_id');
    }
}
