<?php

namespace Cachet\Filament\Pages\Settings;

use Cachet\Cachet;
use Cachet\Data\Cachet\ThemeData;
use Cachet\Settings\ThemeSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
            ->components([
                Section::make(__('cachet::settings.manage_theme.app_banner_label'))->columns(2)->schema([
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

                Section::make(__('cachet::settings.manage_theme.status_page_colors.heading'))
                    ->description(__('cachet::settings.manage_theme.status_page_colors.description'))
                    ->columns(2)
                    ->collapsible()
                    ->schema(self::colorPickers()),

                Section::make(__('cachet::settings.manage_theme.status_page_colors.dark_heading'))
                    ->description(__('cachet::settings.manage_theme.status_page_colors.dark_description'))
                    ->columns(2)
                    ->collapsed()
                    ->schema(self::colorPickers('_dark')),
            ]);
    }

    /** @return array<ColorPicker> */
    private static function colorPickers(string $suffix = ''): array
    {
        return array_map(fn (string $name): ColorPicker => ColorPicker::make($name.$suffix)
            ->format('hex')
            ->label(__('cachet::settings.manage_theme.'.$name)), self::COLOR_SETTINGS);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $defaults = $this->formDefaults();

        foreach ($defaults as $name => $default) {
            $data[$name] ??= $default;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
}
