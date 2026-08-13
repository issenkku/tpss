<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FilterComponentsTest extends TestCase
{
    public function test_async_filter_component_renders_a_reusable_scope(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-async-filter scope="example-results" class="example-page">
                <p>ผลลัพธ์</p>
            </x-async-filter>
        BLADE);

        $this->assertStringContainsString('data-async-filter-scope="example-results"', $html);
        $this->assertStringContainsString('class="example-page"', $html);
        $this->assertStringContainsString('<p>ผลลัพธ์</p>', $html);
    }

    public function test_filter_select_component_connects_label_and_async_behavior(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-filter-select
                id="academic-year"
                name="academic_year_id"
                label="ปีการศึกษา"
                reset="term_sequence"
                data-testid="year-filter">
                <option value="1">2569</option>
            </x-filter-select>
        BLADE);

        $this->assertStringContainsString('for="academic-year"', $html);
        $this->assertStringContainsString('id="academic-year"', $html);
        $this->assertStringContainsString('name="academic_year_id"', $html);
        $this->assertStringContainsString('data-async-filter-change', $html);
        $this->assertStringContainsString('data-async-filter-reset="term_sequence"', $html);
        $this->assertStringContainsString('class="filter-select-control"', $html);
        $this->assertStringContainsString('data-testid="year-filter"', $html);
    }
}
