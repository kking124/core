<?php

use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\DB;

/**
 * Put the theme group back to its pre-branding shape with the given accent.
 */
function seedLegacyAccent(string $accent): void
{
    DB::table('settings')->where('group', 'theme')->whereIn('name', [
        'link_color', 'link_color_dark', 'metrics_fill_color', 'metrics_fill_color_dark',
    ])->delete();

    DB::table('settings')->updateOrInsert(
        ['group' => 'theme', 'name' => 'accent'],
        ['payload' => json_encode($accent), 'locked' => false],
    );
}

function applyThemeMigration(): void
{
    $migration = require __DIR__.'/../../../database/migrations/2026_10_27_000001_add_status_page_theme_settings.php';

    $migration->up();
}

function themePayload(string $name): mixed
{
    return json_decode(DB::table('settings')->where('group', 'theme')->where('name', $name)->value('payload'));
}

it('carries a named accent over to the link and metrics colors', function () {
    seedLegacyAccent('blue');

    applyThemeMigration();

    expect(themePayload('metrics_fill_color'))->toBe(Color::convertToHex(Color::Blue[500]))
        ->and(themePayload('link_color'))->toBe(Color::convertToHex(Color::Blue[600]))
        ->and(themePayload('metrics_fill_color_dark'))->toBe(Color::convertToHex(Color::Blue[500]))
        ->and(themePayload('link_color_dark'))->toBe(Color::convertToHex(Color::Blue[400]))
        ->and(DB::table('settings')->where('group', 'theme')->where('name', 'accent')->exists())->toBeFalse();
});

it('leaves the default accent without overrides', function () {
    seedLegacyAccent('cachet');

    applyThemeMigration();

    expect(themePayload('link_color'))->toBeNull()
        ->and(themePayload('metrics_fill_color'))->toBeNull();
});
