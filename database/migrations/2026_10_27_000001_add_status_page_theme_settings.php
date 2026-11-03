<?php

use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Palette shades of the old named accents: [light accent, light content, dark accent, dark content].
     * Grays used white in dark mode.
     */
    private const ACCENT_SHADES = [
        'gray' => [800, 800, 'white', 'white'],
        'zinc' => [800, 800, 'white', 'white'],
        'neutral' => [800, 800, 'white', 'white'],
        'stone' => [800, 800, 'white', 'white'],
        'red' => [500, 600, 500, 400],
        'orange' => [500, 600, 400, 400],
        'amber' => [400, 600, 400, 400],
        'yellow' => [400, 600, 400, 400],
        'lime' => [400, 600, 400, 400],
        'green' => [600, 600, 600, 400],
        'emerald' => [600, 600, 600, 400],
        'teal' => [600, 600, 600, 400],
        'cyan' => [600, 600, 600, 400],
        'sky' => [600, 600, 600, 400],
        'blue' => [500, 600, 500, 400],
        'indigo' => [500, 600, 500, 300],
        'violet' => [500, 600, 500, 400],
        'purple' => [500, 600, 500, 300],
        'fuchsia' => [600, 600, 600, 400],
        'pink' => [600, 600, 600, 400],
        'rose' => [500, 500, 500, 400],
    ];

    public function up(): void
    {
        $accentColors = $this->accentColors();

        foreach ([
            'background_color',
            'text_color',
            'banner_background_color',
            'background_fill_color',
            'error_color',
            'info_color',
            'success_color',
            'alert_color',
            'notice_color',
            'metrics_fill_color',
            'link_color',
            'banner_padding',
            'background_color_dark',
            'text_color_dark',
            'banner_background_color_dark',
            'background_fill_color_dark',
            'error_color_dark',
            'info_color_dark',
            'success_color_dark',
            'alert_color_dark',
            'notice_color_dark',
            'metrics_fill_color_dark',
            'link_color_dark',
        ] as $setting) {
            rescue(fn () => $this->migrator->add("theme.{$setting}", $accentColors[$setting] ?? null));
        }

        foreach (['accent', 'accent_content', 'accent_pairing'] as $setting) {
            rescue(fn () => $this->migrator->delete("theme.{$setting}"));
        }
    }

    /**
     * Map the old accent to the metrics fill and link colors that replace it.
     * The default and unknown accents need no override.
     *
     * @return array<string, string>
     */
    private function accentColors(): array
    {
        $payload = rescue(fn () => DB::table('settings')
            ->where('group', 'theme')
            ->where('name', 'accent')
            ->value('payload'));
        $accent = json_decode((string) $payload);
        $shades = is_string($accent) ? (self::ACCENT_SHADES[$accent] ?? null) : null;

        if ($shades === null) {
            return [];
        }

        $palette = Color::all()[$accent];
        [$lightAccent, $lightContent, $darkAccent, $darkContent] = array_map(
            fn ($shade) => Color::convertToHex($shade === 'white' ? 'oklch(1 0 0)' : $palette[$shade]),
            $shades,
        );

        return [
            'metrics_fill_color' => $lightAccent,
            'link_color' => $lightContent,
            'metrics_fill_color_dark' => $darkAccent,
            'link_color_dark' => $darkContent,
        ];
    }
};
