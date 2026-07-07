<?php

declare(strict_types=1);

namespace App\Filament\Admin\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Reusable relation manager showing the audit trail for any model
 * that uses the Spatie LogsActivity trait.
 *
 * Register on any resource via:
 *   \App\Filament\Admin\RelationManagers\ActivitiesRelationManager::class
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity Log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can('view_activity_log') || $user->can('view_any_activity_log');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('causer.email')
                    ->label('Actor')
                    ->placeholder('System'),
                TextColumn::make('description')
                    ->label('Action')
                    ->wrap()
                    ->limit(80),
                TextColumn::make('event')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('properties')
                    ->label('Changes')
                    ->formatStateUsing(function (Activity $record): string {
                        $props = $record->properties;
                        $parts = [];

                        if (isset($props['old'], $props['attributes'])) {
                            $format = fn (mixed $v): string => is_array($v) ? json_encode($v) : (string) $v;
                            foreach ($props['attributes'] as $key => $newVal) {
                                $oldVal = $props['old'][$key] ?? '-';
                                $parts[] = "{$key}: {$format($oldVal)} -> {$format($newVal)}";
                            }
                        }

                        return implode(', ', $parts) ?: '-';
                    })
                    ->wrap()
                    ->limit(120),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
