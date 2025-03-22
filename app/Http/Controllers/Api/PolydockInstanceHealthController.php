<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PolydockInstanceHealthController extends Controller
{
    /**
     * Handle both GET and POST requests for instance health updates
     */
    public function __invoke(Request $request, string $uuid, string $status)
    {
        $logContext = [
            'uuid' => $uuid,
            'status' => $status,
            'location' => 'PolydockInstanceHealthController',
            'method' => 'invoke',
            'query' => $request->query(),
            'data' => $request->all()
        ];

        // Find the instance
        $instance = PolydockAppInstance::where('uuid', $uuid)->first();
        
        if (!$instance) {
            Log::error('Instance not found', $logContext);
            return response()->json([
                'error' => 'Instance not found',
                'status_code' => 404
            ], 404);
        }

        // Validate status
        try {
            $statusEnum = PolydockAppInstanceStatus::from($status);
        } catch (\ValueError $e) {
            Log::error('Invalid status value', $logContext + ['status_code' => 400]);
            
            return response()->json([
                'error' => 'Invalid status value',
                'status_code' => 400
            ], 400);
        }

        $logContext['initial_status'] = $instance->status;

        $acceptableStatuses = [
            PolydockAppInstanceStatus::RUNNING_HEALTHY,
            PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
            PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
            PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
        ];


        // Validate that the current status is in a state where we can update it
        if (!in_array($instance->status, $acceptableStatuses)) {
            Log::error('Current status is not ready for health check update', $logContext + ['status_code' => 400]);
            
            return response()->json([
                'error' => 'Current status is not ready for health check update',
                'status_code' => 400
            ], 400);
        }

        // Check if status is allowed
        if (!in_array($statusEnum, PolydockAppInstance::$stageRunningStatuses)) {
            Log::error('Invalid running status', $logContext + ['status_code' => 400]); 

            return response()->json([
                'error' => 'Invalid running status',
                'status_code' => 400,
                'allowed_statuses' => array_map(
                    fn($status) => $status->value, 
                    PolydockAppInstance::$stageRunningStatuses
                )
            ], 400);
        }

        // Get debug data based on request type
        $debugData = [];
        if ($request->isMethod('post')) {
            $debugData = $request->all();
        } else {
            $debugData = $request->query();
        }

        $logContext['debug_data'] = $debugData;
        $logContext['status_code'] = 200;

        // Log debug data if present
        if (!empty($debugData)) {
            $instance->debug('Health check data received', $logContext);
        }

        // Update instance status
        $instance->setStatus($statusEnum)->save();

        return response()->json([
            'message' => 'Health status updated successfully',
            'instance' => $instance->uuid,
            'status' => $status,
            'status_code' => 200
        ], 200);
    }
} 