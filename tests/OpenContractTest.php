<?php

namespace Glubschi\Seat\Contracts\Tests;

use Glubschi\Seat\Contracts\Jobs\OpenContract;
use Seat\Eveapi\Models\RefreshToken;

/** The job must call ESI's open window route as the ESI spec describes it. */
class OpenContractTest extends TestCase
{
    public function test_posts_to_the_contract_window_route_with_the_ui_scope(): void
    {
        $token = new RefreshToken();
        $token->character_id = 314354929;
        $job = new OpenContract($token, 229876543);

        $this->assertSame('/ui/openwindow/contract/', $job->getEndpoint());
        $this->assertSame('esi-ui.open_window.v1', $job->getScope());
        $this->assertSame(229876543, $job->contractId());
        $this->assertSame('post', (new \ReflectionProperty($job, 'method'))->getValue($job));
        $this->assertSame(['contract_id' => 229876543], (new \ReflectionProperty($job, 'query_string'))->getValue($job));
    }
}
