<?php

namespace Tests\Feature;

use App\Services\Module\DependencyValidator;
use Tests\TestCase;

class DependencyValidatorTest extends TestCase
{
    private DependencyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DependencyValidator();
    }

    public function test_validate_passes_with_no_dependencies(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => [], 'conflicts' => []];
        $active = ['other' => ['name' => 'Other', 'version' => '2.0.0']];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertEmpty($errors);
    }

    public function test_validate_passes_when_dependency_is_active_and_satisfies_constraint(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => ['example' => '>=1.0.0']];
        $active = ['example' => ['name' => 'Example', 'version' => '1.2.0']];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertEmpty($errors);
    }

    public function test_validate_fails_when_required_module_is_missing(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => ['example' => '>=1.0.0']];
        $active = [];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('requires_missing:example', $errors[0]);
    }

    public function test_validate_fails_when_version_does_not_satisfy_constraint(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => ['example' => '>=2.0.0']];
        $active = ['example' => ['name' => 'Example', 'version' => '1.5.0']];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('requires_version:example', $errors[0]);
    }

    public function test_validate_detects_conflict_with_active_module(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => [], 'conflicts' => ['example']];
        $active = ['example' => ['name' => 'Example', 'version' => '1.0.0']];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('conflict:example', $errors[0]);
    }

    public function test_validate_passes_when_conflict_module_is_not_active(): void
    {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'requires' => [], 'conflicts' => ['other']];
        $active = ['example' => ['name' => 'Example', 'version' => '1.0.0']];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertEmpty($errors);
    }

    public function test_validate_reports_multiple_errors(): void
    {
        $manifest = [
            'name' => 'test',
            'version' => '1.0.0',
            'requires' => ['missing' => '>=1.0', 'outdated' => '>=3.0'],
            'conflicts' => ['enemy'],
        ];
        $active = [
            'outdated' => ['name' => 'Outdated', 'version' => '2.0.0'],
            'enemy' => ['name' => 'Enemy', 'version' => '1.0.0'],
        ];

        $errors = $this->validator->validate($manifest, $active);

        $this->assertCount(3, $errors);
    }

    public function test_reverse_dependencies_finds_dependant_modules(): void
    {
        $allModules = [
            'base' => ['name' => 'Base', 'version' => '1.0.0'],
            'child' => ['name' => 'Child', 'version' => '1.0.0', 'requires' => ['base' => '>=1.0']],
            'unrelated' => ['name' => 'Unrelated', 'version' => '1.0.0'],
        ];
        $active = $allModules;

        $dependants = $this->validator->checkReverseDependencies('base', $allModules, $active);

        $this->assertEquals(['child'], $dependants);
    }

    public function test_reverse_dependencies_returns_empty_when_no_dependants(): void
    {
        $allModules = [
            'base' => ['name' => 'Base', 'version' => '1.0.0'],
            'other' => ['name' => 'Other', 'version' => '1.0.0'],
        ];
        $active = $allModules;

        $dependants = $this->validator->checkReverseDependencies('base', $allModules, $active);

        $this->assertEmpty($dependants);
    }

    public function test_satisfies_constraint_with_wildcard(): void
    {
        $this->assertTrue($this->validator->satisfiesConstraint('0.1.0', '*'));
        $this->assertTrue($this->validator->satisfiesConstraint('5.0.0', '*'));
    }

    public function test_satisfies_constraint_with_gte(): void
    {
        $this->assertTrue($this->validator->satisfiesConstraint('1.0.0', '>=1.0.0'));
        $this->assertTrue($this->validator->satisfiesConstraint('2.0.0', '>=1.0.0'));
        $this->assertFalse($this->validator->satisfiesConstraint('0.9.0', '>=1.0.0'));
    }

    public function test_satisfies_constraint_with_caret(): void
    {
        $this->assertTrue($this->validator->satisfiesConstraint('1.0.0', '^1.0'));
        $this->assertTrue($this->validator->satisfiesConstraint('1.9.9', '^1.0'));
        $this->assertFalse($this->validator->satisfiesConstraint('2.0.0', '^1.0'));
        $this->assertFalse($this->validator->satisfiesConstraint('0.9.0', '^1.0'));
    }

    public function test_satisfies_constraint_with_tilde(): void
    {
        $this->assertTrue($this->validator->satisfiesConstraint('1.2.0', '~1.2'));
        $this->assertTrue($this->validator->satisfiesConstraint('1.2.9', '~1.2'));
        $this->assertFalse($this->validator->satisfiesConstraint('1.3.0', '~1.2'));
        $this->assertFalse($this->validator->satisfiesConstraint('1.1.0', '~1.2'));
    }

    public function test_satisfies_constraint_with_bare_version(): void
    {
        $this->assertTrue($this->validator->satisfiesConstraint('1.0.0', '1.0.0'));
        $this->assertTrue($this->validator->satisfiesConstraint('2.0.0', '1.0.0'));
        $this->assertFalse($this->validator->satisfiesConstraint('0.9.0', '1.0.0'));
    }

    public function test_circular_dependency_detected_simple(): void
    {
        $allModules = [
            'b' => ['name' => 'B', 'version' => '1.0.0', 'requires' => ['a' => '>=1.0']],
        ];
        $manifest = ['name' => 'A', 'version' => '1.0.0', 'requires' => ['b' => '>=1.0']];

        $cycle = $this->validator->detectCircularDependencies('a', $manifest, $allModules);

        $this->assertNotNull($cycle);
        $this->assertContains('a', $cycle);
        $this->assertContains('b', $cycle);
    }

    public function test_no_circular_dependency_in_clean_graph(): void
    {
        $allModules = [
            'b' => ['name' => 'B', 'version' => '1.0.0'],
            'c' => ['name' => 'C', 'version' => '1.0.0', 'requires' => ['b' => '>=1.0']],
        ];
        $manifest = ['name' => 'A', 'version' => '1.0.0', 'requires' => ['c' => '>=1.0']];

        $cycle = $this->validator->detectCircularDependencies('a', $manifest, $allModules);

        $this->assertNull($cycle);
    }

    public function test_deep_circular_dependency_detected(): void
    {
        $allModules = [
            'b' => ['name' => 'B', 'version' => '1.0.0', 'requires' => ['c' => '>=1.0']],
            'c' => ['name' => 'C', 'version' => '1.0.0', 'requires' => ['a' => '>=1.0']],
        ];
        $manifest = ['name' => 'A', 'version' => '1.0.0', 'requires' => ['b' => '>=1.0']];

        $cycle = $this->validator->detectCircularDependencies('a', $manifest, $allModules);

        $this->assertNotNull($cycle);
        $this->assertContains('a', $cycle);
        $this->assertContains('b', $cycle);
        $this->assertContains('c', $cycle);
    }

    public function test_self_dependency_detected(): void
    {
        $allModules = [];
        $manifest = ['name' => 'A', 'version' => '1.0.0', 'requires' => ['a' => '>=1.0']];

        $cycle = $this->validator->detectCircularDependencies('a', $manifest, $allModules);

        $this->assertNotNull($cycle);
        $this->assertContains('a', $cycle);
    }
}
