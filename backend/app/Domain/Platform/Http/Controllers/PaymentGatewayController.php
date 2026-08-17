<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentGatewayLog;
use App\Domain\Finance\Services\PaymentGatewayService;
use App\Domain\Platform\Http\Requests\PaymentGatewayRequest;
use App\Domain\Platform\Http\Resources\PaymentGatewayResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    public function index(Request $request): Response
    {
        $gateways = PaymentGateway::query()
            ->withCount('transactions')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/Finance/Gateways/Index', [
            'gateways' => PaymentGatewayResource::collection($gateways),
        ]);
    }

    public function store(PaymentGatewayRequest $request, PaymentGatewayService $service): RedirectResponse
    {
        $service->create($request->validated());

        return back()->with('status', 'gateway-created');
    }

    public function update(PaymentGatewayRequest $request, PaymentGateway $paymentGateway, PaymentGatewayService $service): RedirectResponse
    {
        $data = $request->validated();

        // Blank credential fields mean "leave unchanged" — merge into the
        // existing encrypted set rather than overwriting it, so clearing
        // the form never wipes secrets that were never re-entered.
        if (array_key_exists('credentials', $data)) {
            $data['credentials'] = array_filter($data['credentials'] ?? [], fn ($value) => $value !== '' && $value !== null);
            $data['credentials'] = $data['credentials'] === []
                ? $paymentGateway->credentials
                : array_merge($paymentGateway->credentials ?? [], $data['credentials']);
        }

        $service->update($paymentGateway, $data);

        return back()->with('status', 'gateway-updated');
    }

    public function enable(PaymentGateway $paymentGateway, PaymentGatewayService $service): RedirectResponse
    {
        $service->enable($paymentGateway);

        return back()->with('status', 'gateway-enabled');
    }

    public function disable(PaymentGateway $paymentGateway, PaymentGatewayService $service): RedirectResponse
    {
        $service->disable($paymentGateway);

        return back()->with('status', 'gateway-disabled');
    }

    public function checkHealth(PaymentGateway $paymentGateway, PaymentGatewayService $service): RedirectResponse
    {
        $service->checkHealth($paymentGateway);

        return back()->with('status', 'gateway-health-checked');
    }

    public function logs(PaymentGateway $paymentGateway): Response
    {
        $logs = $paymentGateway->logs()
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $logs->through(fn (PaymentGatewayLog $log) => [
            'id' => $log->id,
            'direction' => $log->direction,
            'event_type' => $log->event_type,
            'status_code' => $log->status_code,
            'is_successful' => $log->is_successful,
            'error_message' => $log->error_message,
            'request_payload' => $log->request_payload,
            'response_payload' => $log->response_payload,
            'created_at' => $log->created_at,
        ]);

        return Inertia::render('Platform/Finance/Gateways/Logs', [
            'gateway' => new PaymentGatewayResource($paymentGateway),
            'logs' => [
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'links' => $logs->linkCollection()->toArray(),
                ],
            ],
        ]);
    }
}
