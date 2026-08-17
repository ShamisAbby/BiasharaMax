<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\DepreciationSchedule;
use App\Domain\Finance\Models\FixedAsset;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\FixedAssetService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class FixedAssetTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function createAsset(string $businessId, string $userId, array $overrides = []): FixedAsset
    {
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $assetAccount = Account::query()->where('business_id', $businessId)->where('code', '1010')->firstOrFail();

        return app(FixedAssetService::class)->create($businessId, array_merge([
            'asset_code' => 'VEH-001',
            'asset_name' => 'Company Vehicle',
            'category' => FixedAsset::CATEGORY_VEHICLE,
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => '120000.00',
            'account_id' => $assetAccount->id,
            'useful_life_months' => 60,
            'residual_value' => '20000.00',
            'depreciation_method' => FixedAsset::METHOD_STRAIGHT_LINE,
            'created_by' => $userId,
        ], $overrides));
    }

    public function test_creating_an_asset_generates_a_depreciation_schedule(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $asset = $this->createAsset($business->id, $owner->id);

        $this->assertEquals(FixedAsset::STATUS_ACTIVE, $asset->status);

        $scheduleCount = $asset->depreciationSchedule()->count();
        $this->assertEquals(60, $scheduleCount);

        // Straight-line: (120000 - 20000) / 60 = 1666.67 per month
        $firstRow = $asset->depreciationSchedule()->orderBy('period_date')->first();
        $this->assertNotNull($firstRow);
        $this->assertEquals(DepreciationSchedule::STATUS_PENDING, $firstRow->status);
    }

    public function test_posting_monthly_depreciation_marks_schedule_posted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $asset = $this->createAsset($business->id, $owner->id);

        $posted = app(FixedAssetService::class)->postMonthlyDepreciation($business->id, '2026-02', $owner->id);

        $this->assertEquals(1, $posted);

        $schedule = $asset->depreciationSchedule()->where('period_date', '2026-02-01')->first();
        $this->assertNotNull($schedule);
        $this->assertEquals(DepreciationSchedule::STATUS_POSTED, $schedule->status);
        $this->assertNotNull($schedule->journal_entry_id);
    }

    public function test_book_value_decreases_after_posting_depreciation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $asset = $this->createAsset($business->id, $owner->id);

        $this->assertEquals('120000.00', $asset->currentBookValue());

        app(FixedAssetService::class)->postMonthlyDepreciation($business->id, '2026-02', $owner->id);

        $firstRow = $asset->depreciationSchedule()->where('period_date', '2026-02-01')->first();
        $expectedBookValue = bcsub('120000.00', (string) $firstRow->depreciation_amount, 2);

        $this->assertEquals($expectedBookValue, $asset->fresh()->currentBookValue());
    }

    public function test_disposal_posts_journal_entry_and_marks_asset_disposed(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $asset = $this->createAsset($business->id, $owner->id);

        $cashAccount = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();

        $je = app(FixedAssetService::class)->dispose(
            $asset,
            '2026-06-30',
            '110000.00',
            $cashAccount->id,
            $owner->id,
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(FixedAsset::STATUS_DISPOSED, $asset->fresh()->status);
        $this->assertEquals('110000.00', $asset->fresh()->disposal_proceeds);

        // Pending schedule rows should be deleted
        $pendingCount = $asset->depreciationSchedule()->where('status', DepreciationSchedule::STATUS_PENDING)->count();
        $this->assertEquals(0, $pendingCount);
    }

    public function test_asset_with_no_depreciation_method_generates_no_schedule(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $asset = $this->createAsset($business->id, $owner->id, [
            'asset_code' => 'LAND-001',
            'asset_name' => 'Land Plot',
            'depreciation_method' => FixedAsset::METHOD_NONE,
        ]);

        $this->assertEquals(0, $asset->depreciationSchedule()->count());
    }
}
