<?php

namespace Cachet\Data\Cachet;

use Cachet\Data\BaseData;
use Cachet\Settings\ThemeSettings;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\Validation\Required;

final class ThemeData extends BaseData
{
    private const STATUS_FALLBACKS = [
        'success' => Color::Green[600],
        'info' => Color::Blue[600],
        'alert' => Color::Amber[600],
        'error' => Color::Red[600],
        'notice' => Color::Orange[600],
    ];

    /**
     * Palette built from a configurable status color (success, info, alert, error, notice).
     *
     * @return array<int, string>
     */
    public static function statusPalette(string $status): array
    {
        $configured = app(ThemeSettings::class)->{$status.'_color'};

        return Color::generateV3Palette($configured ?: self::STATUS_FALLBACKS[$status]);
    }

    private const CSS_NAMES = [
        'background_color' => 'page-background-color',
        'text_color' => 'page-text-color',
    ];

    private const SURFACE_COLORS = [
        'background_color',
        'text_color',
        'banner_background_color',
        'background_fill_color',
    ];

    #[Computed]
    public string $styles;

    /** @var array<string, string> */
    private array $lightColors = [];

    /** @var array<string, array{light: string, dark: string}> */
    private array $formDefaults = [];

    public function __construct(
        #[Required]
        public readonly ThemeSettings $themeSettings,
    ) {
        $this->generate();
    }

    /**
     * Generate the theme from the given settings.
     */
    protected function generate(): void
    {
        $primaryColor = FilamentColor::getColors()['cachet'];

        $theme = [
            'light' => [
                'accent' => $primaryColor[500],
                'accent-content' => $primaryColor[500],
                'accent-foreground' => 'oklch(1 0 0)',
            ],
            'dark' => [
                'accent' => $primaryColor[500],
                'accent-content' => $primaryColor[400],
                'accent-foreground' => $primaryColor[950],
            ],
        ];

        $this->styles = $this->compileCss($theme);
    }

    /**
     * Compile the CSS from the theme.
     */
    protected function compileCss(array $theme): string
    {
        $pairingColor = Color::Zinc;
        $defaults = $this->defaultColors($theme, $pairingColor);
        $lightVariables = [
            '--accent' => $theme['light']['accent'],
            '--accent-content' => $theme['light']['accent-content'],
            '--accent-foreground' => $theme['light']['accent-foreground'],
            '--accent-background' => $pairingColor[50],
            '--banner-padding' => ($this->themeSettings->banner_padding ?? 16).'px',
        ];
        $darkVariables = [
            '--accent' => $theme['dark']['accent'],
            '--accent-content' => $theme['dark']['accent-content'],
            '--accent-foreground' => $theme['dark']['accent-foreground'],
            '--accent-background' => $pairingColor[900],
        ];

        foreach ($defaults as $setting => $colors) {
            $cssName = self::CSS_NAMES[$setting] ?? str_replace('_', '-', $setting);
            $lightValue = $this->themeSettings->{$setting};
            $lightVariables['--'.$cssName] = $this->color($lightValue, $colors['light']);
            $darkSetting = $setting.'_dark';
            $darkFallback = in_array($setting, self::SURFACE_COLORS, true)
                ? $colors['dark']
                : $this->color($lightValue, $colors['dark']);
            $darkVariables['--'.$cssName] = $this->color($this->themeSettings->{$darkSetting}, $darkFallback);
            $this->formDefaults[$setting] = [
                'light' => $this->toHex($colors['light']),
                'dark' => $this->toHex($darkFallback),
            ];
        }

        foreach (['--accent', '--accent-content'] as $variable) {
            $lightVariables[$variable] = $lightVariables['--link-color'];
            $darkVariables[$variable] = $darkVariables['--link-color'];
        }

        $this->lightColors = [
            'accent' => $lightVariables['--accent'],
            'accent-content' => $lightVariables['--accent-content'],
            'accent-foreground' => $theme['light']['accent-foreground'],
            'accent-background' => $pairingColor[50],
        ];

        return ':root {'."\n".$this->formatVariables($lightVariables)."\n}\n\n"
            .':root[data-theme=dark] {'."\n".$this->formatVariables($darkVariables)."\n}";
    }

    /**
     * @param  array<string, array{accent: string, accent-content: string, accent-foreground: string}>  $theme
     * @param  array<int, string>  $pairingColor
     * @return array<string, array{light: string, dark: string}>
     */
    private function defaultColors(array $theme, array $pairingColor): array
    {
        return [
            'background_color' => ['light' => $pairingColor[50], 'dark' => $pairingColor[900]],
            'text_color' => ['light' => Color::Zinc[700], 'dark' => Color::Zinc[300]],
            'banner_background_color' => ['light' => 'rgb(255 255 255 / 70%)', 'dark' => 'rgb(24 24 27 / 66%)'],
            'background_fill_color' => ['light' => 'rgb(255 255 255 / 92%)', 'dark' => 'rgb(24 24 27 / 76%)'],
            'error_color' => ['light' => Color::Red[600], 'dark' => Color::Red[600]],
            'info_color' => ['light' => Color::Blue[600], 'dark' => Color::Blue[600]],
            'success_color' => ['light' => Color::Green[600], 'dark' => Color::Green[600]],
            'alert_color' => ['light' => Color::Amber[600], 'dark' => Color::Amber[600]],
            'notice_color' => ['light' => Color::Orange[600], 'dark' => Color::Orange[600]],
            'metrics_fill_color' => ['light' => $theme['light']['accent'], 'dark' => $theme['dark']['accent']],
            'link_color' => ['light' => $theme['light']['accent-content'], 'dark' => $theme['dark']['accent-content']],
        ];
    }

    private function color(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value)
            ? $value
            : $fallback;
    }

    private function toHex(string $color): string
    {
        if (preg_match('/^rgba?\(\s*(\d+)\s+(\d+)\s+(\d+)(?:\s*\/\s*([\d.]+%?))?\s*\)$/i', $color, $matches)) {
            $color = sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
        }

        return Color::convertToHex($color);
    }

    /** @param array<string, string> $variables */
    private function formatVariables(array $variables): string
    {
        $css = '';

        foreach ($variables as $name => $value) {
            $css .= "    {$name}: {$value};\n";
        }

        return rtrim($css);
    }

    /**
     * The resolved light-mode theme colors, keyed by CSS variable name.
     *
     * @return array<string, string>
     */
    public function lightColors(): array
    {
        return $this->lightColors;
    }

    /**
     * @return array<string, string>
     */
    public function formDefaults(): array
    {
        $defaults = [];

        foreach ($this->formDefaults as $setting => $colors) {
            $defaults[$setting] = $colors['light'];
            $defaults[$setting.'_dark'] = $colors['dark'];
        }

        return $defaults;
    }
}
