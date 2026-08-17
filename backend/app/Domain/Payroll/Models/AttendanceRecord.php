<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_WEEKEND = 'weekend';

    public const DAY_REGULAR = 'regular';

    public const DAY_HOLIDAY = 'holiday';

    public const DAY_WEEKEND = 'weekend';

    public const METHOD_MANUAL = 'manual';

    public const METHOD_QR = 'qr';

    public const METHOD_PIN = 'pin';

    public const METHOD_BIOMETRIC = 'biometric';

    public const METHOD_GPS = 'gps';

    /**
     * The statuses a record may legitimately hold.
     *
     * Exposed so validation can whitelist against it — manual entry used to
     * accept `['required', 'string']`, which let any value at all be stored
     * in the column and then read back by the payroll calculation.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_ABSENT,
            self::STATUS_LATE,
            self::STATUS_HALF_DAY,
            self::STATUS_ON_LEAVE,
            self::STATUS_HOLIDAY,
            self::STATUS_WEEKEND,
        ];
    }

    protected $fillable = [
        'business_id',
        'employee_profile_id',
        'shift_id',
        'leave_request_id',
        'attendance_date',
        'day_type',
        'status',
        'clock_in_at',
        'clock_out_at',
        'break_start_at',
        'break_end_at',
        'regular_hours',
        'overtime_hours',
        'break_hours',
        'is_late',
        'late_minutes',
        'early_departure',
        'clock_in_method',
        'location_latitude',
        'location_longitude',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'break_start_at' => 'datetime',
            'break_end_at' => 'datetime',
            'approved_at' => 'datetime',
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'break_hours' => 'decimal:2',
            'is_late' => 'boolean',
            'early_departure' => 'boolean',
            'late_minutes' => 'integer',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function correction(): HasOne
    {
        return $this->hasOne(AttendanceCorrection::class);
    }

    public function isOpen(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }

    public function isClosed(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at !== null;
    }
}
