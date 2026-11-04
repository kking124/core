<?php

namespace Tests\Feature\Filament\Settings;

use Cachet\Data\Cachet\ThemeData;
use Cachet\Filament\Pages\Settings\ManageTheme;
use Cachet\Settings\ThemeSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
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

it('locks every light and dark color until customizing is enabled', function () {
    $component = livewire(ManageTheme::class)
        ->assertFormFieldExists('branding_color', fn (Select $field): bool => ! $field->isDisabled())
        ->assertFormFieldExists('branding_base', fn (Select $field): bool => $field->isDisabled())
        ->assertFormFieldExists('branding_pairing', fn (Toggle $field): bool => ! $field->isDisabled())
        ->assertFormFieldExists('customize_branding', fn (Checkbox $field): bool => ! $field->isDisabled())
        ->assertSet('data.customize_branding', false)
        ->assertSet('data.branding_color', 'cachet')
        ->assertSet('data.branding_base', 'zinc')
        ->assertSet('data.branding_pairing', true);

    foreach (['text_color', 'link_color_dark', 'error_color', 'background_color_dark'] as $name) {
        $component->assertFormFieldExists($name, fn (ColorPicker $field): bool => $field->isDisabled());
    }

    $component->set('data.customize_branding', true);

    foreach (['text_color', 'link_color_dark', 'error_color', 'background_color_dark'] as $name) {
        $component->assertFormFieldExists($name, fn (ColorPicker $field): bool => ! $field->isDisabled());
    }
});

it('places the customize checkbox after the original branding controls', function () {
    $names = collect(livewire(ManageTheme::class)->instance()->form->getFlatComponents())
        ->map(fn ($component) => method_exists($component, 'getName') ? $component->getName() : null)
        ->filter(fn ($name) => in_array($name, ['branding_color', 'branding_base', 'branding_pairing', 'customize_branding'], true))
        ->values()
        ->all();

    expect($names)->toBe(['branding_color', 'branding_base', 'branding_pairing', 'customize_branding']);
});

it('pairs the base color with the brand color while pairing is on', function () {
    $slate = ThemeData::brandingBases()['slate'];

    livewire(ManageTheme::class)
        ->set('data.branding_color', 'blue')
        ->assertSet('data.branding_base', 'slate')
        ->assertSet('data.background_color', $slate['background_color'])
        ->assertSet('data.background_color_dark', $slate['background_color_dark'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->background_color)->toBe($slate['background_color']);
});

it('lets the base color be chosen when pairing is off', function () {
    $stone = ThemeData::brandingBases()['stone'];

    livewire(ManageTheme::class)
        ->set('data.branding_pairing', false)
        ->assertFormFieldExists('branding_base', fn (Select $field): bool => ! $field->isDisabled())
        ->set('data.branding_base', 'stone')
        ->set('data.branding_color', 'blue')
        ->assertSet('data.branding_base', 'stone')
        ->assertSet('data.background_color', $stone['background_color']);
});

it('sets the brand colors from the selected branding color', function () {
    $blue = ThemeData::brandingPresets()['blue'];

    livewire(ManageTheme::class)
        ->set('data.branding_color', 'blue')
        ->assertSet('data.link_color', $blue['link_color'])
        ->assertSet('data.link_color_dark', $blue['link_color_dark'])
        ->assertSet('data.metrics_fill_color', $blue['metrics_fill_color'])
        ->assertSet('data.metrics_fill_color_dark', $blue['metrics_fill_color_dark'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ThemeSettings::class);

    expect($settings->link_color)->toBe($blue['link_color'])
        ->and($settings->metrics_fill_color_dark)->toBe($blue['metrics_fill_color_dark'])
        ->and((new ThemeData($settings))->styles)->toContain('--link-color: '.$blue['link_color']);
});

it('stores the default branding color as no override', function () {
    livewire(ManageTheme::class)
        ->set('data.branding_color', 'blue')
        ->call('save')
        ->set('data.branding_color', 'cachet')
        ->call('save');

    $settings = app(ThemeSettings::class);

    expect($settings->link_color)->toBeNull()
        ->and($settings->metrics_fill_color)->toBeNull();
});

it('saves customized brand colors and reopens in customize mode', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->fillForm(['link_color' => '#112233', 'metrics_fill_color_dark' => '#aabbcc'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ThemeSettings::class)->link_color)->toBe('#112233');

    livewire(ManageTheme::class)
        ->assertSet('data.customize_branding', true)
        ->assertSet('data.branding_color', null);
});

it('restores the selected branding color when customizing is turned off', function () {
    $green = ThemeData::brandingPresets()['green'];

    livewire(ManageTheme::class)
        ->set('data.branding_color', 'green')
        ->set('data.customize_branding', true)
        ->set('data.link_color', '#112233')
        ->set('data.customize_branding', false)
        ->assertSet('data.link_color', $green['link_color']);
});

it('resets every color to the branding defaults when customizing is turned off', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->set('data.text_color', '#112233')
        ->set('data.customize_branding', false)
        ->assertSet('data.text_color', '#3f3f46');
});

