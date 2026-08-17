<?php

namespace App\Domain\Platform\Filament\Resources\Subscribers;

use App\Domain\Platform\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Domain\Platform\Filament\Resources\Subscribers\Tables\SubscribersTable;
use App\Domain\Subscription\Models\Subscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * No create/edit/delete — subscriptions only ever come into existence via
 * the signup flow (SubscriptionService::startTrial/startSubscription).
 * Platform staff manage an existing subscription entirely through the
 * table row actions (assign plan, extend trial, record a renewal
 * payment, suspend/restore/cancel, email the owner), matching
 * App\Domain\Platform\Http\Controllers\SubscriberController exactly —
 * there was never a create/edit/delete route for subscribers there
 * either.
 */
class SubscriberResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Subscribers';

    protected static ?string $modelLabel = 'subscriber';

    public static function table(Table $table): Table
    {
        return SubscribersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscribers::route('/'),
        ];
    }
}
