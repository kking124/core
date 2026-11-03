<?php

namespace Tests\Feature\Filament\Settings;

use Cachet\Filament\Pages\Settings\ManageTheme;
use Cachet\Settings\ThemeSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Workbench\App\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    actingAs(User::factory()->create(['is_admin' => true]));
    Storage::fake('public');

    config()->set([
        'cachet.uploads.disk' => 'public',
        'cachet.uploads.max_size' => 256,
        'cachet.uploads.image_mime_types' => ['image/jpeg', 'image/png'],
    ]);
});

it('hides the duplicate banner field label beneath the section heading', function () {
    livewire(ManageTheme::class)
        ->assertSchemaComponentExists(
            'app_banner',
            'form',
            fn (FileUpload $field): bool => $field->isLabelHidden()
                && $field->getLabel() === __('cachet::settings.manage_theme.app_banner_label'),
        );
});

it('configures app logo upload validation', function () {
    livewire(ManageTheme::class)
        ->assertFormFieldExists('app_banner', function (FileUpload $field): bool {
            return $field->getDiskName() === 'public'
                && $field->getMaxSize() === 256
                && $field->getAcceptedFileTypes() === ['image/jpeg', 'image/png']
                && $field->shouldPreventFilePathTampering();
        });
});

it('rejects app logos with disallowed image types', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.gif', 32, 'image/gif'),
        ])
        ->call('save')
        ->assertHasFormErrors(['app_banner']);
});

it('rejects app logos larger than the configured maximum', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.png', 257, 'image/png'),
        ])
        ->call('save')
        ->assertHasFormErrors(['app_banner']);
});

it('accepts app logos within the configured constraints', function () {
    livewire(ManageTheme::class)
        ->fillForm([
            'app_banner' => UploadedFile::fake()->create('app-logo.png', 256, 'image/png'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('provides light and dark color pickers with their effective defaults', function () {
    $component = livewire(ManageTheme::class);

    foreach ([
        'background_color', 'text_color', 'banner_background_color', 'background_fill_color',
        'error_color', 'info_color', 'success_color', 'alert_color', 'notice_color',
        'metrics_fill_color', 'link_color',
    ] as $name) {
        $component->assertFormFieldExists($name, fn (ColorPicker $field): bool => $field->getFormat() === 'hex');
        $component->assertFormFieldExists($name.'_dark', fn (ColorPicker $field): bool => $field->getFormat() === 'hex');
    }

    $component->assertSet('data.text_color', '#3f3f46')
        ->assertSet('data.text_color_dark', '#d4d4d8');
});

it('stores unchanged default colors as null and persists changed colors', function () {
    livewire(ManageTheme::class)
        ->call('save');

    expect(app(ThemeSettings::class)->text_color)->toBeNull();

    livewire(ManageTheme::class)
        ->fillForm([
            'text_color' => '#112233',
            'text_color_dark' => '#aabbcc',
            'banner_padding' => 24,
        ])
        ->call('save');

    $settings = app(ThemeSettings::class);

    expect($settings->text_color)->toBe('#112233')
        ->and($settings->text_color_dark)->toBe('#aabbcc')
        ->and($settings->banner_padding)->toBe(24);
});
