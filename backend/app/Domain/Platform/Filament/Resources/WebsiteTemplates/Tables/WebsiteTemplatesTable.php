<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates\Tables;

use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use App\Domain\WebsiteTemplates\Services\WebsiteTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WebsiteTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('businessType.name')
                    ->label('Business type')
                    ->placeholder('Any'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WebsiteTemplate::STATUS_PUBLISHED => 'success',
                        WebsiteTemplate::STATUS_DRAFT => 'gray',
                        WebsiteTemplate::STATUS_ARCHIVED => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_default')
                    ->boolean(),
                TextColumn::make('pages_count')
                    ->label('Pages')
                    ->counts('pages'),
                TextColumn::make('versions_count')
                    ->label('Versions'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WebsiteTemplate::STATUS_DRAFT => 'Draft',
                        WebsiteTemplate::STATUS_PUBLISHED => 'Published',
                        WebsiteTemplate::STATUS_ARCHIVED => 'Archived',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    static::publishAction(),
                    static::archiveAction(),
                    static::cloneAction(),
                ]),
                DeleteAction::make(),
            ]);
    }

    protected static function publishAction(): Action
    {
        return Action::make('publish')
            ->color('success')
            ->icon(Heroicon::CloudArrowUp)
            ->requiresConfirmation()
            ->modalDescription('This snapshots the current template and pages for rollback, then makes it live.')
            ->visible(fn (WebsiteTemplate $record): bool => $record->status !== WebsiteTemplate::STATUS_PUBLISHED)
            ->action(function (WebsiteTemplate $record, WebsiteTemplateService $service): void {
                $service->publish($record, Auth::guard('platform')->user());

                Notification::make()->title('Template published.')->success()->send();
            });
    }

    protected static function archiveAction(): Action
    {
        return Action::make('archive')
            ->color('danger')
            ->icon(Heroicon::ArchiveBox)
            ->requiresConfirmation()
            ->visible(fn (WebsiteTemplate $record): bool => $record->status !== WebsiteTemplate::STATUS_ARCHIVED)
            ->action(function (WebsiteTemplate $record, WebsiteTemplateService $service): void {
                $service->archive($record);

                Notification::make()->title('Template archived.')->success()->send();
            });
    }

    protected static function cloneAction(): Action
    {
        return Action::make('clone')
            ->icon(Heroicon::DocumentDuplicate)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
            ])
            ->action(function (WebsiteTemplate $record, array $data, WebsiteTemplateService $service): void {
                $service->clone($record, $data['name']);

                Notification::make()->title('Template cloned.')->success()->send();
            });
    }
}
