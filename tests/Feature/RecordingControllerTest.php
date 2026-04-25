<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Beacon;
use HttpBeacon\Tests\TestCase;

class RecordingControllerTest extends TestCase
{
    public function test_status_pause_and_resume_round_trip(): void
    {
        $this->getJson('/beacon/api/recording')
            ->assertOk()
            ->assertJsonPath('data.recording', true);

        $this->postJson('/beacon/api/recording/pause')
            ->assertOk()
            ->assertJsonPath('data.recording', false);

        $this->assertFalse(Beacon::isRecording());

        $this->postJson('/beacon/api/recording/resume')
            ->assertOk()
            ->assertJsonPath('data.recording', true);

        $this->assertTrue(Beacon::isRecording());
    }
}
