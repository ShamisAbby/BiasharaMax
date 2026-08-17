<?php

namespace App\Domain\Platform\Filament\Resources\SupportTickets\Tables;

use App\Domain\Support\Models\SupportAgent;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Services\SupportTicketService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * No create/edit/delete — tickets are opened by tenants, not platform
 * staff, matching App\Domain\Platform\Http\Controllers\SupportTicketController
 * exactly (no store/update/destroy route). Staff act on them via reply,
 * assign, resolve, close, reopen — the same set of routes.
 */
class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('business.name')
                    ->label('Business'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—'),
                TextColumn::make('assignedAgent.platformUser.name')
                    ->label('Agent')
                    ->placeholder('Unassigned'),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SupportTicket::PRIORITY_URGENT => 'danger',
                        SupportTicket::PRIORITY_HIGH => 'warning',
                        SupportTicket::PRIORITY_MEDIUM => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SupportTicket::STATUS_OPEN, SupportTicket::STATUS_REOPENED => 'warning',
                        SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_PENDING => 'info',
                        SupportTicket::STATUS_RESOLVED => 'success',
                        SupportTicket::STATUS_CLOSED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        SupportTicket::STATUS_OPEN => 'Open',
                        SupportTicket::STATUS_PENDING => 'Pending',
                        SupportTicket::STATUS_IN_PROGRESS => 'In progress',
                        SupportTicket::STATUS_RESOLVED => 'Resolved',
                        SupportTicket::STATUS_CLOSED => 'Closed',
                        SupportTicket::STATUS_REOPENED => 'Reopened',
                    ]),
                SelectFilter::make('priority')
                    ->options([
                        SupportTicket::PRIORITY_LOW => 'Low',
                        SupportTicket::PRIORITY_MEDIUM => 'Medium',
                        SupportTicket::PRIORITY_HIGH => 'High',
                        SupportTicket::PRIORITY_URGENT => 'Urgent',
                    ]),
            ])
            ->recordActions([
                static::replyAction(),
                ActionGroup::make([
                    static::assignAction(),
                    static::resolveAction(),
                    static::closeAction(),
                    static::reopenAction(),
                ]),
            ]);
    }

    protected static function replyAction(): Action
    {
        return Action::make('reply')
            ->icon(Heroicon::ChatBubbleLeftRight)
            ->schema([
                Textarea::make('body')->required()->rows(4),
                Toggle::make('is_internal_note')->label('Internal note (not visible to the tenant)'),
            ])
            ->action(function (SupportTicket $record, array $data, SupportTicketService $service): void {
                $service->reply(
                    $record,
                    'platform_user',
                    Auth::guard('platform')->id(),
                    $data['body'],
                    (bool) ($data['is_internal_note'] ?? false),
                );

                Notification::make()->title('Reply added.')->success()->send();
            });
    }

    protected static function assignAction(): Action
    {
        return Action::make('assign')
            ->icon(Heroicon::UserPlus)
            ->schema([
                Select::make('agent_id')
                    ->label('Agent')
                    ->options(fn () => SupportAgent::query()->with('platformUser')->get()->mapWithKeys(fn (SupportAgent $agent) => [$agent->id => $agent->platformUser?->name ?? $agent->id]))
                    ->required(),
            ])
            ->action(function (SupportTicket $record, array $data, SupportTicketService $service): void {
                $agent = SupportAgent::query()->findOrFail($data['agent_id']);
                $service->assign($record, $agent);

                Notification::make()->title('Ticket assigned.')->success()->send();
            });
    }

    protected static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->color('success')
            ->icon(Heroicon::CheckCircle)
            ->visible(fn (SupportTicket $record): bool => ! in_array($record->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true))
            ->action(fn (SupportTicket $record, SupportTicketService $service) => $service->resolve($record));
    }

    protected static function closeAction(): Action
    {
        return Action::make('close')
            ->color('gray')
            ->icon(Heroicon::XCircle)
            ->visible(fn (SupportTicket $record): bool => $record->status !== SupportTicket::STATUS_CLOSED)
            ->action(fn (SupportTicket $record, SupportTicketService $service) => $service->close($record));
    }

    protected static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->color('warning')
            ->icon(Heroicon::ArrowPath)
            ->visible(fn (SupportTicket $record): bool => in_array($record->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true))
            ->action(fn (SupportTicket $record, SupportTicketService $service) => $service->reopen($record));
    }
}
