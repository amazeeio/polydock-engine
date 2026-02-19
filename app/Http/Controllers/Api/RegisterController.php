<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\PolydockAppInstance;
use App\Models\UserRemoteRegistration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function processRegister(Request $request): JsonResponse
    {
        Log::info('Processing register request', ['request' => $request->all()]);
        try {
            $registration = UserRemoteRegistration::create([
                'email' => $request->input('email'),
                'request_data' => $request->all(),
                'status' => UserRemoteRegistrationStatusEnum::PENDING,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating user remote registration', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => UserRemoteRegistrationStatusEnum::FAILED->value,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Log::info('User remote registration created', ['registration' => $registration->toArray()]);

        return response()->json([
            'status' => UserRemoteRegistrationStatusEnum::PENDING->value,
            'message' => 'Registration pending',
            'id' => $registration->uuid,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Display the specified resource.
     */
    public function showRegister(string $uuid): JsonResponse
    {
        try {
            $registration = UserRemoteRegistration::where('uuid', $uuid)->firstOrFail();
            Log::info('Showing user remote registration', ['registration' => $registration->toArray()]);
            $responseResultData = $registration->result_data ?? [];

            if ($registration->appInstance) {
                $appInstance = $registration->appInstance;
                if (
                    in_array($appInstance->status, PolydockAppInstance::$failedStatuses)
                    && $registration->status != UserRemoteRegistrationStatusEnum::FAILED
                ) {
                    $registration->status = UserRemoteRegistrationStatusEnum::FAILED;
                    $registration->setResultValue('message', 'Failed to process registration.');
                    $registration->setResultValue('message_detail', 'An unexpected error occurred.');
                    $registration->setResultValue('result_type', 'registration_failed');
                    $registration->save();
                }

                if ($appInstance->getKeyValue('lagoon-project-id')) {
                    $responseResultData['lagoon-project-id'] = $appInstance->getKeyValue('lagoon-project-id');
                }

                if ($appInstance->getKeyValue('lagoon-deploy-branch')) {
                    $responseResultData['lagoon-deploy-branch'] = $appInstance->getKeyValue('lagoon-deploy-branch');
                }
            }

            return response()->json([
                'status' => $registration->status->value,
                'email' => $registration->email,
                'result_data' => $responseResultData,
                'created_at' => $registration->created_at,
                'updated_at' => $registration->updated_at,
            ]);
        } catch (ModelNotFoundException) {
            Log::warning('Registration not found', ['uuid' => $uuid]);

            return response()->json([
                'status' => 'error',
                'message' => 'Registration not found',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
