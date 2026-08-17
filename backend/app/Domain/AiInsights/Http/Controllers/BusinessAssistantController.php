<?php

namespace App\Domain\AiInsights\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\AiInsights\Http\Requests\AssistantAskRequest;
use App\Domain\AiInsights\Services\BusinessAssistantService;
use Illuminate\Http\JsonResponse;

class BusinessAssistantController extends Controller
{
    public function __invoke(AssistantAskRequest $request, BusinessAssistantService $assistant): JsonResponse
    {
        $business = $request->user()->business;

        abort_unless($business, 404);

        return response()->json(
            $assistant->ask($business, $request->validated('question')),
        );
    }
}
