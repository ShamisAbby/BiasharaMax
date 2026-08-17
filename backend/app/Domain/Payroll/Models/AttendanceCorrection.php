<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_id',
        'attendance_record_id',
        'employee_profile_id',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_clock_in' => 'datetime',
            'requested_clock_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
