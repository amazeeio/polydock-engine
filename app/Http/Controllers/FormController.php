<?php

namespace App\Http\Controllers;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Forms\DrupalAIDemoDrupalOrgForm;
use App\Forms\HostedFormInterface;
use App\Models\PolydockStore;
use App\Models\UserRemoteRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    /**
     * Map slugs to their concrete HostedFormInterface classes.
     */
    private function getFormBySlug(string $slug): ?HostedFormInterface
    {
        $forms = [
            'drupal-ai-demo' => DrupalAIDemoDrupalOrgForm::class,
        ];

        if (! isset($forms[$slug])) {
            return null;
        }

        return app($forms[$slug]);
    }

    /**
     * Display the hosted iframe form.
     */
    public function show(string $formSlug, Request $request): Response
    {
        $form = $this->getFormBySlug($formSlug);

        if (! $form) {
            abort(404, 'Form not found.');
        }

        // Fetch public stores with available trial apps
        $regions = PolydockStore::query()
            ->where('status', PolydockStoreStatusEnum::PUBLIC)
            ->with(['apps' => function ($query) {
                $query->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
                    ->where('available_for_trials', true);
            }])
            ->get();

        $regionsData = $regions->map(fn ($store) => [
            'id' => $store->id,
            'name' => $store->name,
            'apps' => $store->apps->map(fn ($app) => [
                'uuid' => $app->uuid,
                'name' => $app->name,
            ]),
        ]);

        $viewName = $form->getViewName();

        if (! view()->exists($viewName)) {
            abort(500, "View [{$viewName}] not found for form.");
        }

        $response = response()->view($viewName, [
            'form' => $form,
            'regions' => $regions,
            'regionsData' => $regionsData,
            'recaptchaSiteKey' => config('services.recaptcha.sitekey'),
        ]);

        // Inject secure framing headers based on allowed origins
        $origins = implode(' ', $form->getAllowedEmbedOrigins());
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' {$origins}");

        return $response;
    }

    /**
     * Submit and process the hosted iframe form.
     */
    public function submit(string $formSlug, Request $request): JsonResponse
    {
        $form = $this->getFormBySlug($formSlug);

        if (! $form) {
            return response()->json([
                'status' => 'error',
                'message' => 'Form not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Perform standard request validation based on Form definitions
        $validator = Validator::make($request->all(), $form->getValidationRules());

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Handle reCAPTCHA verification if enabled
        if ($form->getRecaptchaEnabled()) {
            $recaptchaToken = $request->input('recaptcha');

            if (! $recaptchaToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please verify that you are not a robot.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $secretKey = config('services.recaptcha.secret');

            try {
                $recaptchaResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secretKey,
                    'response' => $recaptchaToken,
                    'remoteip' => $request->ip(),
                ]);

                if (! $recaptchaResponse->json('success')) {
                    Log::warning('reCAPTCHA verification failed for hosted form', [
                        'form' => $formSlug,
                        'ip' => $request->ip(),
                        'response' => $recaptchaResponse->json(),
                    ]);

                    return response()->json([
                        'status' => 'error',
                        'message' => 'reCAPTCHA verification failed. Please try again.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            } catch (\Exception $e) {
                Log::error('reCAPTCHA communication error during form submit', [
                    'form' => $formSlug,
                    'error' => $e->getMessage(),
                ]);

                // Fallback graceful check in dev environment to allow offline testing
                if (app()->environment('production', 'prod')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unable to verify reCAPTCHA. Please try again later.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        }

        // Transform form data to match UserRemoteRegistration structure
        $payload = $form->transformPayload($validator->validated());

        try {
            // Create the remote registration model which dispatches async provisioning
            $registration = UserRemoteRegistration::create([
                'email' => $payload['email'],
                'request_data' => $payload,
                'status' => UserRemoteRegistrationStatusEnum::PENDING,
            ]);

            Log::info('Created UserRemoteRegistration via hosted form', [
                'form' => $formSlug,
                'registration_id' => $registration->id,
                'uuid' => $registration->uuid,
            ]);

            return response()->json([
                'status' => UserRemoteRegistrationStatusEnum::PENDING->value,
                'message' => 'Registration pending',
                'id' => $registration->uuid,
            ], Response::HTTP_ACCEPTED);
        } catch (\Exception $e) {
            Log::error('Error creating user remote registration from hosted form', [
                'form' => $formSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
