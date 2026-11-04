<?php

namespace Cachet\Filament\Pages\Settings;

use Cachet\Cachet;
use Cachet\Data\Cachet\ThemeData;
use Cachet\Settings\ThemeSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Support\Arr;

class ManageTheme extends SettingsPage
{
    protected static string $settings = ThemeSettings::class;

    private const COLOR_SETTINGS = [
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
    ];

    private const BRANDING_FIELDS = [
        'link_color',
        'link_color_dark',
        'metrics_fill_color',
        'metrics_fill_color_dark',
    ];

    private const BASE_FIELDS = ['background_color', 'background_color_dark'];

    private const BRANDING_CONTROLS = ['branding_color', 'branding_base', 'branding_pairing', 'customize_branding'];

    public static function canAccess(): bool
    {
        return Cachet::canAccessDashboardFeature('themes');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('cachet::navigation.settings.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('cachet::navigation.settings.items.manage_theme');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make(__('cachet::settings.manage_theme.app_banner_label'))->columns(2)->columnSpan(3)->schema([
                    FileUpload::make('app_banner')
                        ->acceptedFileTypes((array) config('cachet.uploads.image_mime_types'))
                        ->maxSize((int) config('cachet.uploads.max_size'))
                        ->preventFilePathTampering()
                        ->imageEditor()
                        ->label(__('cachet::settings.manage_theme.app_banner_label'))
                        ->hiddenLabel()
                        ->helperText(__('cachet::settings.manage_theme.app_banner_helper'))
                        ->disk((string) config('cachet.uploads.disk'))
                        ->columnSpanFull(),
                    TextInput::make('banner_padding')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(128)
                        ->suffix('px')
                        ->label(__('cachet::settings.manage_theme.banner_padding')),
                ]),

                Section::make(__('cachet::settings.manage_theme.branding_io.heading'))
                    ->description(__('cachet::settings.manage_theme.branding_io.description'))
                    ->columnSpan(1)
                    ->schema([
                        Actions::make([$this->exportAction(), $this->importAction()])
                            ->key('branding-io')
                            ->fullWidth(),
                    ]),

                Section::make(__('cachet::settings.manage_theme.branding.heading'))
                    ->description(__('cachet::settings.manage_theme.branding.description'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('branding_color')
                            ->label(__('cachet::settings.manage_theme.branding.color_label'))
                            ->helperText(__('cachet::settings.manage_theme.branding.color_helper'))
                            ->options(fn (): array => self::swatchOptions(array_map(
                                fn (array $colors): string => $colors['metrics_fill_color'],
                                ThemeData::brandingPresets(),
                            )))
                            ->native(false)
                            ->allowHtml()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                $this->applyColors($set, ThemeData::brandingPresets()[$state] ?? []);

                                if ($get('branding_pairing')) {
                                    $this->pairBase($set, $state);
                                }
                            }),

