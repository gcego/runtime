<?php

declare(strict_types=1);

use Gcego\Runtime\Diagnostics\RuntimeInspector;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Security\GateExecutionAuthorizer;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Gcego\Runtime\Tests\Fixtures\CountingActionHandler;

it('reports configuration, framework state, categories, authorizer and middleware', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');

    $report = app(RuntimeInspector::class)->inspect();

    expect($report->isHealthy())->toBeTrue()
        ->and($report->check('execution.framework')[0]->level)->toBe('ok')
        ->and(array_column(array_map(fn ($diagnostic): array => $diagnostic->toArray(), $report->check('execution.categories')), 'level', 'message'))->toBe([
            '"action" operations have a handler.' => 'ok',
            '"query" operations have no handler yet (Gcego\Runtime\Contracts\QueryHandler).' => 'warning',
            '"form" operations have no handler yet (Gcego\Runtime\Contracts\FormSubmissionHandler).' => 'warning',
        ])
        ->and($report->check('execution.authorizer')[0]->message)->toContain(GateExecutionAuthorizer::class)
        ->and($report->handlers)->toBe([['type' => 'action', 'name' => 'archive', 'handler' => ArchiveHandler::class]])
        ->and($report->toArray())->toHaveKeys(['healthy', 'handlers', 'diagnostics']);
});

it('warns about handler overrides', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');
    app(HandlerRegistry::class)->register(Operation::Action, CountingActionHandler::class, name: 'archive', override: true);

    expect(app(RuntimeInspector::class)->inspect()->check('execution.override')[0]->details['previous'])->toBe(ArchiveHandler::class);
});

it('reports an unbooted framework as an error', function (): void {
    $this->rebuildApplication(['gcego.enabled' => false]);

    expect(app(RuntimeInspector::class)->inspect()->isHealthy())->toBeFalse();
});
