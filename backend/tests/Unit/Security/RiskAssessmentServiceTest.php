<?php

namespace Tests\Unit\Security;

use App\Domain\Security\Services\RiskAssessmentService;
use App\Domain\Shared\Models\AuditLog;
use Tests\TestCase;

class RiskAssessmentServiceTest extends TestCase
{
    private RiskAssessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RiskAssessmentService();
    }

    public function test_ordinary_create_in_a_non_sensitive_module_is_normal(): void
    {
        $risk = $this->service->assess('created', 'App\\Domain\\Inventory\\Models\\Product', null);

        $this->assertSame(AuditLog::RISK_NORMAL, $risk);
    }

    public function test_delete_in_a_sensitive_module_is_high(): void
    {
        $risk = $this->service->assess('deleted', 'App\\Domain\\Finance\\Models\\PaymentTransaction', null);

        $this->assertSame(AuditLog::RISK_HIGH, $risk);
    }

    public function test_update_in_a_sensitive_module_is_elevated(): void
    {
        $risk = $this->service->assess('updated', 'App\\Domain\\Security\\Models\\BlockedIp', null);

        $this->assertSame(AuditLog::RISK_ELEVATED, $risk);
    }

    public function test_any_role_named_class_is_at_least_elevated_regardless_of_module(): void
    {
        $risk = $this->service->assess('updated', 'App\\Domain\\WebsiteTemplates\\Models\\RoleTemplate', null);

        $this->assertSame(AuditLog::RISK_ELEVATED, $risk);
    }

    public function test_ordinary_update_in_a_non_sensitive_module_is_normal(): void
    {
        $risk = $this->service->assess('updated', 'App\\Domain\\Support\\Models\\KnowledgeBaseArticle', null);

        $this->assertSame(AuditLog::RISK_NORMAL, $risk);
    }
}
