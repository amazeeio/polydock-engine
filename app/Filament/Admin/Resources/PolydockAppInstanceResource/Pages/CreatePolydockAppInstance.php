<?php

namespace App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Filament\Admin\Resources\PolydockAppInstanceResource;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;

class CreatePolydockAppInstance extends Page
{
    protected static string $resource = PolydockAppInstanceResource::class;

    protected static string $view = 'filament.admin.pages.create-polydock-app-instance';

    protected static ?string $title = 'Create App Instance';

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'aup_and_privacy_acceptance' => true,
            'opt_in_to_product_updates' => true,
            'is_trial' => true,
            'custom_fields' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->description('Enter the user details for the instance owner')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('user@example.com')
                            ->columnSpan(2),
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('John'),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Doe'),
                        TextInput::make('organization')
                            ->maxLength(255)
                            ->placeholder('Acme Inc.'),
                        TextInput::make('job_title')
                            ->maxLength(255)
                            ->placeholder('Developer'),
                    ])
                    ->columns(2),

                Section::make('App Configuration')
                    ->description('Select the app and configure instance settings')
                    ->schema([
                        Select::make('trial_app')
                            ->label('Store App')
                            ->options(
                                PolydockStoreApp::query()
                                    ->where('available_for_trials', true)
                                    ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
                                    ->get()
                                    ->mapWithKeys(fn ($app) => [$app->uuid => $app->name.' ('.$app->store->name.')']),
                            )
                            ->required()
                            ->searchable()
                            ->placeholder('Select an app'),
                        Toggle::make('is_trial')
                            ->label('Is Trial Instance')
                            ->default(true)
                            ->helperText(
                                'If enabled, trial duration and emails will be configured based on the store app settings',
                            ),
                    ]),

                Section::make('Consent & Preferences')
                    ->description('These are auto-accepted for admin-created instances')
                    ->schema([
                        Toggle::make('aup_and_privacy_acceptance')
                            ->label('AUP and Privacy Acceptance')
                            ->default(true)
                            ->disabled()
                            ->dehydrated(),
                        Toggle::make('opt_in_to_product_updates')
                            ->label('Opt-in to Product Updates')
                            ->default(true),
                    ])
                    ->columns(2),

                Grid::make(2)->schema([
                    Section::make('Custom Fields')
                        ->description(
                            'Add additional key-value data to pass through to the Polydock webhooks (e.g. to be consumed by n8n)',
                        )
                        ->schema([
                            KeyValue::make('custom_fields')
                                ->label('')
                                ->keyLabel('Field Name')
                                ->valueLabel('Value')
                                ->addActionLabel('Add Custom Field')
                                ->reorderable()
                                ->columnSpanFull(),
                        ])
                        ->columnSpan(1),
                ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        Log::info('Admin creating app instance', ['data' => $data]);

        try {
            // Build the request data array
            $requestData = [
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'organization' => $data['organization'] ?? '',
                'job_title' => $data['job_title'] ?? '',
                'register_type' => 'REQUEST_TRIAL',
                'trial_app' => $data['trial_app'],
                'aup_and_privacy_acceptance' => $data['aup_and_privacy_acceptance'] ? 1 : 0,
                'opt_in_to_product_updates' => $data['opt_in_to_product_updates'] ? 1 : 0,
                'admin_created' => true,
                'is_trial' => $data['is_trial'],
            ];

            // Merge custom fields into request data
            if (! empty($data['custom_fields'])) {
                $requestData = array_merge($requestData, $data['custom_fields']);
            }

            // Create the UserRemoteRegistration record
            $registration = UserRemoteRegistration::create([
                'email' => $data['email'],
                'request_data' => $requestData,
            ]);

            Log::info('Created registration for admin-initiated instance', [
                'registration_id' => $registration->id,
                'registration_uuid' => $registration->uuid,
            ]);

            // Dispatch the job to process the registration
            ProcessUserRemoteRegistration::dispatch($registration);

            Notification::make()
                ->title('Instance creation started')
                ->body('The app instance is being created. You can track its progress in the App Instances list.')
                ->success()
                ->send();

            // Redirect to the registrations list so they can track progress
            $this->redirect(PolydockAppInstanceResource::getUrl('index'));
        } catch (\Exception $e) {
            Log::error('Failed to create admin-initiated instance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Failed to create instance')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Create Instance')
                ->submit('create'),
        ];
    }
}
