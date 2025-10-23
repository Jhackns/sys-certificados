<?php

namespace Tests\Feature\Services;

use App\Models\CertificateTemplate;
use App\Services\CertificateTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CertificateTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_returns_paginator_with_items(): void
    {
        CertificateTemplate::factory()->count(3)->create();

        $service = new CertificateTemplateService();
        $result = $service->getAll(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
    }

    public function test_search_by_status_filters_results(): void
    {
        CertificateTemplate::factory()->count(2)->active()->create();
        CertificateTemplate::factory()->count(1)->inactive()->create();

        $service = new CertificateTemplateService();
        $result = $service->search(['status' => 'active'], 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
    }
}