<?php

namespace Tests\Unit\Data;

use Cachet\Data\Cachet\ThemeData;
use Cachet\Settings\ThemeSettings;
use Filament\Support\Colors\Color;

it('compiles custom light and dark status page colors', function () {
    $settings = app(ThemeSettings::class)->fill([
        'background_color' => '#101820',
        'text_color' => '#112233',
        'text_color_dark' => '#d4d4d8',
        'alert_color_dark' => '#abcdef',
    ]);

    $styles = (new ThemeData($settings))->styles;
    [$light, $dark] = explode(':root[data-theme=dark]', $styles);

    expect($light)->toContain('--page-background-color: #101820', '--page-text-color: #112233')
        ->and($dark)->toContain('--page-text-color: #d4d4d8', '--alert-color: #abcdef')
        ->and($styles)->not->toContain('@media(prefers-color-scheme: dark)');
});

it('uses mode-specific defaults and keeps custom light surfaces out of dark mode', function () {
    $settings = app(ThemeSettings::class)->fill([
        'background_color' => '#101820',
        'success_color' => '#123456',
    ]);

    $styles = (new ThemeData($settings))->styles;
    [, $dark] = explode(':root[data-theme=dark]', $styles);

    expect($dark)->toContain('--page-background-color: '.Color::Zinc[900])
        ->and($dark)->toContain('--alert-color: '.Color::Amber[600])
        ->and($dark)->toContain('--success-color: #123456')
        ->and($dark)->toContain('--metrics-fill-color: '.Color::generateV3Palette('rgb(4, 193, 71)')[500]);
});

it('rejects invalid custom colors and exposes picker defaults', function () {
    $settings = app(ThemeSettings::class)->fill([
        'background_color' => 'red; } body { display: none',
    ]);

    $theme = new ThemeData($settings);

    expect($theme->styles)->not->toContain('display: none')
        ->and($theme->formDefaults())->toMatchArray([
            'text_color' => '#3f3f46',
            'text_color_dark' => '#d4d4d8',
        ]);
});
