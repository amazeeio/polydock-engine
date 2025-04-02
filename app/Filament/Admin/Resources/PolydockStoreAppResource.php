<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages;
use App\Filament\Admin\Resources\PolydockStoreAppResource\RelationManagers;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Grid as InfolistGrid;

class PolydockStoreAppResource extends Resource
{
    protected static ?string $model = PolydockStoreApp::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'Apps';

    protected static ?int $navigationSort = 5100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('polydock_store_id')
                    ->label('Store')
                    ->options(PolydockStore::all()->pluck('name', 'id'))
                    ->required()
                    ->disabled(fn (PolydockStoreApp $record) => 
                        $record && $record->instances()->exists()
                    )
                    ->dehydrated(fn (PolydockStoreApp $record) => 
                        !$record || !$record->instances()->exists()
                    ),
                Forms\Components\TextInput::make('polydock_app_class')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('website')
                    ->maxLength(255),
                Forms\Components\TextInput::make('support_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lagoon_deploy_git')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lagoon_deploy_branch')
                    ->required()
                    ->maxLength(255)
                    ->default('main'),
                Forms\Components\Select::make('status')
                    ->options(PolydockStoreAppStatusEnum::class)
                    ->required(),
                Forms\Components\Toggle::make('available_for_trials')
                    ->required(),
                Forms\Components\TextInput::make('target_unallocated_app_instances')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Section::make('Instance Ready Email Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('email_subject_line')
                            ->label('Email Subject Line')
                            ->placeholder('Your {app name} Instance is Ready')
                            ->helperText('Leave blank to use default subject')
                            ->columnSpanFull(),
                            
                        Forms\Components\MarkdownEditor::make('email_body_markdown')
                            ->label('Email Body Content')
                            ->placeholder('Enter custom content for the "What to Know About Your App" section')
                            ->helperText('This content will appear between the access details and signature')
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'heading',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Section::make('Trial Settings')
                    ->schema([
                        Forms\Components\TextInput::make('trial_duration_days')
                            ->label('Trial Duration (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365),

                        Grid::make(2)->schema([
                            Section::make('Mid-trial Email')
                                ->schema([
                                    Forms\Components\Toggle::make('send_midtrial_email')
                                        ->label('Send Mid-trial Email'),
                                    Forms\Components\TextInput::make('midtrial_email_subject')
                                        ->label('Subject Line')
                                        ->maxLength(255),
                                    Forms\Components\MarkdownEditor::make('midtrial_email_markdown')
                                        ->label('Email Content')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('One Day Left Email')
                                ->schema([
                                    Forms\Components\Toggle::make('send_one_day_left_email')
                                        ->label('Send One Day Left Email'),
                                    Forms\Components\TextInput::make('one_day_left_email_subject')
                                        ->label('Subject Line')
                                        ->maxLength(255),
                                    Forms\Components\MarkdownEditor::make('one_day_left_email_markdown')
                                        ->label('Email Content')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Trial Complete Email')
                                ->schema([
                                    Forms\Components\Toggle::make('send_trial_complete_email')
                                        ->label('Send Trial Complete Email'),
                                    Forms\Components\TextInput::make('trial_complete_email_subject')
                                        ->label('Subject Line')
                                        ->maxLength(255),
                                    Forms\Components\MarkdownEditor::make('trial_complete_email_markdown')
                                        ->label('Email Content')
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\IconColumn::make('available_for_trials')
                    ->label('Trials')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('target_unallocated_app_instances')
                    ->label('Target Unallocated')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unallocated_instances_count')
                    ->label('Unallocated')
                    ->sortable(),
                Tables\Columns\TextColumn::make('allocatedInstances')
                    ->state(function($record) {
                        return $record->allocatedInstances()->count(); 
                    })
                    ->label('Allocated')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (PolydockStoreApp $record): bool => 
                        $record->instances()->exists()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn () => true), // Disable bulk delete entirely
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockStoreApps::route('/'),
            'create' => Pages\CreatePolydockStoreApp::route('/create'),
            'view' => Pages\ViewPolydockStoreApp::route('/{record}'),
            'edit' => Pages\EditPolydockStoreApp::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('App Details')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('App Name'),
                                \Filament\Infolists\Components\TextEntry::make('store.name')
                                    ->label('Store')
                                    ->icon('heroicon-m-building-storefront')
                                    ->iconColor('primary'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ]),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                        
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('lagoon_deploy_git')
                                    ->copyable()
                                    ->label('Git Repository')
                                    ->icon('heroicon-m-code-bracket')
                                    ->iconColor('gray')
                                    ->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('lagoon_deploy_branch')
                                    ->label('Deploy Branch')
                                    ->icon('heroicon-m-code-bracket-square')
                                    ->iconColor('warning'),
                            ])
                    ])
                    ->columnSpan(2),

                \Filament\Infolists\Components\Section::make('Instance Management')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(1)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('unallocated_instances_count')
                                    ->label('Unallocated Instances')
                                    ->icon('heroicon-m-queue-list')
                                    ->iconColor('warning'),
                                \Filament\Infolists\Components\TextEntry::make('target_unallocated_app_instances')
                                    ->label('Target Unallocated Instances')
                                    ->icon('heroicon-m-queue-list')
                                    ->iconColor('warning'),
                                \Filament\Infolists\Components\TextEntry::make('allocatedInstances')
                                    ->label('Allocated Instances')
                                    ->state(fn ($record) => $record->allocatedInstances()->count())
                                    ->icon('heroicon-m-check-circle')
                                    ->iconColor('success'),
                            ]),
                    ])
                    ->columnSpan(1),
                
                \Filament\Infolists\Components\Section::make('Support Information')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('author')
                                    ->icon('heroicon-m-user')
                                    ->iconColor('primary'),
                                \Filament\Infolists\Components\TextEntry::make('support_email')
                                    ->icon('heroicon-m-envelope')
                                    ->iconColor('success'),
                            ]),
                        \Filament\Infolists\Components\TextEntry::make('website')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-globe-alt')
                            ->iconColor('info'),
                    ])
                    ->columnSpan(3),

                \Filament\Infolists\Components\Section::make('Trial Settings')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\IconEntry::make('available_for_trials')
                                    ->label('Available for Trials')
                                    ->boolean(),
                                \Filament\Infolists\Components\TextEntry::make('trial_duration_days')
                                    ->label('Trial Duration')
                                    ->suffix(' days')
                                    ->placeholder('Not set'),
                            ]),
                    ])
                    ->columnSpan(3),

                \Filament\Infolists\Components\Section::make('Mid-trial Email')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\IconEntry::make('send_midtrial_email')
                                    ->label('Email Enabled')
                                    ->boolean(),
                                \Filament\Infolists\Components\TextEntry::make('midtrial_email_subject')
                                    ->label('Subject Line')
                                    ->visible(fn ($record) => $record->send_midtrial_email)
                                    ->placeholder('Not configured'),
                            ]),
                    ])
                    ->columnSpan(3),

                \Filament\Infolists\Components\Section::make('One Day Left Email')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\IconEntry::make('send_one_day_left_email')
                                    ->label('Email Enabled')
                                    ->boolean(),
                                \Filament\Infolists\Components\TextEntry::make('one_day_left_email_subject')
                                    ->label('Subject Line')
                                    ->visible(fn ($record) => $record->send_one_day_left_email)
                                    ->placeholder('Not configured'),
                            ]),
                    ])
                    ->columnSpan(3),

                \Filament\Infolists\Components\Section::make('Trial Complete Email')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\IconEntry::make('send_trial_complete_email')
                                    ->label('Email Enabled')
                                    ->boolean(),
                                \Filament\Infolists\Components\TextEntry::make('trial_complete_email_subject')
                                    ->label('Subject Line')
                                    ->visible(fn ($record) => $record->send_trial_complete_email)
                                    ->placeholder('Not configured'),
                            ]),
                    ])
                    ->columnSpan(3),

            ])
            ->columns(3);
    }
}
