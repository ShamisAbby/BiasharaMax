<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Tables;

use App\Domain\Authentication\Models\PlatformUser;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors PlatformAdminController exactly, including its two
 * self-protection rules: a platform user can't deactivate or delete their
 * own account (checked via $platformUser->is($request->user()) there,
 * auth('platform')->user()?->is($record) here).
 */
class PlatformUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not set'),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone number')
                    ->searchable()
                    ->placeholder('Not set'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PlatformUser::STATUS_ACTIVE => 'success',
                        PlatformUser::STATUS_INVITED => 'info',
                        PlatformUser::STATUS_SUSPENDED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('platformRole.name')
                    ->label('Role')
                    ->placeholder('Unrestricted (no role assigned)'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PlatformUser::STATUS_INVITED => 'Invited',
                        PlatformUser::STATUS_ACTIVE => 'Active',
                        PlatformUser::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn (PlatformUser $record): bool => $record->status !== PlatformUser::STATUS_SUSPENDED
                        && ! Auth::guard('platform')->user()?->is($record))
                    ->requiresConfirmation()
                    ->action(function (PlatformUser $record): void {
                        $record->update(['status' => PlatformUser::STATUS_SUSPENDED]);

                        Notification::make()->title('Admin deactivated')->success()->send();
                    }),
                Action::make('activate')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (PlatformUser $record): bool => $record->status === PlatformUser::STATUS_SUSPENDED)
                    ->action(function (PlatformUser $record): void {
                        $record->update(['status' => PlatformUser::STATUS_ACTIVE]);

                        Notification::make()->title('Admin activated')->success()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (PlatformUser $record): bool => ! Auth::guard('platform')->user()?->is($record)),
            ]);
    }
}