it('reopens in customize mode when a non-brand color is overridden', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->set('data.error_color', '#112233')
        ->call('save');

    livewire(ManageTheme::class)
        ->assertSet('data.customize_branding', true);
});

it('does not persist the branding form controls', function () {
    livewire(ManageTheme::class)
        ->set('data.branding_color', 'blue')
        ->call('save');

    expect(DB::table('settings')->where('group', 'theme')->whereIn('name', ['branding_color', 'branding_base', 'branding_pairing', 'customize_branding'])->exists())->toBeFalse();
});

function brandingAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent('branding-io');
}

function brandingFile(array|string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('branding.json', is_array($contents) ? json_encode($contents) : $contents);
}

it('exports the branding colors as a json download', function () {
    $component = livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->set('data.text_color', '#112233')
        ->set('data.link_color_dark', '#aabbcc')
        ->callAction(brandingAction('exportBranding'))
        ->assertFileDownloaded('branding-colors.json');

    $exported = json_decode(base64_decode(data_get($component->effects, 'download.content')), true);

    expect($exported)->toHaveCount(22)
        ->and($exported['text_color'])->toBe('#112233')
        ->and($exported['link_color_dark'])->toBe('#aabbcc')
        ->and($exported)->not->toHaveKeys(['banner_padding', 'branding_color', 'customize_branding']);
});

it('imports branding colors into the form without saving them', function () {
    livewire(ManageTheme::class)
        ->callAction(brandingAction('importBranding'), ['file' => brandingFile([
            'text_color' => '#112233',
            'error_color_dark' => '#abcdef',
        ])])
        ->assertHasNoFormErrors()
        ->assertSet('data.text_color', '#112233')
        ->assertSet('data.error_color_dark', '#abcdef')
        ->assertSet('data.customize_branding', true);

    expect(app(ThemeSettings::class)->text_color)->toBeNull();
});

it('round trips an export through an import', function () {
    $exported = base64_decode(data_get(
        livewire(ManageTheme::class)
            ->set('data.customize_branding', true)
            ->set('data.success_color', '#123456')
            ->callAction(brandingAction('exportBranding'))
            ->effects,
        'download.content',
    ));

    livewire(ManageTheme::class)
        ->callAction(brandingAction('importBranding'), ['file' => brandingFile($exported)])
        ->assertSet('data.success_color', '#123456');
});

it('ignores unknown keys and invalid colors on import', function () {
    livewire(ManageTheme::class)
        ->callAction(brandingAction('importBranding'), ['file' => brandingFile([
            'text_color' => 'red; } body { display: none',
            'banner_padding' => 999,
            'not_a_color' => '#112233',
            'link_color' => '#112233',
        ])])
        ->assertSet('data.link_color', '#112233')
        ->assertSet('data.text_color', '#3f3f46')
        ->assertSet('data.banner_padding', null);
});

it('rejects an import with no valid colors', function (string $contents) {
    livewire(ManageTheme::class)
        ->callAction(brandingAction('importBranding'), ['file' => brandingFile($contents)])
        ->assertNotified(__('cachet::settings.manage_theme.branding_io.import_failed'))
        ->assertSet('data.customize_branding', false);
})->with([
    'not json' => 'not json',
    'not an object' => '"#112233"',
    'no known colors' => '{"banner_padding": 10}',
]);

function colorSectionCollapsed(Testable $component, string $id): bool
{
    $section = collect($component->instance()->form->getComponents())
        ->first(fn ($candidate): bool => $candidate instanceof Section && $candidate->getId() === $id);

    return $section->isCollapsed();
}

it('collapses the light and dark color sections by default', function () {
    $component = livewire(ManageTheme::class);

    expect(colorSectionCollapsed($component, 'light-colors'))->toBeTrue()
        ->and(colorSectionCollapsed($component, 'dark-colors'))->toBeTrue();
});

it('opens both color sections when customizing is turned on', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->assertDispatched('open-section', id: 'light-colors')
        ->assertDispatched('open-section', id: 'dark-colors');
});

it('does not open the color sections when customizing is turned off', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->set('data.customize_branding', false)
        ->assertNotDispatched('open-section');
});

it('opens both color sections on load when colors are customized', function () {
    livewire(ManageTheme::class)
        ->set('data.customize_branding', true)
        ->set('data.error_color', '#112233')
        ->call('save');

    $component = livewire(ManageTheme::class);

    expect(colorSectionCollapsed($component, 'light-colors'))->toBeFalse()
        ->and(colorSectionCollapsed($component, 'dark-colors'))->toBeFalse();
});
