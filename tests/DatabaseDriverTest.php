<?php

namespace JoeDixon\Translation\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use JoeDixon\Translation\Drivers\Translation;
use JoeDixon\Translation\Exceptions\LanguageExistsException;
use JoeDixon\Translation\Language;
use JoeDixon\Translation\Translation as TranslationModel;
use JoeDixon\Translation\TranslationBindingsServiceProvider;
use JoeDixon\Translation\TranslationServiceProvider;
use Orchestra\Testbench\TestCase;

class DatabaseDriverTest extends TestCase
{
    use DatabaseMigrations;

    private $translation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__.'/../database/factories');
        $this->translation = $this->app[Translation::class];
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance(Translation::class);
        unset($this->translation);

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('translation.driver', 'database');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    /**
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            TranslationServiceProvider::class,
            TranslationBindingsServiceProvider::class,
        ];
    }

    /** @test */
    public function it_returns_all_languages(): void
    {
        $newLanguages = factory(Language::class, 2)->create();
        $newLanguages = $newLanguages->mapWithKeys(function ($language) {
            return [$language->language => $language->name];
        })->toArray();
        $languages = $this->translation->allLanguages();

        $this->assertEquals($languages->count(), 3);
        $this->assertEquals($languages->toArray(), ['en' => 'en'] + $newLanguages);
    }

    /** @test */
    public function it_returns_all_translations(): void
    {
        $default = Language::where('language', config('app.locale'))->first();
        $spanish = factory(Language::class)->create(['language' => 'es', 'name' => 'Español']);
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'whats_up', 'value' => "What's up!"]);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => 'Hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => "What's up", 'value' => "What's up!"]);

        $translations = $this->translation->allTranslations();

        $this->assertCount(2, $translations);

        $this->assertArrayHasKey('en', $translations->toArray());
        $this->assertArrayHasKey('es', $translations->toArray());

        $this->assertEquals([
            'en' => [
                'single' => [
                    'single' => [
                        'Hello' => 'Hello',
                        "What's up" => "What's up!",
                    ],
                ],
                'group' => [
                    'test' => [
                        'hello' => 'Hello',
                        'whats_up' => "What's up!",
                    ],
                ],
            ],
            'es' => [
                'single' => [],
                'group' => [],
            ],
        ], $translations->toArray());
    }

    /** @test */
    public function it_returns_all_translations_for_a_given_language(): void
    {
        $default = Language::where('language', config('app.locale'))->first();
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'whats_up', 'value' => "What's up!"]);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => 'Hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => "What's up", 'value' => "What's up!"]);

        $translations = $this->translation->allTranslationsFor('en');

        $this->assertCount(2, $translations);

        $this->assertArrayHasKey('single', $translations->toArray());
        $this->assertArrayHasKey('group', $translations->toArray());

        $this->assertEquals(['single' => ['single' => ['Hello' => 'Hello', "What's up" => "What's up!"]], 'group' => ['test' => ['hello' => 'Hello', 'whats_up' => "What's up!"]]], $translations->toArray());
    }

    /** @test */
    public function it_throws_an_exception_if_a_language_exists(): void
    {
        $this->expectException(LanguageExistsException::class);
        $this->translation->addLanguage('en');
    }

    /** @test */
    public function it_can_add_a_new_language(): void
    {
        $this->assertDatabaseMissing(config('translation.database.languages_table'), [
            'language' => 'fr',
            'name' => 'Français',
        ]);

        $this->translation->addLanguage('fr', 'Français');
        $this->assertDatabaseHas(config('translation.database.languages_table'), [
            'language' => 'fr',
            'name' => 'Français',
        ]);
    }

    /** @test */
    public function it_can_add_a_new_translation_to_a_new_group(): void
    {
        $this->translation->addGroupTranslation('es', 'test', 'hello', 'Hola!');

        $translations = $this->translation->allTranslationsFor('es');

        $this->assertEquals(['group' => ['test' => ['hello' => 'Hola!']], 'single' => []], $translations->toArray());
    }

    /** @test */
    public function it_can_add_a_new_translation_to_an_existing_translation_group(): void
    {
        $translation = factory(TranslationModel::class)->create();

        $this->translation->addGroupTranslation($translation->language->language, "{$translation->group}", 'test', 'Testing');

        $translations = $this->translation->allTranslationsFor($translation->language->language);

        $this->assertEquals(['group' => [$translation->group => [$translation->key => $translation->value, 'test' => 'Testing']], 'single' => []], $translations->toArray());
    }

    /** @test */
    public function it_can_add_a_new_single_translation(): void
    {
        $this->translation->addSingleTranslation('es', 'single', 'Hello', 'Hola!');

        $translations = $this->translation->allTranslationsFor('es');

        $this->assertEquals(['single' => ['single' => ['Hello' => 'Hola!']], 'group' => []], $translations->toArray());
    }

    /** @test */
    public function it_can_add_a_new_single_translation_to_an_existing_language(): void
    {
        $translation = factory(TranslationModel::class)->states('single')->create();

        $this->translation->addSingleTranslation($translation->language->language, 'single', 'Test', 'Testing');

        $translations = $this->translation->allTranslationsFor($translation->language->language);

        $this->assertEquals(['single' => ['single' => ['Test' => 'Testing', $translation->key => $translation->value]], 'group' => []], $translations->toArray());
    }

    /** @test */
    public function it_can_get_a_collection_of_group_names_for_a_given_language(): void
    {
        $language = Language::where(['language' => 'en'])->first();

        factory(TranslationModel::class)->create([
            'language_id' => $language->id,
            'group' => 'test',
        ]);

        $groups = $this->translation->getGroupsFor('en');

        $this->assertEquals(['test'], $groups->toArray());
    }

    /** @test */
    public function it_can_merge_a_language_with_the_base_language(): void
    {
        $default = Language::where('language', config('app.locale'))->first();
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'whats_up', 'value' => "What's up!"]);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => 'Hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'group' => 'single', 'key' => "What's up", 'value' => "What's up!"]);

        $this->translation->addGroupTranslation('es', 'test', 'hello', 'Hola!');
        $translations = $this->translation->getSourceLanguageTranslationsWith('es');

        $this->assertEquals([
            'group' => [
                'test' => [
                    'hello' => ['en' => 'Hello', 'es' => 'Hola!'],
                    'whats_up' => ['en' => "What's up!", 'es' => ''],
                ],
            ],
            'single' => [
                'single' => [
                    'Hello' => [
                        'en' => 'Hello',
                        'es' => '',
                    ],
                    "What's up" => [
                        'en' => "What's up!",
                        'es' => '',
                    ],
                ],
            ],
        ], $translations->toArray());
    }

    /** @test */
    public function it_can_add_a_vendor_namespaced_translations(): void
    {
        $this->translation->addGroupTranslation('es', 'translation_test::test', 'hello', 'Hola!');

        $this->assertEquals($this->translation->allTranslationsFor('es')->toArray(), [
            'group' => [
                'translation_test::test' => [
                    'hello' => 'Hola!',
                ],
            ],
            'single' => [],
        ]);
    }

    /** @test */
    public function it_can_add_a_nested_translation(): void
    {
        $this->translation->addGroupTranslation('en', 'test', 'test.nested', 'Nested!');

        $this->assertEquals([
            'test' => [
                'test' => [
                    'nested' => 'Nested!',
                ],
            ],
        ], $this->translation->getGroupTranslationsFor('en')->toArray());
    }

    /** @test */
    public function it_can_add_nested_vendor_namespaced_translations(): void
    {
        $this->translation->addGroupTranslation('es', 'translation_test::test', 'nested.hello', 'Hola!');

        $this->assertEquals([
            'group' => [
                'translation_test::test' => [
                    'nested' => [
                        'hello' => 'Hola!',
                    ],
                ],
            ],
            'single' => [],
        ], $this->translation->allTranslationsFor('es')->toArray());
    }

    /** @test */
    public function it_can_merge_a_namespaced_language_with_the_base_language(): void
    {
        $this->translation->addGroupTranslation('en', 'translation_test::test', 'hello', 'Hello');
        $this->translation->addGroupTranslation('es', 'translation_test::test', 'hello', 'Hola!');
        $translations = $this->translation->getSourceLanguageTranslationsWith('es');

        $this->assertEquals([
            'group' => [
                'translation_test::test' => [
                    'hello' => [
                        'en' => 'Hello',
                        'es' => 'Hola!',
                    ],
                ],
            ],
            'single' => [],
        ], $translations->toArray());
    }

    /** @test */
    public function a_list_of_languages_can_be_viewed(): void
    {
        $newLanguages = factory(Language::class, 2)->create();
        $response = $this->get(config('translation.ui_url'));

        $response->assertSee(config('app.locale'));
        foreach ($newLanguages as $language) {
            $response->assertSee($language->language);
        }
    }

    /** @test */
    public function the_language_creation_page_can_be_viewed(): void
    {
        $this->translation->addGroupTranslation(config('app.locale'), 'translation::translation', 'add_language', 'Add a new language');
        $this->get(config('translation.ui_url').'/create')
            ->assertSee('Add a new language');
    }

    /** @test */
    public function a_language_can_be_added()
    {
        $this->post(config('translation.ui_url'), ['locale' => 'de'])
            ->assertRedirect();

        $this->assertDatabaseHas('languages', ['language' => 'de']);
    }

    /** @test */
    public function a_list_of_translations_can_be_viewed(): void
    {
        $default = Language::where('language', config('app.locale'))->first();
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'whats_up', 'value' => "What's up!"]);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'key' => 'Hello', 'value' => 'Hello!']);
        factory(TranslationModel::class)->states('single')->create(['language_id' => $default->id, 'key' => "What's up", 'value' => 'Sup!']);

        $this->get(config('translation.ui_url').'/en/translations')
            ->assertSee('hello')
            ->assertSee('whats_up')
            ->assertSee('Hello')
            ->assertSee('Sup!');
    }

    /** @test */
    public function the_translation_creation_page_can_be_viewed(): void
    {
        $this->translation->addGroupTranslation('en', 'translation::translation', 'add_translation', 'Add a translation');
        $this->get(config('translation.ui_url').'/'.config('app.locale').'/translations/create')
            ->assertSee('Add a translation');
    }

    /** @test */
    public function a_new_translation_can_be_added(): void
    {
        $this->post(config('translation.ui_url').'/'.config('app.locale').'/translations', ['group' => 'single', 'key' => 'joe', 'value' => 'is cool'])
            ->assertRedirect();

        $this->assertDatabaseHas('translations', ['language_id' => 1, 'key' => 'joe', 'value' => 'is cool']);
    }

    /** @test */
    public function a_translation_can_be_updated(): void
    {
        $default = Language::where('language', config('app.locale'))->first();
        factory(TranslationModel::class)->states('group')->create(['language_id' => $default->id, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);
        $this->assertDatabaseHas('translations', ['language_id' => 1, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello']);

        $this->post(config('translation.ui_url').'/en', ['group' => 'test', 'key' => 'hello', 'value' => 'Hello there!'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('translations', ['language_id' => 1, 'group' => 'test', 'key' => 'hello', 'value' => 'Hello there!']);
    }
}
