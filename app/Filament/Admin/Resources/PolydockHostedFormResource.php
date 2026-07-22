<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockHostedFormResource\Pages;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStoreApp;
use App\Services\HostedFormClassDiscovery;
use App\Support\HostedFormHtml;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PolydockHostedFormResource extends Resource
{
    protected static ?string $model = PolydockHostedForm::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'External Forms';

    protected static ?int $navigationSort = 5200;

    #[\Override]
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->helperText('Plain text — HTML is stripped')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->helperText('The form is served at /f/{slug}')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('form_class')
                    ->label('Form type')
                    ->helperText('Detected form implementations; "Generic Hosted Form" is fully driven by the fields below')
                    ->options(app(HostedFormClassDiscovery::class)->getAvailableFormClasses())
                    ->required(),
                Forms\Components\Toggle::make('enabled')
                    ->helperText('Disabled forms return 404')
                    ->default(true),
                Forms\Components\Select::make('storeApps')
                    ->label('Allowed apps')
                    ->helperText('Store apps this form may offer and provision. With none selected the form is locked.')
                    ->relationship(
                        'storeApps',
                        'name',
                        fn ($query) => $query->with('store'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (PolydockStoreApp $record) => "{$record->store->name} — {$record->name}")
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->helperText('Optional text shown under the title (generic forms only). Allowed HTML tags: '.HostedFormHtml::ALLOWED_TAGS_HINT.' — everything else is stripped.')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notice')
                    ->helperText('Optional text highlighted below the description (generic forms only). Same allowed HTML tags as the description.')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('disclaimer')
                    ->helperText('Optional text shown above the terms checkbox (generic forms only). Same allowed HTML tags as the description.')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('seo_title')
                    ->helperText('Falls back to the title')
                    ->maxLength(255),
                Forms\Components\TextInput::make('seo_description')
                    ->maxLength(255),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->prefix('/f/')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_class')
                    ->label('Form type')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('storeApps_count')
                    ->label('Allowed apps')
                    ->counts('storeApps'),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolydockHostedForms::route('/'),
            'create' => Pages\CreatePolydockHostedForm::route('/create'),
            'edit' => Pages\EditPolydockHostedForm::route('/{record}/edit'),
        ];
    }
}
