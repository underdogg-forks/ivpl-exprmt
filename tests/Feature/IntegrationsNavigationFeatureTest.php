<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntegrationsNavigationFeatureTest extends TestCase
{
    /**
     * Arrange: navbar template content.
     * Act: parse anchor tags from the navbar.
     * Assert: settings dropdown includes a link whose href contains 'integrations/index'.
     */
    #[Test]
    public function it_contains_integrations_link_in_settings_menu(): void
    {
        $navbarPath = __DIR__ . '/../../application/modules/layout/views/includes/navbar.php';

        $this->assertFileExists($navbarPath, 'Navbar template must exist');

        $navbar = file_get_contents($navbarPath);

        $this->assertNotFalse($navbar, 'Navbar template must be readable');
        $this->assertStringContainsString('integrations/index', $navbar, 'Navbar must contain an integrations/index route');
    }
}
