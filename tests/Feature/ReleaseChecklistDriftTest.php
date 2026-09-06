<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Prompt 024/026 (150-prompt deploy-hardening program): docs/release-checklist.json
 * is meant to be read by an operator or a future tool as the authoritative,
 * ordered list of deploy.sh's fail-closed gates. A checklist nobody keeps
 * honest is worse than no checklist — this asserts every listed marker is
 * still literally present in deploy.sh, and still appears in the exact
 * order the checklist claims, so the two can never silently diverge.
 */
class ReleaseChecklistDriftTest extends TestCase
{
    public function test_every_gate_marker_is_present_in_deploy_sh_in_the_declared_order(): void
    {
        $checklist = $this->checklist();
        $script = file_get_contents(base_path('deploy.sh'));
        $this->assertIsString($script);

        $lastPosition = -1;

        foreach ($checklist['gates'] as $gate) {
            $position = strpos($script, $gate['marker']);

            $this->assertNotFalse(
                $position,
                "Checklist gate '{$gate['id']}' expects marker not found in deploy.sh: {$gate['marker']}"
            );

            $this->assertGreaterThan(
                $lastPosition,
                $position,
                "Checklist gate '{$gate['id']}' is declared out of order relative to deploy.sh's real gate sequence."
            );

            $lastPosition = $position;
        }
    }

    public function test_post_gate_actions_run_strictly_after_every_gate(): void
    {
        $checklist = $this->checklist();
        $script = file_get_contents(base_path('deploy.sh'));
        $this->assertIsString($script);

        $lastGatePosition = -1;

        foreach ($checklist['gates'] as $gate) {
            $lastGatePosition = max($lastGatePosition, strpos($script, $gate['marker']));
        }

        foreach ($checklist['post_gate_actions'] as $action) {
            $position = strpos($script, $action['marker']);

            $this->assertNotFalse($position, "Post-gate action '{$action['id']}' marker not found in deploy.sh.");
            $this->assertGreaterThan(
                $lastGatePosition,
                $position,
                "Post-gate action '{$action['id']}' must run after every gate, never before or interleaved."
            );
        }
    }

    public function test_every_gate_has_a_unique_id_and_a_non_empty_description(): void
    {
        $checklist = $this->checklist();
        $ids = [];

        foreach ([...$checklist['gates'], ...$checklist['post_gate_actions']] as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('description', $entry);
            $this->assertNotSame('', trim($entry['description']));
            $this->assertArrayNotHasKey($entry['id'], $ids, "Duplicate checklist id: {$entry['id']}");
            $ids[$entry['id']] = true;
        }
    }

    /** @return array{gates: list<array{id:string,description:string,marker:string,failure_is:string}>, post_gate_actions: list<array{id:string,description:string,marker:string}>} */
    private function checklist(): array
    {
        $raw = file_get_contents(base_path('docs/release-checklist.json'));
        $this->assertIsString($raw);

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('gates', $decoded);
        $this->assertArrayHasKey('post_gate_actions', $decoded);

        return $decoded;
    }
}
