<?php

use PHPUnit\Framework\TestCase;

class IntegrationsNavigationFeatureTest extends TestCase
{
    public function testSettingsMenuContainsIntegrationsLink()
    {
        $navbar = file_get_contents(__DIR__ . '/../../application/modules/layout/views/includes/navbar.php');

        $this->assertStringContainsString("integrations/index", $navbar);
    }
}
