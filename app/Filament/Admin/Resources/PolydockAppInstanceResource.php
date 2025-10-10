<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\RelationManagers;
use App\Models\PolydockAppInstance;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\Helpers\LagoonHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;

class PolydockAppInstanceResource extends Resource
{
    protected static ?string $model = PolydockAppInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'App Instances';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Instance Name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record) => $record->storeApp->store->name.' - '.$record->storeApp->name)
                    ->searchable(),
                TextColumn::make('userGroup.name')
                    ->label('User Group')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getColor())
                    ->icon(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getIcon())
                    ->formatStateUsing(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getLabel()),
                TextColumn::make('is_trial')
                    ->state(fn ($record) => $record->is_trial ? 'Yes' : 'No')
                    ->label('Trial'),
                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->description(fn ($record) => $record->trial_completed ? 'Trial completed' : 'Trial active')
                    ->dateTime(),
                TextColumn::make('send_midtrial_email_at')
                    ->label('Midtrial Email')
                    ->description(fn ($record) => $record->midtrial_email_sent ? 'Sent' : 'Pending')
                    ->state(fn ($record) => $record->send_midtrial_email_at ? $record->send_midtrial_email_at->format('Y-m-d H:i:s') : ''),
                TextColumn::make('send_one_day_left_email_at')
                    ->label('1D Left Email')
                    ->description(fn ($record) => $record->one_day_left_email_sent ? 'Sent' : 'Pending')
                    ->state(fn ($record) => $record->send_one_day_left_email_at ? $record->send_one_day_left_email_at->format('Y-m-d H:i:s') : ''),
                TextColumn::make('trial_complete_email_sent')
                    ->label('Trial Complete Email')
                    ->state(fn ($record) => ($record->is_trial && $record->trial_complete_email_sent) ? 'Sent' : ($record->is_trial ? 'Pending' : '')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PolydockAppInstanceStatus::cases())
                        ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                        ->toArray())
                    ->multiple()
                    ->label('Instance Status')
                    ->indicator('Status'),

                SelectFilter::make('status_group')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'polling' => 'Polling',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where(function ($query) use ($data) {
                            match ($data['value']) {
                                'pending' => $query->whereIn('status', PolydockAppInstance::$pendingStatuses),
                                'completed' => $query->whereIn('status', PolydockAppInstance::$completedStatuses),
                                'failed' => $query->whereIn('status', PolydockAppInstance::$failedStatuses),
                                'polling' => $query->whereIn('status', PolydockAppInstance::$pollingStatuses),
                                default => null,
                            };
                        });
                    })
                    ->label('Status Group')
                    ->indicator('Status Group'),

                SelectFilter::make('stage_group')
                    ->options([
                        'create' => 'Create Stage',
                        'deploy' => 'Deploy Stage',
                        'remove' => 'Remove Stage',
                        'upgrade' => 'Upgrade Stage',
                        'running' => 'Running Stage',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->where(function ($query) use ($data) {
                            match ($data['value']) {
                                'create' => $query->whereIn('status', PolydockAppInstance::$stageCreateStatuses),
                                'deploy' => $query->whereIn('status', PolydockAppInstance::$stageDeployStatuses),
                                'remove' => $query->whereIn('status', PolydockAppInstance::$stageRemoveStatuses),
                                'upgrade' => $query->whereIn('status', PolydockAppInstance::$stageUpgradeStatuses),
                                'running' => $query->whereIn('status', PolydockAppInstance::$stageRunningStatuses),
                                default => null,
                            };
                        });
                    })
                    ->label('Stage Group')
                    ->indicator('Stage'),

                SelectFilter::make('is_trial')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Is Trial')
                    ->indicator('Is Trial'),

                SelectFilter::make('store')
                    ->relationship('storeApp.store', 'name')
                    ->label('Store')
                    ->searchable()
                    ->preload()
                    ->indicator('Store'),

                SelectFilter::make('polydock_store_app_id')
                    ->relationship('storeApp', 'name')
                    ->label('Store App')
                    ->searchable()
                    ->preload()
                    ->indicator('Store App'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Instance Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Instance Name'),
                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar')
                                    ->iconColor('gray'),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getColor())
                                    ->icon(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getIcon())
                                    ->formatStateUsing(fn ($state) => PolydockAppInstanceStatus::from($state->value)->getLabel()),
                                \Filament\Infolists\Components\TextEntry::make('status_message')
                                    ->label('Status Message'),
                            ]),
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('storeApp.lagoon_deploy_region_id_ext')
                                    ->label('Deploy Region')
                                    ->formatStateUsing(fn ($state) => LagoonHelper::getLagoonCodeDataValueForRegion($state, 'name')),
                                \Filament\Infolists\Components\TextEntry::make('storeApp.amazee_ai_backend_region_id_ext')
                                    ->label('AI Backend Region')
                                    ->formatStateUsing(fn ($state) => AmazeeAiBackendHelper::getAmazeeAiBackendCodeDataValueForRegion($state, 'name')),
                            ]),
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('App & Group')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('storeApp.name')
                            ->label('Store App')
                            ->icon('heroicon-m-squares-2x2')
                            ->iconColor('primary'),
                        \Filament\Infolists\Components\TextEntry::make('userGroup.name')
                            ->label('User Group')
                            ->visible(fn ($record) => $record->userGroup !== null)
                            ->url(fn ($record) => UserGroupResource::getUrl('view', ['record' => $record->userGroup]))
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-user-group')
                            ->iconColor('success'),
                    ])
                    ->columnSpan(1),

                \Filament\Infolists\Components\Section::make('Instance Data')
                    ->description('Safe data that can be shared with webhooks')
                    ->schema(function ($record) {
                        return self::getRenderedSafeDataForRecord($record);
                    })
                    ->columnSpan(3)
                    ->collapsible(),
            ])
            ->columns(3);
    }

    public static function getRenderedSafeDataForRecord(PolydockAppInstance $record): array
    {
        $safeData = $record->data;
        $renderedArray = [];
        foreach ($safeData as $key => $value) {

            if ($record->shouldFilterKey($key, $record->getSensitiveDataKeys())) {
                $value = 'REDACTED';
            }

            $renderKey = 'webhook_data_'.$key;
            $renderedItem = \Filament\Infolists\Components\TextEntry::make($renderKey)
                ->label($key)
                ->markdown()
                ->columnSpanFull()
                ->bulleted();

            if (is_array($value)) {
                $renderedItem->state($value);
            } else {
                $renderedItem->state([$value]);
            }

            $renderedArray[] = $renderedItem;
        }

        return $renderedArray;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogsRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockAppInstances::route('/'),
            'view' => Pages\ViewPolydockAppInstance::route('/{record}'),
            'edit' => Pages\EditPolydockAppInstance::route('/{record}/edit'),
        ];
    }
}
