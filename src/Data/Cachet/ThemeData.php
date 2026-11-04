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
    public const GRAYS = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'mauve', 'olive', 'mist', 'taupe'];

    private const DEFAULT_PAIRING = 'zinc';

    private const THEME_PAIRINGS = [
        'cachet' => 'zinc',
        'red' => 'zinc',
        'orange' => 'neutral',
        'amber' => 'neutral',
        'yellow' => 'stone',
        'lime' => 'zinc',
        'green' => 'zinc',
        'emerald' => 'zinc',
        'teal' => 'gray',
        'cyan' => 'gray',
        'sky' => 'gray',
        'blue' => 'slate',
        'indigo' => 'slate',
        'violet' => 'gray',
        'purple' => 'gray',
        'fuchsia' => 'zinc',
        'pink' => 'zinc',
        'rose' => 'zinc',
    ];

    private const STATUS_FALLBACKS = [
        'success' => Color::Green[600],
        'info' => Color::Blue[600],
        'alert' => Color::Amber[600],
        'error' => Color::Red[600],
        'notice' => Color::Orange[600],
    ];

    /**
     * Palette shades of each named branding color: [light metrics, light link, dark metrics, dark link].
     */
    private const BRANDING_SHADES = [
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

    /**
     * Brand colors, light and dark, for each named branding color.
     *
     * @return array<string, array<string, string>>
     */
    public static function brandingPresets(): array
    {
        $cachet = FilamentColor::getColors()['cachet'];
        $presets = ['cachet' => [$cachet[500], $cachet[500], $cachet[500], $cachet[400]]];

        foreach (self::BRANDING_SHADES as $name => $shades) {
            $palette = Color::all()[$name];
            $presets[$name] = array_map(fn (int $shade): string => $palette[$shade], $shades);
        }

        return array_map(fn (array $colors): array => array_combine(
            ['metrics_fill_color', 'link_color', 'metrics_fill_color_dark', 'link_color_dark'],
            array_map(fn (string $color): string => strtolower(self::toHex($color)), $colors),
        ), $presets);
    }

    /**
     * Page background colors, light and dark, for each named base color.
     *
     * @return array<string, array<string, string>>
     */
    public static function brandingBases(): array
    {
        $bases = [];

        foreach (self::GRAYS as $gray) {
            $bases[$gray] = [
                'background_color' => strtolower(self::toHex(Color::all()[$gray][50])),
                'background_color_dark' => strtolower(self::toHex(Color::all()[$gray][900])),
            ];
        }

        return $bases;
    }

    /**
     * The named branding color whose brand colors equal the given ones, if any.
     *
     * @param  array<string, string|null>  $brandColors
     */
    public static function matchBranding(array $brandColors): ?string
    {
        return self::matchPreset(self::brandingPresets(), $brandColors);
    }

    /**
     * The named base color whose background colors equal the given ones, if any.
     *
     * @param  array<string, string|null>  $backgroundColors
     */
    public static function matchBase(array $backgroundColors): ?string
    {
        return self::matchPreset(self::brandingBases(), $backgroundColors);
    }

    /**
     * Match the base color paired with the given branding color, falling back
     * to a neutral gray for colors Cachet has no explicit pairing for.
     */
    public static function matchPairing(?string $branding): string
    {
        return self::THEME_PAIRINGS[$branding] ?? self::DEFAULT_PAIRING;
    }

    /**
     * @param  array<string, array<string, string>>  $presets
     * @param  array<string, string|null>  $colors
     */
    private static function matchPreset(array $presets, array $colors): ?string
    {
        $colors = array_map(fn ($color) => is_string($color) ? strtolower($color) : $color, $colors);

        foreach ($presets as $name => $preset) {
            $candidate = array_intersect_key($colors, $preset);
            ksort($candidate);
            ksort($preset);

            if ($candidate === $preset) {
                return $name;
            }
        }

        return null;
    }

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

    /** @var array<string, string> */
    private array $pristineDefaults = [];

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
            $this->pristineDefaults[$setting] = $this->toHex($colors['light']);
            $this->pristineDefaults[$setting.'_dark'] = $this->toHex($colors['dark']);
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

    private static function toHex(string $color): string
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
     * Every color's built-in default, ignoring what is stored.
     *
     * @return array<string, string>
     */
    public function pristineDefaults(): array
    {
        return $this->pristineDefaults;
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
