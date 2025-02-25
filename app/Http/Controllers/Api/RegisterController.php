<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegisterController extends Controller
{

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRegister(Request $request)
    {
        $email = $request->input('email');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $organization_name = $request->input('organization_name');
        $region_id = $request->input('region_id');
    
        if(preg_match('/fail/', $email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something is wrong.'
            ], 400);
        }

        if(preg_match('/redirect/', $email)) {
            return response()->json([
                'status' => 'redirect',
                'message' => 'Welcome to the jungle.',
                'redirect_url' => 'https://www.amazee.io'
            ], 200);
        }

        if(preg_match('/poll/', $email)) {
            return response()->json([
                'status' => 'poll',
                'message' => 'Your registration is being processed.',
                'status_poll_url' => route('register.show', ['id' => 666])
            ], 200);
        }
        
        if(preg_match('/registered/', $email)) {
            return response()->json([
                'status' => 'registered',
                'message' => 'You have been registered.',
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Something else went wrong.'
        ], 400);
    }

    /**
     * Display the specified resource.
     */
    public function showRegister(string $id)
    {
        return [
            'status' => 'processing',
            'message' => 'Processing Registration',
            'registration_id' => $id
        ];
    }
}
