<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PolydockHostedFormResource\Pages;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStoreApp;
use App\Services\HostedFormClassDiscovery;
use App\Support\HostedFormHtml;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PolydockHostedFormResource extends Resource
{
    protected static ?string $model = PolydockHostedForm::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Apps';

    protected static ?string $navigationLabel = 'External Forms';

    protected static ?int $navigationSort = 5200;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->helperText('Plain text — HTML is stripped')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('The form is served at /f/{slug}')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('form_class')
                    ->label('Form type')
                    ->helperText('Detected form implementations; "Generic Hosted Form" is fully driven by the fields below')
                    ->options(app(HostedFormClassDiscovery::class)->getAvailableFormClasses())
                    ->required(),
                Toggle::make('enabled')
                    ->helperText('Disabled forms return 404')
                    ->default(true),
                Select::make('storeApps')
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
                Textarea::make('description')
                    ->helperText('Optional text shown under the title (generic forms only). Allowed HTML tags: '.HostedFormHtml::ALLOWED_TAGS_HINT.' — everything else is stripped.')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('notice')
                    ->helperText('Optional text highlighted below the description (generic forms only). Same allowed HTML tags as the description.')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('disclaimer')
                    ->helperText('Optional text shown above the terms checkbox (generic forms only). Same allowed HTML tags as the description.')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('seo_title')
                    ->helperText('Falls back to the title')
                    ->maxLength(255),
                TextInput::make('seo_description')
                    ->maxLength(255),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->prefix('/f/')
                    ->searchable(),
                TextColumn::make('form_class')
                    ->label('Form type')
                    ->formatStateUsing(fn (string $state) => app(HostedFormClassDiscovery::class)->getAvailableFormClasses()[$state] ?? class_basename($state))
                    ->badge(),
                TextColumn::make('storeApps_count')
                    ->label('Allowed apps')
                    ->counts('storeApps'),
                IconColumn::make('enabled')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
