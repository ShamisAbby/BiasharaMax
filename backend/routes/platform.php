<?php

use App\Domain\Platform\Http\Controllers\AccountLockoutController;
use App\Domain\Platform\Http\Controllers\AdminSurfaceController;
use App\Domain\Platform\Http\Controllers\AiInsightController;
use App\Domain\Platform\Http\Controllers\ApiTokenController;
use App\Domain\Platform\Http\Controllers\AuditLogController;
use App\Domain\Platform\Http\Controllers\Auth\AcceptPlatformInvitationController;
use App\Domain\Platform\Http\Controllers\Auth\PlatformAuthenticatedSessionController;
use App\Domain\Platform\Http\Controllers\BackupController;
use App\Domain\Platform\Http\Controllers\BlockedIpController;
use App\Domain\Platform\Http\Controllers\BusinessModuleController;
use App\Domain\Platform\Http\Controllers\BusinessManagementController;
use App\Domain\Platform\Http\Controllers\BusinessTypeController;
use App\Domain\Platform\Http\Controllers\DashboardController;
use App\Domain\Platform\Http\Controllers\DeveloperCenterController;
use App\Domain\Platform\Http\Controllers\FinanceDashboardController;
use App\Domain\Platform\Http\Controllers\FinanceReportController;
use App\Domain\Platform\Http\Controllers\GlobalSearchController;
use App\Domain\Platform\Http\Controllers\ImpersonationController;
use App\Domain\Platform\Http\Controllers\IntegrationController;
use App\Domain\Platform\Http\Controllers\KnowledgeBaseController;
use App\Domain\Platform\Http\Controllers\LicenseController;
use App\Domain\Platform\Http\Controllers\ModuleController;
use App\Domain\Platform\Http\Controllers\MonitoringController;
use App\Domain\Platform\Http\Controllers\NotificationCampaignController;
use App\Domain\Platform\Http\Controllers\NotificationChannelController;
use App\Domain\Platform\Http\Controllers\NotificationTemplateController;
use App\Domain\Platform\Http\Controllers\OperationsDashboardController;
use App\Domain\Platform\Http\Controllers\PaymentGatewayController;
use App\Domain\Platform\Http\Controllers\PaymentTransactionController;
use App\Domain\Platform\Http\Controllers\PermissionMatrixController;
use App\Domain\Platform\Http\Controllers\PlatformAdminController;
use App\Domain\Platform\Http\Controllers\PlatformAnnouncementController;
use App\Domain\Platform\Http\Controllers\PlatformNotificationController;
use App\Domain\Platform\Http\Controllers\PlatformProfileController;
use App\Domain\Platform\Http\Controllers\PlatformRoleController;
use App\Domain\Platform\Http\Controllers\EnvSettingsController;
use App\Domain\Platform\Http\Controllers\PlatformSettingsController;
use App\Domain\Platform\Http\Controllers\PlatformUserManagementController;
use App\Domain\Platform\Http\Controllers\RoleTemplateController;
use App\Domain\Platform\Http\Controllers\SecurityAlertController;
use App\Domain\Platform\Http\Controllers\SecurityDashboardController;
use App\Domain\Platform\Http\Controllers\RegistrationCodeController;
use App\Domain\Platform\Http\Controllers\SubscriberController;
use App\Domain\Platform\Http\Controllers\SubscriptionDashboardController;
use App\Domain\Platform\Http\Controllers\SubscriptionPlanController;
use App\Domain\Platform\Http\Controllers\SubscriptionTransactionController;
use App\Domain\Platform\Http\Controllers\SupportAgentController;
use App\Domain\Platform\Http\Controllers\SupportDepartmentController;
use App\Domain\Platform\Http\Controllers\SupportTicketController;
use App\Domain\Platform\Http\Controllers\SystemDashboardController;
use App\Domain\Platform\Http\Controllers\TwoFactorController;
use App\Domain\Platform\Http\Controllers\WebhookController;
use App\Domain\Platform\Http\Controllers\WebsiteTemplateController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('platform.')->group(function () {
    /*
     * The bare /admin URL honours the administrator's chosen surface, so
     * a bookmark of the root follows the preference the same way signing
     * in does.
     *
     * Only this route redirects across. /admin/* stays on the Inertia
     * admin whatever the preference says — the preference picks a
     * starting point, it does not fence anyone in, and enforcing it per
     * route is how you get /admin and /platform bouncing off each other
     * forever.
     */
    Route::get('/', function () {
        if (! Auth::guard('platform')->check()) {
            return redirect()->route('platform.login');
        }

        $surface = Auth::guard('platform')->user()->preferredAdminSurface();

        return $surface === \App\Domain\Platform\Support\AdminSurface::FILAMENT
            ? redirect()->to(\App\Domain\Platform\Support\AdminSurface::path($surface))
            : redirect()->route('platform.dashboard');
    })->name('home');

    Route::get('login', [PlatformAuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [PlatformAuthenticatedSessionController::class, 'store']);

    Route::middleware('signed')->group(function () {
        Route::get('staff-invitations/{platform_user}', [AcceptPlatformInvitationController::class, 'show'])
            ->name('staff-invitations.accept');
        Route::post('staff-invitations/{platform_user}', [AcceptPlatformInvitationController::class, 'store'])
            ->name('staff-invitations.store');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::post('logout', [PlatformAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        Route::get('dashboard', DashboardController::class)
            ->name('dashboard');

        Route::get('search', GlobalSearchController::class)->name('search');
        Route::get('notifications/live', PlatformNotificationController::class)->name('notifications.live');

        /*
         * Dismissal is deliberately available to any signed-in platform
         * admin, with no extra permission. It hides an item from the top
         * bar and changes nothing else — the underlying alert stays
         * unresolved, and the operator email still goes out. Gating it
         * would only produce an admin who can see a notification but not
         * acknowledge it.
         */
        Route::post('notifications/dismiss', [PlatformNotificationController::class, 'dismiss'])
            ->name('notifications.dismiss');
        Route::post('notifications/dismiss-all', [PlatformNotificationController::class, 'dismissAll'])
            ->name('notifications.dismiss-all');

        Route::get('businesses', [BusinessManagementController::class, 'index'])
            ->name('businesses.index');
        Route::post('businesses/{business}/suspend', [BusinessManagementController::class, 'suspend'])
            ->name('businesses.suspend');
        Route::post('businesses/{business}/activate', [BusinessManagementController::class, 'activate'])
            ->name('businesses.activate');
        Route::patch('businesses/{business}/subscription', [BusinessManagementController::class, 'updateSubscription'])
            ->name('businesses.subscription.update');
        Route::post('businesses/{business}/impersonate', [ImpersonationController::class, 'start'])
            ->name('businesses.impersonate');

        // Per-business dashboard section switches.
        Route::get('businesses/{business}/modules', [BusinessModuleController::class, 'index'])
            ->middleware('platform.permission:modules.manage')
            ->name('businesses.modules.index');
        Route::patch('businesses/{business}/modules', [BusinessModuleController::class, 'update'])
            ->middleware('platform.permission:modules.manage')
            ->name('businesses.modules.update');

        Route::get('users', [PlatformUserManagementController::class, 'index'])
            ->name('users.index');
        Route::post('users/{user}/activate', [PlatformUserManagementController::class, 'activate'])
            ->name('users.activate');
        Route::post('users/{user}/deactivate', [PlatformUserManagementController::class, 'deactivate'])
            ->name('users.deactivate');
        Route::post('users/{user}/send-password-reset', [PlatformUserManagementController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset');
        Route::post('users/broadcast', [PlatformUserManagementController::class, 'sendBroadcast'])
            ->name('users.broadcast');
        Route::post('users/{user}/send-email', [PlatformUserManagementController::class, 'sendEmail'])
            ->name('users.send-email');
        Route::delete('users/{user}', [PlatformUserManagementController::class, 'destroy'])
            ->name('users.destroy');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])
            ->middleware('platform.permission:audit_logs.export')
            ->name('audit-logs.export');

        Route::get('profile', [PlatformProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [PlatformProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/avatar', [PlatformProfileController::class, 'uploadAvatar'])->name('profile.avatar');
        Route::put('profile/password', [PlatformProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::prefix('profile/two-factor')->name('profile.two-factor.')->group(function () {
            Route::post('setup', [TwoFactorController::class, 'setup'])->name('setup');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::delete('disable', [TwoFactorController::class, 'disable'])->name('disable');
        });

        Route::prefix('profile/api-tokens')->name('profile.api-tokens.')->group(function () {
            Route::post('/', [ApiTokenController::class, 'store'])->name('store');
            Route::delete('{tokenId}', [ApiTokenController::class, 'destroy'])->name('destroy');
        });

        /*
         * Switches between the Inertia admin and the Filament panel.
         *
         * No permission middleware: this changes nothing but where the
         * signed-in admin lands, and every route on the far side keeps
         * its own guards. Gating the switch itself would only produce an
         * admin who can reach a surface but not choose it.
         */
        Route::post('preferences/admin-surface', [AdminSurfaceController::class, 'update'])
            ->name('preferences.admin-surface');

        Route::prefix('staff')->name('staff.')->middleware('platform.permission:platform_users.manage')->group(function () {
            Route::get('/', [PlatformAdminController::class, 'index'])->name('index');
            Route::post('/', [PlatformAdminController::class, 'store'])->name('store');
            Route::patch('{platform_user}', [PlatformAdminController::class, 'update'])->name('update');
            Route::delete('{platform_user}', [PlatformAdminController::class, 'destroy'])->name('destroy');
            Route::post('{platform_user}/activate', [PlatformAdminController::class, 'activate'])->name('activate');
            Route::post('{platform_user}/deactivate', [PlatformAdminController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', SubscriptionDashboardController::class)->name('dashboard');

            Route::get('plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
            Route::patch('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->name('plans.destroy');
            Route::post('plans/{plan}/activate', [SubscriptionPlanController::class, 'activate'])->name('plans.activate');
            Route::post('plans/{plan}/deactivate', [SubscriptionPlanController::class, 'deactivate'])->name('plans.deactivate');

            Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
            Route::patch('subscribers/{subscription}/plan', [SubscriberController::class, 'assignPlan'])->name('subscribers.assign-plan');
            Route::post('subscribers/{subscription}/extend-trial', [SubscriberController::class, 'extendTrial'])->name('subscribers.extend-trial');
            Route::post('subscribers/{subscription}/renew', [SubscriberController::class, 'renew'])->name('subscribers.renew');
            Route::post('subscribers/{subscription}/suspend', [SubscriberController::class, 'suspend'])->name('subscribers.suspend');
            Route::post('subscribers/{subscription}/restore', [SubscriberController::class, 'restore'])->name('subscribers.restore');
            Route::post('subscribers/{subscription}/cancel', [SubscriberController::class, 'cancel'])->name('subscribers.cancel');
            Route::post('subscribers/broadcast', [SubscriberController::class, 'sendBroadcast'])->name('subscribers.broadcast');
            Route::post('subscribers/{subscription}/send-email', [SubscriberController::class, 'sendEmail'])->name('subscribers.send-email');

            Route::get('transactions', [SubscriptionTransactionController::class, 'index'])->name('transactions.index');

            Route::prefix('registration-codes')->name('registration-codes.')->group(function () {
                Route::get('/', [RegistrationCodeController::class, 'index'])->name('index');
                Route::post('/', [RegistrationCodeController::class, 'store'])->name('store');
                Route::delete('{registrationCode}', [RegistrationCodeController::class, 'destroy'])->name('destroy');
            });
        });

        Route::prefix('licenses')->name('licenses.')->group(function () {
            Route::get('/', [LicenseController::class, 'dashboard'])->name('dashboard');
            Route::get('list', [LicenseController::class, 'index'])->name('index');
            Route::post('/', [LicenseController::class, 'store'])->name('store');
            Route::get('{license}', [LicenseController::class, 'show'])->name('show');
            Route::post('{license}/renew', [LicenseController::class, 'renew'])->name('renew');
            Route::post('{license}/suspend', [LicenseController::class, 'suspend'])->name('suspend');
            Route::post('{license}/restore', [LicenseController::class, 'restore'])->name('restore');
            Route::post('{license}/revoke', [LicenseController::class, 'revoke'])->name('revoke');
            Route::post('{license}/reset-activation', [LicenseController::class, 'resetActivation'])->name('reset-activation');
            Route::post('{license}/devices/{device}/deactivate', [LicenseController::class, 'deactivateDevice'])->name('devices.deactivate');
            Route::get('{license}/certificate', [LicenseController::class, 'downloadCertificate'])->name('certificate');
        });

        Route::prefix('business-types')->name('business-types.')->middleware('platform.permission:business_types.manage')->group(function () {
            Route::get('/', [BusinessTypeController::class, 'index'])->name('index');
            Route::post('/', [BusinessTypeController::class, 'store'])->name('store');
            Route::patch('{business_type}', [BusinessTypeController::class, 'update'])->name('update');
            Route::delete('{business_type}', [BusinessTypeController::class, 'destroy'])->name('destroy');
            Route::post('{business_type}/archive', [BusinessTypeController::class, 'archive'])->name('archive');
            Route::post('{business_type}/activate', [BusinessTypeController::class, 'activate'])->name('activate');
            Route::post('{business_type}/deactivate', [BusinessTypeController::class, 'deactivate'])->name('deactivate');
            Route::post('{business_type}/clone', [BusinessTypeController::class, 'clone'])->name('clone');
        });

        Route::prefix('modules')->name('modules.')->middleware('platform.permission:modules.manage')->group(function () {
            Route::get('/', [ModuleController::class, 'index'])->name('index');
            Route::post('/', [ModuleController::class, 'store'])->name('store');
            Route::patch('{module}', [ModuleController::class, 'update'])->name('update');
            Route::delete('{module}', [ModuleController::class, 'destroy'])->name('destroy');
            Route::post('{module}/enable', [ModuleController::class, 'enable'])->name('enable');
            Route::post('{module}/disable', [ModuleController::class, 'disable'])->name('disable');
            Route::post('{module}/version', [ModuleController::class, 'updateVersion'])->name('version.update');
            Route::patch('{module}/plans', [ModuleController::class, 'assignToPlans'])->name('plans.update');
            Route::patch('{module}/business-types', [ModuleController::class, 'assignToBusinessTypes'])->name('business-types.update');
        });

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', FinanceDashboardController::class)
                ->middleware('platform.permission:payments.view')
                ->name('dashboard');

            Route::prefix('payments')->name('payments.')->middleware('platform.permission:payments.view')->group(function () {
                Route::get('/', [PaymentTransactionController::class, 'index'])->name('index');
                Route::get('{payment_transaction}', [PaymentTransactionController::class, 'show'])->name('show');

                Route::middleware('platform.permission:payments.manage')->group(function () {
                    Route::post('/', [PaymentTransactionController::class, 'store'])->name('store');
                    Route::post('{payment_transaction}/retry', [PaymentTransactionController::class, 'retry'])->name('retry');
                    Route::post('{payment_transaction}/approve', [PaymentTransactionController::class, 'approve'])->name('approve');
                });

                Route::post('{payment_transaction}/refund', [PaymentTransactionController::class, 'refund'])
                    ->middleware('platform.permission:payments.refund')
                    ->name('refund');
            });

            Route::prefix('gateways')->name('gateways.')->middleware('platform.permission:payment_gateways.view')->group(function () {
                Route::get('/', [PaymentGatewayController::class, 'index'])->name('index');
                Route::get('{payment_gateway}/logs', [PaymentGatewayController::class, 'logs'])->name('logs');

                Route::middleware('platform.permission:payment_gateways.manage')->group(function () {
                    Route::post('/', [PaymentGatewayController::class, 'store'])->name('store');
                    Route::patch('{payment_gateway}', [PaymentGatewayController::class, 'update'])->name('update');
                    Route::post('{payment_gateway}/enable', [PaymentGatewayController::class, 'enable'])->name('enable');
                    Route::post('{payment_gateway}/disable', [PaymentGatewayController::class, 'disable'])->name('disable');
                    Route::post('{payment_gateway}/check-health', [PaymentGatewayController::class, 'checkHealth'])->name('check-health');
                });
            });

            Route::prefix('reports')->name('reports.')->middleware('platform.permission:finance_reports.view')->group(function () {
                Route::get('/', [FinanceReportController::class, 'index'])->name('index');

                Route::middleware('platform.permission:finance_reports.export')->group(function () {
                    Route::get('export/csv', [FinanceReportController::class, 'exportCsv'])->name('export.csv');
                    Route::get('export/pdf', [FinanceReportController::class, 'exportPdf'])->name('export.pdf');
                });
            });
        });

        Route::prefix('rbac')->name('rbac.')->group(function () {
            Route::get('permission-matrix', [PermissionMatrixController::class, 'index'])
                ->middleware('platform.permission:platform_roles.view')
                ->name('permission-matrix.index');

            Route::middleware('platform.permission:platform_roles.view')->group(function () {
                Route::get('platform-roles', [PlatformRoleController::class, 'index'])->name('platform-roles.index');
            });

            Route::middleware('platform.permission:platform_roles.manage')->group(function () {
                Route::post('platform-roles', [PlatformRoleController::class, 'store'])->name('platform-roles.store');
                Route::patch('platform-roles/{platform_role}', [PlatformRoleController::class, 'update'])->name('platform-roles.update');
                Route::delete('platform-roles/{platform_role}', [PlatformRoleController::class, 'destroy'])->name('platform-roles.destroy');
                Route::post('platform-roles/{platform_role}/clone', [PlatformRoleController::class, 'clone'])->name('platform-roles.clone');
                Route::post('platform-roles/{platform_role}/apply-template', [PlatformRoleController::class, 'applyTemplate'])->name('platform-roles.apply-template');
            });

            Route::middleware('platform.permission:role_templates.view')->group(function () {
                Route::get('role-templates', [RoleTemplateController::class, 'index'])->name('role-templates.index');
            });

            Route::middleware('platform.permission:role_templates.manage')->group(function () {
                Route::post('role-templates', [RoleTemplateController::class, 'store'])->name('role-templates.store');
                Route::patch('role-templates/{role_template}', [RoleTemplateController::class, 'update'])->name('role-templates.update');
                Route::delete('role-templates/{role_template}', [RoleTemplateController::class, 'destroy'])->name('role-templates.destroy');
            });
        });

        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('/', OperationsDashboardController::class)->name('dashboard');

            Route::prefix('website-templates')->name('website-templates.')->middleware('platform.permission:website_templates.view')->group(function () {
                Route::get('/', [WebsiteTemplateController::class, 'index'])->name('index');

                // Inside the `view` group, not `manage`: looking at a
                // template changes nothing, and anyone allowed to see the
                // list should be able to see what the entries look like.
                Route::get('{website_template}/preview', [WebsiteTemplateController::class, 'preview'])
                    ->name('preview');

                Route::middleware('platform.permission:website_templates.manage')->group(function () {
                    Route::post('/', [WebsiteTemplateController::class, 'store'])->name('store');
                    Route::patch('{website_template}', [WebsiteTemplateController::class, 'update'])->name('update');
                    Route::delete('{website_template}', [WebsiteTemplateController::class, 'destroy'])->name('destroy');
                    Route::post('{website_template}/publish', [WebsiteTemplateController::class, 'publish'])->name('publish');
                    Route::post('{website_template}/archive', [WebsiteTemplateController::class, 'archive'])->name('archive');
                    Route::post('{website_template}/clone', [WebsiteTemplateController::class, 'clone'])->name('clone');
                    Route::patch('{website_template}/plans', [WebsiteTemplateController::class, 'assignToPlans'])->name('plans.update');
                    Route::post('{website_template}/pages', [WebsiteTemplateController::class, 'storePage'])->name('pages.store');
                    Route::patch('{website_template}/pages/{page}', [WebsiteTemplateController::class, 'updatePage'])->name('pages.update');
                    Route::delete('{website_template}/pages/{page}', [WebsiteTemplateController::class, 'destroyPage'])->name('pages.destroy');
                });
            });

            Route::prefix('notifications')->name('notifications.')->middleware('platform.permission:platform_notifications.view')->group(function () {
                Route::get('/', [NotificationChannelController::class, 'index'])->name('index');

                Route::middleware('platform.permission:platform_notifications.manage')->group(function () {
                    Route::post('channels', [NotificationChannelController::class, 'store'])->name('channels.store');
                    Route::patch('channels/{notification_channel}', [NotificationChannelController::class, 'update'])->name('channels.update');
                    Route::post('channels/{notification_channel}/enable', [NotificationChannelController::class, 'enable'])->name('channels.enable');
                    Route::post('channels/{notification_channel}/disable', [NotificationChannelController::class, 'disable'])->name('channels.disable');

                    Route::post('templates', [NotificationTemplateController::class, 'store'])->name('templates.store');
                    Route::patch('templates/{notification_template}', [NotificationTemplateController::class, 'update'])->name('templates.update');
                    Route::delete('templates/{notification_template}', [NotificationTemplateController::class, 'destroy'])->name('templates.destroy');
                });

                Route::get('campaigns/{notification_campaign}', [NotificationCampaignController::class, 'show'])->name('campaigns.show');

                Route::middleware('platform.permission:platform_notifications.send')->group(function () {
                    Route::post('campaigns', [NotificationCampaignController::class, 'store'])->name('campaigns.store');
                    Route::post('campaigns/{notification_campaign}/send', [NotificationCampaignController::class, 'send'])->name('campaigns.send');
                    Route::post('deliveries/{notification_delivery}/retry', [NotificationCampaignController::class, 'retryDelivery'])->name('deliveries.retry');
                });
            });

            Route::prefix('support')->name('support.')->middleware('platform.permission:support.view')->group(function () {
                Route::get('/', [SupportTicketController::class, 'index'])->name('index');
                Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
                Route::get('{support_ticket}', [SupportTicketController::class, 'show'])->name('show');
                Route::post('{support_ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');

                Route::middleware('platform.permission:support.manage')->group(function () {
                    Route::post('{support_ticket}/assign', [SupportTicketController::class, 'assign'])->name('assign');
                    Route::post('{support_ticket}/resolve', [SupportTicketController::class, 'resolve'])->name('resolve');
                    Route::post('{support_ticket}/close', [SupportTicketController::class, 'close'])->name('close');
                    Route::post('{support_ticket}/reopen', [SupportTicketController::class, 'reopen'])->name('reopen');

                    Route::post('departments', [SupportDepartmentController::class, 'store'])->name('departments.store');
                    Route::patch('departments/{support_department}', [SupportDepartmentController::class, 'update'])->name('departments.update');

                    Route::post('agents', [SupportAgentController::class, 'store'])->name('agents.store');
                    Route::patch('agents/{support_agent}', [SupportAgentController::class, 'update'])->name('agents.update');
                    Route::delete('agents/{support_agent}', [SupportAgentController::class, 'destroy'])->name('agents.destroy');

                    Route::post('knowledge-base/categories', [KnowledgeBaseController::class, 'storeCategory'])->name('knowledge-base.categories.store');
                    Route::post('knowledge-base/articles', [KnowledgeBaseController::class, 'storeArticle'])->name('knowledge-base.articles.store');
                    Route::patch('knowledge-base/articles/{knowledge_base_article}', [KnowledgeBaseController::class, 'updateArticle'])->name('knowledge-base.articles.update');
                    Route::delete('knowledge-base/articles/{knowledge_base_article}', [KnowledgeBaseController::class, 'destroyArticle'])->name('knowledge-base.articles.destroy');

                    Route::post('announcements', [PlatformAnnouncementController::class, 'store'])->name('announcements.store');
                    Route::patch('announcements/{platform_announcement}', [PlatformAnnouncementController::class, 'update'])->name('announcements.update');
                    Route::delete('announcements/{platform_announcement}', [PlatformAnnouncementController::class, 'destroy'])->name('announcements.destroy');
                });
            });

            Route::prefix('security')->name('security.')->middleware('platform.permission:security.view')->group(function () {
                Route::get('/', [SecurityDashboardController::class, 'index'])->name('index');

                Route::middleware('platform.permission:security.manage')->group(function () {
                    Route::post('blocked-ips', [BlockedIpController::class, 'store'])->name('blocked-ips.store');
                    Route::delete('blocked-ips/{blocked_ip}', [BlockedIpController::class, 'destroy'])->name('blocked-ips.destroy');

                    Route::post('lockouts', [AccountLockoutController::class, 'store'])->name('lockouts.store');
                    Route::post('lockouts/{account_lockout}/unlock', [AccountLockoutController::class, 'unlock'])->name('lockouts.unlock');

                    Route::post('alerts/{security_alert}/resolve', [SecurityAlertController::class, 'resolve'])->name('alerts.resolve');
                });
            });

            Route::prefix('monitoring')->name('monitoring.')->middleware('platform.permission:monitoring.view')->group(function () {
                Route::get('/', [MonitoringController::class, 'index'])->name('index');
            });

            Route::prefix('developer')->name('developer.')->middleware('platform.permission:developer.view')->group(function () {
                Route::get('/', [DeveloperCenterController::class, 'index'])->name('index');

                Route::middleware('platform.permission:developer.manage')->group(function () {
                    Route::post('cache/clear', [DeveloperCenterController::class, 'clearCache'])->name('cache.clear');

                    Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
                    Route::patch('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
                    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
                    Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->name('webhooks.deliveries');
                    Route::post('webhook-deliveries/{webhook_delivery}/retry', [WebhookController::class, 'retryDelivery'])->name('webhooks.deliveries.retry');
                });
            });
        });

        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/', SystemDashboardController::class)->name('dashboard');

            Route::prefix('settings')->name('settings.')->middleware('platform.permission:platform_settings.view')->group(function () {
                Route::get('/', [PlatformSettingsController::class, 'index'])->name('index');

                Route::middleware('platform.permission:platform_settings.manage')->group(function () {
                    Route::patch('{group}', [PlatformSettingsController::class, 'updateGroup'])->name('update');
                    Route::patch('env/{group}', [EnvSettingsController::class, 'update'])->name('env.update');
                    Route::post('upload', [PlatformSettingsController::class, 'uploadFile'])->name('upload');

                    Route::post('currencies', [PlatformSettingsController::class, 'storeCurrency'])->name('currencies.store');
                    Route::patch('currencies/{currency}', [PlatformSettingsController::class, 'updateCurrency'])->name('currencies.update');

                    Route::post('tax-rates', [PlatformSettingsController::class, 'storeTaxRate'])->name('tax-rates.store');
                    Route::patch('tax-rates/{tax_rate}', [PlatformSettingsController::class, 'updateTaxRate'])->name('tax-rates.update');
                    Route::delete('tax-rates/{tax_rate}', [PlatformSettingsController::class, 'destroyTaxRate'])->name('tax-rates.destroy');
                });
            });

            Route::prefix('backups')->name('backups.')->middleware('platform.permission:backups.manage')->group(function () {
                Route::get('/', [BackupController::class, 'index'])->name('index');
                Route::post('run', [BackupController::class, 'run'])->name('run');

                // Plain .sql export/import, alongside the zip archives.
                Route::get('export-sql', [BackupController::class, 'exportSql'])->name('export-sql');
                Route::post('inspect-sql', [BackupController::class, 'inspectSql'])->name('inspect-sql');
                Route::post('restore-sql', [BackupController::class, 'restoreSql'])->name('restore-sql');
                Route::get('{backup_record}/download', [BackupController::class, 'download'])->name('download');
                Route::delete('{backup_record}', [BackupController::class, 'destroy'])->name('destroy');
                Route::get('{backup_record}/preview', [BackupController::class, 'preview'])->name('preview');
                Route::post('{backup_record}/restore', [BackupController::class, 'restore'])->name('restore');
            });

            Route::prefix('ai-insights')->name('ai-insights.')->middleware('platform.permission:ai_insights.view')->group(function () {
                Route::get('/', [AiInsightController::class, 'index'])->name('index');
                Route::post('generate-narrative', [AiInsightController::class, 'generateNarrative'])->name('generate-narrative');
                Route::post('{ai_insight}/mark-read', [AiInsightController::class, 'markRead'])->name('mark-read');
            });

            Route::prefix('integrations')->name('integrations.')->middleware('platform.permission:integrations.view')->group(function () {
                Route::get('/', [IntegrationController::class, 'index'])->name('index');
                Route::get('{integration}/logs', [IntegrationController::class, 'logs'])->name('logs');

                Route::middleware('platform.permission:integrations.manage')->group(function () {
                    Route::post('/', [IntegrationController::class, 'store'])->name('store');
                    Route::patch('{integration}', [IntegrationController::class, 'update'])->name('update');
                    Route::post('{integration}/enable', [IntegrationController::class, 'enable'])->name('enable');
                    Route::post('{integration}/disable', [IntegrationController::class, 'disable'])->name('disable');
                    Route::post('{integration}/test', [IntegrationController::class, 'testConnection'])->name('test');
                });
            });
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
        ->name('impersonation.stop');
});
