<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntegrationsNavigationFeatureTest extends TestCase
{
    /**
     * Arrange: navbar template content.
     * Act: search for integrations route.
     * Assert: settings dropdown includes integrations link.
     */
    #[Test]
    public function it_contains_integrations_link_in_settings_menu()
    {
        $navbar = file_get_contents(__DIR__ . '/../../application/modules/layout/views/includes/navbar.php');

        $this->assertStringContainsString('integrations/index', $navbar);
    }
}