                        Select::make('branding_base')
                            ->label(__('cachet::settings.manage_theme.branding.base_label'))
                            ->helperText(__('cachet::settings.manage_theme.branding.base_helper'))
                            ->options(fn (): array => self::swatchOptions(array_map(
                                fn (string $gray): string => Color::all()[$gray][400],
                                array_combine(ThemeData::GRAYS, ThemeData::GRAYS),
                            )))
                            ->native(false)
                            ->allowHtml()
                            ->live()
                            ->disabled(fn (Get $get): bool => $get('branding_pairing') === true)
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $this->applyColors($set, ThemeData::brandingBases()[$state] ?? [])),

                        Toggle::make('branding_pairing')
                            ->label(__('cachet::settings.manage_theme.branding.pairing_label'))
                            ->helperText(__('cachet::settings.manage_theme.branding.pairing_helper'))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, bool $state): void {
                                if ($state) {
                                    $this->pairBase($set, $get('branding_color'));
                                }
                            }),

                        Checkbox::make('customize_branding')
                            ->label(__('cachet::settings.manage_theme.branding.customize_label'))
                            ->helperText(__('cachet::settings.manage_theme.branding.customize_helper'))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, bool $state): void {
                                if (! $state) {
                                    $this->resetColors($get, $set);
                                }
                            }),
                    ]),

                Section::make(__('cachet::settings.manage_theme.status_page_colors.heading'))
                    ->description(__('cachet::settings.manage_theme.status_page_colors.description'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema(self::colorPickers()),

                Section::make(__('cachet::settings.manage_theme.status_page_colors.dark_heading'))
                    ->description(__('cachet::settings.manage_theme.status_page_colors.dark_description'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema(self::colorPickers('_dark')),
            ]);
    }

    /** @return array<ColorPicker> */
    private static function colorPickers(string $suffix = ''): array
    {
        return array_map(function (string $name) use ($suffix): ColorPicker {
            // Colors follow the branding controls unless the user customizes them.
            return ColorPicker::make($name.$suffix)
                ->format('hex')
                ->label(__('cachet::settings.manage_theme.'.$name))
                ->disabled(fn (Get $get): bool => ! $get('customize_branding'))
                ->dehydrated();
        }, self::COLOR_SETTINGS);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $defaults = $this->formDefaults();

        foreach ($defaults as $name => $default) {
            $data[$name] ??= $default;
        }

        return $this->withBrandingControls($data);
    }

    /**
     * Derive the branding controls from the stored colors.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withBrandingControls(array $data): array
    {
        $branding = ThemeData::matchBranding(Arr::only($data, self::BRANDING_FIELDS));
        $base = ThemeData::matchBase(Arr::only($data, self::BASE_FIELDS));
        $data['branding_color'] = $branding;
        $data['branding_base'] = $base;
        $data['branding_pairing'] = $branding !== null && $base === ThemeData::matchPairing($branding);
        $data['customize_branding'] = $branding === null || $base === null || $this->hasOverrides($data);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = Arr::except($data, self::BRANDING_CONTROLS);

        foreach ($this->formDefaults() as $name => $default) {
            if (isset($data[$name]) && strtolower((string) $data[$name]) === $default) {
                $data[$name] = null;
            }
        }

        return $data;
    }

    /** @return array<string, string> */
    private function formDefaults(): array
    {
        return (new ThemeData(app(ThemeSettings::class)))->formDefaults();
    }

    /**
     * @param  array<string, string>  $options  Swatch color keyed by option name.
     * @return array<string, string>
     */
    private static function swatchOptions(array $options): array
    {
        return collect($options)
            ->map(fn (string $swatch, string $name): string => '<div class="flex items-center"><div class="theme-swatch" style="--swatch: '.e($swatch).'"></div><div>'.e(__(ucwords($name))).'</div></div>')
            ->all();
    }

    /**
     * @param  array<string, string>  $colors
     */
    private function applyColors(Set $set, array $colors): void
    {
        foreach ($colors as $field => $color) {
            $set($field, $color);
        }
    }

    private function pairBase(Set $set, ?string $branding): void
    {
        $base = ThemeData::matchPairing($branding);

        $set('branding_base', $base);
        $this->applyColors($set, ThemeData::brandingBases()[$base]);
    }

    /**
     * Put every color back to what the branding controls produce.
     */
    private function resetColors(Get $get, Set $set): void
    {
        $branding = $get('branding_color') ?? 'cachet';

        $set('branding_color', $branding);
        $this->applyColors($set, $this->themeDefaults());
        $this->applyColors($set, ThemeData::brandingPresets()[$branding]);

        if ($get('branding_pairing') || $get('branding_base') === null) {
            $this->pairBase($set, $branding);

            return;
        }

        $this->applyColors($set, ThemeData::brandingBases()[$get('branding_base')]);
    }

    /**
     * Whether any color outside the branding and base colors differs from its default.
     *
     * @param  array<string, mixed>  $data
     */
    private function hasOverrides(array $data): bool
    {
        $controlled = [...self::BRANDING_FIELDS, ...self::BASE_FIELDS];

        foreach (Arr::except($this->themeDefaults(), $controlled) as $name => $default) {
            if (strtolower((string) ($data[$name] ?? $default)) !== strtolower($default)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function themeDefaults(): array
    {
        return (new ThemeData(app(ThemeSettings::class)))->pristineDefaults();
    }

    private function exportAction(): Action
    {
        return Action::make('exportBranding')
            ->label(__('cachet::settings.manage_theme.branding_io.export_label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn () => response()->streamDownload(
                fn () => print json_encode(
                    Arr::only($this->form->getRawState(), $this->colorFields()),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ),
                'branding-colors.json',
                ['Content-Type' => 'application/json'],
            ));
    }

    private function importAction(): Action
    {
        return Action::make('importBranding')
            ->label(__('cachet::settings.manage_theme.branding_io.import_label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading(__('cachet::settings.manage_theme.branding_io.import_label'))
            ->modalSubmitActionLabel(__('cachet::settings.manage_theme.branding_io.import_label'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('cachet::settings.manage_theme.branding_io.file_label'))
                    ->helperText(__('cachet::settings.manage_theme.branding_io.file_helper'))
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->maxSize(64)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $colors = $this->parseColors((string) $data['file']->get());

                if ($colors === []) {
                    Notification::make()->danger()->title(__('cachet::settings.manage_theme.branding_io.import_failed'))->send();

                    return;
                }

                $this->form->fill($this->withBrandingControls([...$this->form->getRawState(), ...$colors]));

                Notification::make()->success()->title(__('cachet::settings.manage_theme.branding_io.imported'))->send();
            });
    }

    /**
     * The recognised, valid hex colors in a branding JSON document.
     *
     * @return array<string, string>
     */
    private function parseColors(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter(
            Arr::only($decoded, $this->colorFields()),
            fn (mixed $color): bool => ThemeData::isHexColor($color),
        );
    }

    /** @return array<int, string> */
    private function colorFields(): array
    {
        return array_merge(self::COLOR_SETTINGS, array_map(fn (string $name): string => $name.'_dark', self::COLOR_SETTINGS));
    }
}
