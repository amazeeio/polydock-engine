<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\UserRemoteRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        try {
            $registration = UserRemoteRegistration::create([
                'email' => $request->input('email'),
                'request_data' => $request->all(),
                'status' => UserRemoteRegistrationStatusEnum::PENDING,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Registration pending',
            'id' => $registration->uuid,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Display the specified resource.
     * 
     * @param UserRemoteRegistration $registration
     * @return JsonResponse
     */
    public function showRegister(UserRemoteRegistration $registration): JsonResponse
    {
        return response()->json([
            'status' => $registration->status->value,
            'email' => $registration->email,
            'result_data' => $registration->result_data,
            'created_at' => $registration->created_at,
            'updated_at' => $registration->updated_at,
        ]);
    }
}
