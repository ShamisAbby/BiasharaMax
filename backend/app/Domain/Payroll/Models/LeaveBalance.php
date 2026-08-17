<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'employee_profile_id',
        'leave_type_id',
        'year',
        'allocated_days',
        'used_days',
        'pending_days',
        'carried_forward_days',
        'available_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'carried_forward_days' => 'decimal:2',
            'available_days' => 'decimal:2',
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
}
