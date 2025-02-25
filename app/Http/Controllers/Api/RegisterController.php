<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Http\Controllers\Controller;
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
     * 
     * @param Request $request
     * @return JsonResponse
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
                'message' => $e->getMessage()
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
     * 
     * @param string $uuid
     * @return JsonResponse
     */
    public function showRegister(string $uuid): JsonResponse
    {   
        try {
            $registration = UserRemoteRegistration::where('uuid', $uuid)->firstOrFail();
            Log::info('Showing user remote registration', ['registration' => $registration->toArray()]);

            return response()->json([
                'status' => $registration->status->value,
                'email' => $registration->email,
                'result_data' => $registration->result_data,
                'created_at' => $registration->created_at,
                'updated_at' => $registration->updated_at,
            ]);
        } catch (ModelNotFoundException $e) {
            Log::warning('Registration not found', ['uuid' => $uuid]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Registration not found'
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
