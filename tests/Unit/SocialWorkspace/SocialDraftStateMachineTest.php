<?php

namespace Tests\Unit\SocialWorkspace;

use App\Models\SocialDraft;
use App\Services\SocialWorkspace\SocialDraftStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialDraftStateMachineTest extends TestCase
{
    private function machine(): SocialDraftStateMachine
    {
        return new SocialDraftStateMachine;
    }

    public static function allowedTransitions(): array
    {
        return [
            'draft -> reviewed' => [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_REVIEWED],
            'reviewed -> draft' => [SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_DRAFT],
            'reviewed -> approved' => [SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_APPROVED],
            'approved -> reviewed' => [SocialDraft::STATUS_APPROVED, SocialDraft::STATUS_REVIEWED],
            'approved -> scheduled' => [SocialDraft::STATUS_APPROVED, SocialDraft::STATUS_SCHEDULED],
            'scheduled -> approved' => [SocialDraft::STATUS_SCHEDULED, SocialDraft::STATUS_APPROVED],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transition(string $from, string $to): void
    {
        $this->assertTrue($this->machine()->canTransition($from, $to));
    }

    public static function forbiddenTransitions(): array
    {
        $all = [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_APPROVED, SocialDraft::STATUS_SCHEDULED];
        $protected = [SocialDraft::STATUS_PUBLISHED, SocialDraft::STATUS_FAILED];

        $cases = [];

        foreach ($all as $from) {
            foreach ($protected as $to) {
                $cases["{$from} -> {$to} (protetto)"] = [$from, $to];
            }
        }

        foreach ($protected as $from) {
            foreach ([...$all, ...$protected] as $to) {
                $cases["{$from} -> {$to} (da stato protetto)"] = [$from, $to];
            }
        }

        // Salti non consentiti tra stati editoriali validi.
        $cases['draft -> approved (salto)'] = [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_APPROVED];
        $cases['draft -> scheduled (salto)'] = [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_SCHEDULED];
        $cases['reviewed -> scheduled (salto)'] = [SocialDraft::STATUS_REVIEWED, SocialDraft::STATUS_SCHEDULED];
        $cases['scheduled -> draft (salto)'] = [SocialDraft::STATUS_SCHEDULED, SocialDraft::STATUS_DRAFT];
        $cases['scheduled -> reviewed (salto)'] = [SocialDraft::STATUS_SCHEDULED, SocialDraft::STATUS_REVIEWED];

        return $cases;
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transition(string $from, string $to): void
    {
        $this->assertFalse($this->machine()->canTransition($from, $to));
    }

    public function test_allowed_targets_lists_exactly_the_reachable_states(): void
    {
        $this->assertSame([SocialDraft::STATUS_REVIEWED], $this->machine()->allowedTargets(SocialDraft::STATUS_DRAFT));
        $this->assertSame(
            [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_APPROVED],
            $this->machine()->allowedTargets(SocialDraft::STATUS_REVIEWED)
        );
        $this->assertSame([], $this->machine()->allowedTargets(SocialDraft::STATUS_PUBLISHED));
        $this->assertSame([], $this->machine()->allowedTargets(SocialDraft::STATUS_FAILED));
    }
}
