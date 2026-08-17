<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Filament\Pages\FinanceReports;
use App\Domain\Platform\Filament\Resources\BackupRecords\BackupRecordResource;
use App\Domain\Platform\Filament\Resources\Businesses\BusinessResource;
use App\Domain\Platform\Filament\Resources\Integrations\IntegrationResource;
use App\Domain\Platform\Filament\Resources\NotificationCampaigns\NotificationCampaignResource;
use App\Domain\Platform\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use App\Domain\Platform\Filament\Resources\PlatformUsers\PlatformUserResource;
use App\Domain\Platform\Filament\Resources\Subscribers\SubscriberResource;
use App\Domain\Platform\Filament\Resources\WebsiteTemplates\WebsiteTemplateResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Curated replacement for the old dashboard's 9-button Quick Actions
 * panel. Only links to pages/actions that actually exist in this
 * Filament rebuild — the old UI's "New Business" and "New License"
 * buttons are deliberately NOT reproduced here since BusinessResource
 * and LicenseResource have no create page by design (businesses are
 * created via tenant signup, licenses via LicenseService, not admin
 * forms — see those resources' docblocks). "New Subscription" is
 * likewise omitted since subscribing a business happens via the
 * Subscribers table's "Assign plan" action, not a standalone create
 * form; linked to the Subscribers list instead.
 */
class QuickActionsWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.quick-actions';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    /**
     * Colors echo the old dashboard's Quick Actions palette (each button
     * there — New Business/New User/New Subscription/New License/
     * Broadcast/Backup Now/Generate Report/Payment Gateway/Manage
     * Templates — has its own distinct Tailwind `-600` color rather than
     * a single brand color), assigned here to this curated action list's
     * closest analog.
     *
     * @return array<int, array{label: string, url: string, icon: Heroicon, color: string}>
     */
    protected function getViewData(): array
    {
        return [
            'actions' => [
                ['label' => 'New staff member', 'url' => PlatformUserResource::getUrl('create'), 'icon' => Heroicon::UserPlus, 'color' => 'purple'],
                ['label' => 'Assign subscription', 'url' => SubscriberResource::getUrl(), 'icon' => Heroicon::CreditCard, 'color' => 'cyan'],
                ['label' => 'New broadcast', 'url' => NotificationCampaignResource::getUrl('create'), 'icon' => Heroicon::Megaphone, 'color' => 'amber'],
                ['label' => 'Run backup', 'url' => BackupRecordResource::getUrl(), 'icon' => Heroicon::CircleStack, 'color' => 'emerald'],
                ['label' => 'Generate report', 'url' => FinanceReports::getUrl(), 'icon' => Heroicon::ChartBar, 'color' => 'blue'],
                ['label' => 'Payment gateways', 'url' => PaymentGatewayResource::getUrl(), 'icon' => Heroicon::Banknotes, 'color' => 'teal'],
                ['label' => 'Website templates', 'url' => WebsiteTemplateResource::getUrl(), 'icon' => Heroicon::GlobeAlt, 'color' => 'fuchsia'],
                ['label' => 'Integrations', 'url' => IntegrationResource::getUrl(), 'icon' => Heroicon::PuzzlePiece, 'color' => 'indigo'],
                ['label' => 'Businesses', 'url' => BusinessResource::getUrl(), 'icon' => Heroicon::BuildingOffice2, 'color' => 'rose'],
            ],
        ];
    }
}
