<?php

namespace HttpBeacon\Tests\Unit;

use BeaconCallerFixture\Runner;
use HttpBeacon\Support\Caller;
use HttpBeacon\Tests\TestCase;

class CallerTest extends TestCase
{
    public function test_finds_user_frame_with_class_method_format(): void
    {
        // The package and vendor directories are both filtered, so to capture
        // a "user-code" frame we have to invoke Caller::find from a file that
        // lives outside of those paths. Write a temp file under the system
        // tmp dir and require it.
        $tmp = sys_get_temp_dir().'/beacon_caller_'.uniqid().'.php';
        file_put_contents($tmp, <<<'PHP'
            <?php

            namespace BeaconCallerFixture;

            class Runner
            {
                public function trigger(): ?string
                {
                    return \HttpBeacon\Support\Caller::find();
                }
            }
            PHP);

        require $tmp;
        $caller = (new Runner)->trigger();

        @unlink($tmp);

        $this->assertNotNull($caller);
        $this->assertMatchesRegularExpression('/^BeaconCallerFixture\\\\Runner@trigger:\d+$/', $caller);
    }

    public function test_returns_null_when_no_user_frame_exists(): void
    {
        // From inside the package's own tests, every frame is either in vendor/
        // (PHPUnit) or in the package path (this test file). Both are filtered,
        // so find() must return null.
        $this->assertNull(Caller::find());
    }
}
