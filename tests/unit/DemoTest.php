<?php
declare( strict_types = 1 );

/**
 * Demo test class for OpenAI Product Feed plugin.
 */
class DemoTest extends WC_Unit_Test_Case {
    public function test_demo() {
        $this->assertTrue( true );
    }

    public function test_plugin_class() {
        $this->assertEquals( 'true', OAPFW\Utils\StringHelper::bool_string( 'yes' ) );
    }
}