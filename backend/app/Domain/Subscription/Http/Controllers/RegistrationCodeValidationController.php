<?php

namespace App\Domain\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Subscription\Models\RegistrationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationCodeValidationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $code = RegistrationCode::query()
            ->with('plan:id,name,description,features')
            ->where('code', strtoupper($request->string('code')->trim()->value()))
            ->first();

        if ($code === null || ! $code->isUsable()) {
            return response()->json([
                'valid'  => false,
                'reason' => $code === null ? 'Code not found.' : 'This code has already been used or has expired.',
            ], 422);
        }

        return response()->json([
            'valid'           => true,
            'code'            => $code->code,
            'plan'            => $code->plan,
            'billing_cycle'   => $code->billing_cycle,
            'duration_months' => $code->duration_months,
        ]);
    }
}
