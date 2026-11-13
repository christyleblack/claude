<?php

use Tests\Support\TestCase;

/**
 * @internal
 */
final class ExampleSessionTest extends TestCase
{
    public function testSessionSimple(): void
    {
        $session = service('session');

        $session->set('logged_in', 123);
        $this->assertSame(123, $session->get('logged_in'));
    }
}
