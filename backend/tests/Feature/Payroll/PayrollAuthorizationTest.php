<?php

namespace Tests\Feature\Payroll;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Payroll\Models\AttendanceCorrection;
use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Models\LeaveType;
use App\Domain\Payroll\Services\LeaveService;
use App\Domain\Payroll\Services\PayrollService;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Covers the authorization the Payroll module was missing entirely.
 *
 * Leave and attendance were the only write endpoints in the app with no
 * permission check of any kind — `LeaveService::approveRequest()` verified
 * only that the request was still pending, never who was calling. Anyone
 * who could reach the route could approve their own leave and their own
 * attendance corrections, which made the whole request/approve split
 * decorative.
 *
 * These are HTTP tests on purpose. The previous Payroll suite exercised
 * the services directly, which is exactly why the gap went unnoticed:
 * the services still do the right thing, it was the controllers in front
 * of them that let anyone through.
 */
class PayrollAuthorizationTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_an_employee_cannot_approve_their_own_leave_request(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $request = $this->pendingLeaveRequestFor($business, $employee);

        $this->actingAs($employee)
            ->post(route('payroll.leave.approve', $request->id), ['notes' => 'Approving myself'])
            ->assertForbidden();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_even_the_owner_cannot_approve_their_own_leave_request(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        // The owner holds every permission there is, which is exactly what
        // makes this the sharpest version of the rule: holding the approval
        // permission is not enough, because approving your own request
        // defeats the control the approval step exists to provide.
        $this->assertTrue($owner->hasPermission('leave.approve'));

        $request = $this->pendingLeaveRequestFor($business, $owner);

        $this->actingAs($owner)
            ->post(route('payroll.leave.approve', $request->id))
            ->assertForbidden();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_the_owner_can_approve_someone_elses_leave_request(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $request = $this->pendingLeaveRequestFor($business, $employee);

        $this->actingAs($owner)
            ->post(route('payroll.leave.approve', $request->id), ['notes' => 'Approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_an_employee_cannot_delete_someone_elses_leave_request(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $author = $this->employeeWithRole($business, Role::CASHIER);
        $other = $this->employeeWithRole($business, Role::CASHIER);
        $request = $this->pendingLeaveRequestFor($business, $author);

        $this->actingAs($other)
            ->delete(route('payroll.leave.destroy', $request->id))
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $request->id]);
    }

    public function test_an_employee_can_cancel_their_own_pending_leave_request(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $request = $this->pendingLeaveRequestFor($business, $employee);

        $this->actingAs($employee)
            ->delete(route('payroll.leave.destroy', $request->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    public function test_an_employee_cannot_change_leave_types(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $type = $this->leaveTypeFor($business);

        $this->actingAs($employee)
            ->patch(route('payroll.leave-types.update', $type->id), [
                'name' => 'Unlimited Holiday',
                'color' => '#ffffff',
                'days_per_year' => 365,
            ])
            ->assertForbidden();

        $this->assertNotSame('Unlimited Holiday', $type->fresh()->name);
    }

    public function test_an_employee_cannot_write_an_attendance_record_by_hand(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $profile = $this->profileFor($business, $employee);

        // Manual entry bypasses the clock and feeds payroll, so it is
        // management-only even for your own record.
        $this->actingAs($employee)
            ->post(route('payroll.attendance.manual'), [
                'employee_profile_id' => $profile->id,
                'attendance_date' => now()->toDateString(),
                'status' => AttendanceRecord::STATUS_PRESENT,
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_manual_attendance_rejects_a_profile_from_another_business(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$otherOwner, $otherBusiness] = $this->createOwnerWithBusiness();

        $foreignProfile = $this->profileFor($otherBusiness, $otherOwner);

        // The model's tenant scope does not protect this: the id arrives in
        // the request body, and the `exists` rule bypasses global scopes —
        // so the business_id condition has to be written out explicitly.
        $this->actingAs($owner)
            ->post(route('payroll.attendance.manual'), [
                'employee_profile_id' => $foreignProfile->id,
                'attendance_date' => now()->toDateString(),
                'status' => AttendanceRecord::STATUS_PRESENT,
            ])
            ->assertSessionHasErrors('employee_profile_id');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_manual_attendance_rejects_an_unknown_status(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $profile = $this->profileFor($business, $owner);

        $this->actingAs($owner)
            ->post(route('payroll.attendance.manual'), [
                'employee_profile_id' => $profile->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'anything-at-all',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_an_employee_cannot_approve_their_own_attendance_correction(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);
        $profile = $this->profileFor($business, $employee);

        $record = AttendanceRecord::query()->create([
            'business_id' => $business->id,
            'employee_profile_id' => $profile->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceRecord::STATUS_PRESENT,
            'clock_in_at' => now()->setTime(9, 30),
        ]);

        $correction = AttendanceCorrection::query()->create([
            'business_id' => $business->id,
            'attendance_record_id' => $record->id,
            'employee_profile_id' => $profile->id,
            'requested_clock_in' => now()->setTime(9, 0),
            'reason' => 'Traffic, clocked in late by mistake.',
            'status' => 'pending',
        ]);

        // Approving your own correction is the same as paying yourself for
        // hours you did not work.
        $this->actingAs($employee)
            ->post(route('payroll.attendance.corrections.approve', $correction->id))
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }

    public function test_the_hr_dashboard_is_not_open_to_every_employee(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);

        $this->actingAs($employee)->get(route('payroll.dashboard'))->assertForbidden();
        $this->actingAs($owner)->get(route('payroll.dashboard'))->assertOk();
    }

    public function test_the_attendance_page_stays_open_so_employees_can_clock_in(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $employee = $this->employeeWithRole($business, Role::CASHIER);

        // Deliberately NOT gated — this is the time clock. What is scoped
        // is the roster: an employee sees only their own record.
        $this->actingAs($employee)->get(route('payroll.attendance.index'))->assertOk();
    }

    // ---------------------------------------------------------------

    private function employeeWithRole(Business $business, string $roleSlug): User
    {
        $role = Role::query()
            ->where('business_id', $business->getKey())
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $employee = User::query()->create([
            'business_id' => $business->getKey(),
            'role_id' => $role->getKey(),
            'name' => 'Staff '.fake()->unique()->numerify('###'),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('Password123!'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee->roles()->sync([$role->getKey()]);
        $employee->forceFill(['email_verified_at' => now()])->save();

        return $employee->fresh();
    }

    private function profileFor(Business $business, User $user): EmployeeProfile
    {
        return app(PayrollService::class)->createEmployeeProfile($business->getKey(), $user->getKey(), [
            'employee_number' => 'EMP-'.substr($user->getKey(), 0, 6),
            'employment_date' => '2025-01-01',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'base_salary' => '50000.00',
            'salary_cycle' => 'monthly',
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'created_by' => $user->getKey(),
        ]);
    }

    private function leaveTypeFor(Business $business): LeaveType
    {
        app(LeaveService::class)->seedDefaultLeaveTypes($business->getKey());

        return LeaveType::query()->where('business_id', $business->getKey())->firstOrFail();
    }

    private function pendingLeaveRequestFor(Business $business, User $user): LeaveRequest
    {
        $profile = $this->profileFor($business, $user);
        $type = $this->leaveTypeFor($business);

        return app(LeaveService::class)->submitRequest($profile, [
            'leave_type_id' => $type->getKey(),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'reason' => 'Family commitment that needs two days.',
        ]);
    }
}
