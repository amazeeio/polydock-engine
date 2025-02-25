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
