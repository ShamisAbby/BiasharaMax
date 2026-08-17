<?php

namespace App\Domain\Platform\Filament\Resources\Permissions\Tables;

use App\Domain\RBAC\Models\Permission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only browse/search over every permission (tenant + platform
 * scope), matching PermissionMatrixController exactly — including its
 * case-insensitive LOWER() search (see that controller's comment on why
 * plain `like` isn't safe across engines). Which platform roles grant
 * each permission is shown on the Platform Roles resource instead of
 * here, to avoid duplicating the same pivot data in two places.
 */
class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('module')
            ->columns([
                TextColumn::make('module')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(query: fn ($query, string $search) => $query->whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($search).'%'])),
                TextColumn::make('slug')
                    ->searchable(query: fn ($query, string $search) => $query->whereRaw('LOWER(slug) like ?', ['%'.mb_strtolower($search).'%']))
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('scope')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'platform' ? 'warning' : 'gray'),
                TextColumn::make('action')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->options(fn () => Permission::query()->distinct()->orderBy('scope')->pluck('scope', 'scope')),
                SelectFilter::make('module')
                    ->options(fn () => Permission::query()->distinct()->orderBy('module')->pluck('module', 'module')),
            ]);
    }
}
