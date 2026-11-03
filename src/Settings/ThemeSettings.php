<?php

namespace Cachet\Settings;

use Spatie\LaravelSettings\Settings;

class ThemeSettings extends Settings
{
    public ?string $app_banner;

    public ?string $background_color;

    public ?string $background_color_dark;

    public ?string $text_color;

    public ?string $text_color_dark;

    public ?string $banner_background_color;

    public ?string $banner_background_color_dark;

    public ?string $background_fill_color;

    public ?string $background_fill_color_dark;

    public ?string $error_color;

    public ?string $error_color_dark;

    public ?string $info_color;

    public ?string $info_color_dark;

    public ?string $success_color;

    public ?string $success_color_dark;

    public ?string $alert_color;

    public ?string $alert_color_dark;

    public ?string $notice_color;

    public ?string $notice_color_dark;

    public ?string $metrics_fill_color;

    public ?string $metrics_fill_color_dark;

    public ?string $link_color;

    public ?string $link_color_dark;

    public ?int $banner_padding;

    public static function group(): string
    {
        return 'theme';
    }
}
