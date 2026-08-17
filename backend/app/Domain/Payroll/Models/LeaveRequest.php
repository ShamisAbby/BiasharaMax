<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'employee_profile_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days_requested',
        'is_half_day',
        'half_day_period',
        'status',
        'reason',
        'attachment_path',
        'approved_by',
        'approved_at',
        'approval_notes',
        'payroll_adjusted',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
            'days_requested' => 'decimal:2',
            'is_half_day' => 'boolean',
            'payroll_adjusted' => 'boolean',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
