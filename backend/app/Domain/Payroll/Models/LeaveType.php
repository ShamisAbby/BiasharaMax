<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToTenant, HasUuids;

    public const CODE_ANNUAL = 'ANNUAL';

    public const CODE_SICK = 'SICK';

    public const CODE_EMERGENCY = 'EMERGENCY';

    public const CODE_MATERNITY = 'MATERNITY';

    public const CODE_PATERNITY = 'PATERNITY';

    public const CODE_COMPASSIONATE = 'COMPASSIONATE';

    public const CODE_STUDY = 'STUDY';

    public const CODE_UNPAID = 'UNPAID';

    /** @var array<int, array{code: string, name: string, days: int, paid: bool, color: string}> */
    public const DEFAULT_TYPES = [
        ['code' => self::CODE_ANNUAL,       'name' => 'Annual Leave',        'days' => 21, 'paid' => true,  'color' => '#4F46E5'],
        ['code' => self::CODE_SICK,         'name' => 'Sick Leave',          'days' => 14, 'paid' => true,  'color' => '#EF4444', 'requires_attachment' => true],
        ['code' => self::CODE_EMERGENCY,    'name' => 'Emergency Leave',     'days' => 3,  'paid' => true,  'color' => '#F97316'],
        ['code' => self::CODE_MATERNITY,    'name' => 'Maternity Leave',     'days' => 90, 'paid' => true,  'color' => '#EC4899', 'gender_restricted' => true, 'gender_restriction' => 'female'],
        ['code' => self::CODE_PATERNITY,    'name' => 'Paternity Leave',     'days' => 7,  'paid' => true,  'color' => '#3B82F6', 'gender_restricted' => true, 'gender_restriction' => 'male'],
        ['code' => self::CODE_COMPASSIONATE,'name' => 'Compassionate Leave', 'days' => 5,  'paid' => true,  'color' => '#8B5CF6'],
        ['code' => self::CODE_STUDY,        'name' => 'Study Leave',         'days' => 10, 'paid' => false, 'color' => '#06B6D4'],
        ['code' => self::CODE_UNPAID,       'name' => 'Unpaid Leave',        'days' => 30, 'paid' => false, 'color' => '#6B7280'],
    ];

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'color',
        'days_per_year',
        'is_paid',
        'requires_approval',
        'requires_attachment',
        'can_carry_forward',
        'max_carry_forward_days',
        'min_notice_days',
        'gender_restricted',
        'gender_restriction',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'can_carry_forward' => 'boolean',
            'gender_restricted' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'days_per_year' => 'integer',
            'max_carry_forward_days' => 'integer',
            'min_notice_days' => 'integer',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
