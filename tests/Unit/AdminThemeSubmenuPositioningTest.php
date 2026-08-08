<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminThemeSubmenuPositioningTest extends TestCase
{
    public function test_flyout_coordinates_are_applied_directly_to_the_visible_panels(): void
    {
        $themePath = __DIR__.'/../../modules/admin-theme/resources/css/admin-theme.css';

        if (! is_file($themePath)) {
            self::markTestSkipped('The optional admin theme plugin is not installed.');
        }

        $navigation = file_get_contents(__DIR__.'/../../resources/views/layouts/navigation.blade.php');
        $theme = file_get_contents($themePath);

        self::assertIsString($navigation);
        self::assertIsString($theme);
        self::assertStringContainsString("panel.style.setProperty('left', panelLeft + 'px', 'important')", $navigation);
        self::assertStringContainsString("panel.style.setProperty('top', panelTop + 'px', 'important')", $navigation);
        self::assertStringContainsString('const panelTop = Math.max(42, Math.min(groupRect.top', $navigation);
        self::assertStringContainsString('const visiblePanelRect = panel.getBoundingClientRect()', $navigation);
        self::assertStringContainsString('visiblePanelRect.height || panel.scrollHeight', $navigation);
        self::assertStringContainsString('@focusin="if (openSubmenu', $navigation);
        self::assertStringNotContainsString('--z4-submenu-flyout-top', $theme);
        self::assertStringNotContainsString('--z4-submenu-flyout-left', $theme);
        self::assertStringContainsString('html body .dashboard-sidebar button:hover {', $theme);
        self::assertDoesNotMatchRegularExpression('/html body\\s*\\{[^}]*transform:\\s*translateX/s', $theme);
    }
}
