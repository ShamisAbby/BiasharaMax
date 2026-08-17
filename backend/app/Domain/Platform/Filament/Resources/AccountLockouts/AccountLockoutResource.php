<?php

namespace App\Domain\Platform\Filament\Resources\AccountLockouts;

use App\Domain\Platform\Filament\Resources\AccountLockouts\Pages\CreateAccountLockout;
use App\Domain\Platform\Filament\Resources\AccountLockouts\Pages\ListAccountLockouts;
use App\Domain\Security\Models\AccountLockout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AccountLockoutResource extends Resource
{
    protected static ?string $model = AccountLockout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Account Lockouts';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('lockable_type')
                ->options([
                    AccountLockout::TYPE_PLATFORM_USER => 'Platform user',
                    AccountLockout::TYPE_USER => 'Tenant user',
                ])
                ->required(),
            TextInput::make('lockable_id')
                ->label('User ID')
                ->required()
                ->helperText('UUID of the user or platform user to lock.'),
            Textarea::make('reason')->maxLength(500),
            DateTimePicker::make('expires_at')->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('locked_at', 'desc')
            ->columns([
                TextColumn::make('lockable_type')->badge(),
                TextColumn::make('lockable_id')->fontFamily('mono')->limit(12),
                TextColumn::make('reason')->limit(50)->placeholder('—'),
                TextColumn::make('locked_at')->dateTime(),
                IconColumn::make('unlocked_at')
                    ->label('Unlocked')
                    ->boolean(fn ($state) => $state !== null),
            ])
            ->recordActions([
                Action::make('unlock')
                    ->color('success')
                    ->icon(Heroicon::LockOpen)
                    ->visible(fn (AccountLockout $record): bool => $record->unlocked_at === null)
                    ->requiresConfirmation()
                    ->action(function (AccountLockout $record): void {
                        $record->update([
                            'unlocked_at' => now(),
                            'unlocked_by' => Auth::guard('platform')->id(),
                        ]);
                    }),
            ]);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountLockouts::route('/'),
            'create' => CreateAccountLockout::route('/create'),
        ];
    }
}
