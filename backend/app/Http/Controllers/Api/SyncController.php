<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Exceptions\CreditSaleException;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pull/push sync surface for the Flutter Desktop client. Proven out here
 * for two resources — products (+ their per-warehouse inventory) to pull,
 * sales to push — the same shape extends to other modules later without
 * a new mechanism, just new resource handlers.
 *
 * Every query here goes through the ordinary Eloquent models, so the
 * `BelongsToTenant` scope (now guard-aware for `sanctum`, see
 * app/Domain/Shared/Concerns/BelongsToTenant.php) does the tenant
 * isolation — this controller never needs to filter by business_id itself
 * for reads. Writes still pass business_id explicitly to SaleService,
 * taken from the authenticated user, never from client input.
 */
class SyncController extends Controller
{
    /**
     * Rows per pull. Small enough that a first sync of a large catalog
     * doesn't time out on a slow connection, large enough that a shop
     * with a few thousand products isn't paging all day.
     */
    private const PAGE_SIZE = 500;

    public function __construct(private readonly SaleService $sales) {}

    /**
     * GET /api/v1/sync/products?since=<ISO8601>
     *
     * Returns every product (with its per-warehouse inventory rows)
     * created, updated, or soft-deleted after `since`. Soft-deleted rows
     * are included (with `deleted_at` set) so the client can remove them
     * from its local cache instead of only ever accumulating rows.
     * Omitting `since` returns the full catalog — the client's first sync.
     */
    public function pullProducts(Request $request): JsonResponse
    {
        // Taken before the query runs, not after it returns.
        //
        // The watermark has to be a time the client can safely claim to
        // have seen everything up to. Anything sampled *after* the query
        // would silently include the window the query was executing in —
        // a product edited while a 500-row page was being serialised gets
        // an `updated_at` inside that window, is not in the response, and
        // is then excluded by the very watermark the response hands back.
        $startedAt = Carbon::now();

        $since = $this->parseSince($request);
        $sinceId = $request->query('since_id');

        $query = Product::query()->withTrashed()->with(['inventories' => function ($q) {
            $q->select(['id', 'product_id', 'warehouse_id', 'quantity', 'average_cost', 'updated_at']);
        }]);

        if ($since !== null) {
            // Keyset pagination on (updated_at, id), not a bare
            // `updated_at > since`.
            //
            // A plain timestamp cursor loses rows at every page boundary:
            // order by updated_at, take 500, then ask for `> the 500th
            // row's timestamp` — and any 501st row sharing that exact
            // timestamp is skipped, permanently. That is not a rare edge.
            // A bulk product import writes hundreds of rows within the
            // same second, so it is close to guaranteed on a first sync of
            // a large catalog.
            //
            // Adding the id as a tiebreaker makes the cursor unique, so
            // "everything after this exact row" is unambiguous no matter
            // how many rows share a timestamp.
            $query->where(function ($outer) use ($since, $sinceId) {
                $outer->where(function ($q) use ($since, $sinceId) {
                    $q->where('updated_at', '>', $since)
                        ->orWhere(function ($tie) use ($since, $sinceId) {
                            $tie->where('updated_at', '=', $since);

                            if ($sinceId !== null) {
                                $tie->where('id', '>', $sinceId);
                            } else {
                                // No cursor id means the client is resuming
                                // from a timestamp alone; exclude the equal
                                // bucket rather than re-sending it.
                                $tie->whereRaw('1 = 0');
                            }
                        });
                })->orWhere('deleted_at', '>', $since);
            });
        }

        $products = $query
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(self::PAGE_SIZE)
            ->get([
                'id', 'category_id', 'brand_id', 'unit_id', 'name', 'sku', 'barcode',
                'product_type', 'track_stock', 'cost_price', 'selling_price',
                'wholesale_price', 'tax_rate', 'status', 'updated_at', 'deleted_at',
            ]);

        $hasMore = $products->count() === self::PAGE_SIZE;
        $last = $products->last();

        return response()->json([
            'data' => $products,
            // Deliberately a second *behind* when this request started.
            //
            // `updated_at` columns are second-granular and `since` is
            // applied with a strict `>`, so a row written in the same
            // second as the watermark falls in neither bucket: not in this
            // response, and excluded from the next one. It is lost for
            // good, with nothing logged.
            //
            // Rewinding a second means the next pull re-sends the boundary
            // second. The client upserts (`insertOnConflictUpdate`), so a
            // duplicate row costs one redundant write and nothing else.
            // That is the right side of this trade to err on: duplicates
            // are free, losses are permanent and silent.
            'server_time' => $startedAt->copy()->startOfSecond()->subSecond()->toIso8601String(),
            'has_more' => $hasMore,
            // The cursor to resume from. Only meaningful while paging —
            // once `has_more` is false the client should store
            // `server_time` instead, which also covers rows deleted since.
            'next_since' => $hasMore ? $last?->updated_at?->toIso8601String() : null,
            'next_since_id' => $hasMore ? $last?->getKey() : null,
        ]);
    }

    /**
     * POST /api/v1/sync/sales
     *
     * Body: { "sales": [ { idempotency_key, branch_id, warehouse_id,
     * customer_id?, items: [...], payments: [...], ... }, ... ] }
     *
     * Each queued offline sale is replayed through the exact same
     * SaleService::create() the web app's POS controller calls — no
     * parallel "offline sale" business logic. One bad item in the batch
     * doesn't fail the rest; each result is reported back keyed by the
     * client's own idempotency_key so the desktop app knows which outbox
     * entries are safe to drop and which to keep retrying.
     */
    public function pushSales(Request $request): JsonResponse
    {
        $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:100'],
            'sales.*.idempotency_key' => ['required', 'string'],
            'sales.*.branch_id' => ['required', 'string'],
            'sales.*.warehouse_id' => ['required', 'string'],
            'sales.*.items' => ['required', 'array', 'min:1'],
        ]);

        $user = $request->user();
        $results = [];

        foreach ($request->input('sales') as $item) {
            $key = $item['idempotency_key'];

            $validator = Validator::make($item, [
                'items.*.product_id' => ['required', 'string'],
                'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
                'payments.*.amount' => ['nullable', 'numeric'],
            ]);

            if ($validator->fails()) {
                $results[$key] = ['status' => 'error', 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                $sale = $this->sales->create([
                    ...$item,
                    'business_id' => $user->business_id,
                    'sold_by' => $user->id,
                    'source' => Sale::SOURCE_DESKTOP,
                ]);

                $results[$key] = [
                    'status' => 'ok',
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                ];
            } catch (CreditSaleException $e) {
                // Business-rule rejection (credit limit, etc.) — not worth
                // retrying, the client should surface this to the cashier.
                $results[$key] = ['status' => 'rejected', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $results[$key] = ['status' => 'error', 'message' => 'Server error — will retry.'];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function parseSince(Request $request): ?Carbon
    {
        $since = $request->query('since');

        if (! $since) {
            return null;
        }

        try {
            return Carbon::parse($since);
        } catch (Throwable) {
            throw ValidationException::withMessages(['since' => 'Invalid date format, expected ISO 8601.']);
        }
    }
}
