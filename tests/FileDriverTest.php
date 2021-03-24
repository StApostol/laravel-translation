<?php

namespace JoeDixon\Translation\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use JoeDixon\Translation\Drivers\File;
use JoeDixon\Translation\Drivers\Translation;
use JoeDixon\Translation\Exceptions\LanguageExistsException;
use JoeDixon\Translation\TranslationBindingsServiceProvider;
use JoeDixon\Translation\TranslationServiceProvider;
use Orchestra\Testbench\TestCase;

class FileDriverTest extends TestCase
{
    /** @var File */
    private $translation;

    /** @var Filesystem */
    private $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        app()['path.lang'] = __DIR__.'/fixtures/lang';

        $this->translation = app()->make(Translation::class);
        $this->filesystem = app()->make(Filesystem::class);
    }

    protected function tearDown(): void
    {
        $this->restoreFixtureFolder();

        unset(
            $this->translation,
            $this->filesystem
        );

        parent::tearDown();
    }

    protected function getPackageProviders(Application $app): array
    {
        return [
            TranslationServiceProvider::class,
            TranslationBindingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('translation.driver', 'file');
    }

    /** @test */
    public function it_returns_all_languages(): void
    {
        $languages = $this->translation->allLanguages();

        $this->assertCount(2, $languages);
        $this->assertEquals(['en' => 'en', 'es' => 'es'], $languages->toArray());
    }

    /** @test */
    public function it_returns_all_translations(): void
    {
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
                'single' => [
                ],
                'group' => [
                ],
            ],
        ], $translations->toArray());
    }

    /** @test */
    public function it_returns_all_translations_for_a_given_language(): void
    {
        $translations = $this->translation->allTranslationsFor('en');

        $this->assertCount(2, $translations);

        $this->assertArrayHasKey('single', $translations->toArray());
        $this->assertArrayHasKey('group', $translations->toArray());

        $this->assertEquals([
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
        ], $translations->toArray());
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
        $this->translation->addLanguage('fr');

        $this->assertTrue(file_exists(__DIR__.'/fixtures/lang/fr.json'));
        $this->assertTrue(file_exists(__DIR__.'/fixtures/lang/fr'));

        unlink(__DIR__.'/fixtures/lang/fr.json');
        rmdir(__DIR__.'/fixtures/lang/fr');
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
        $this->translation->addGroupTranslation('en', 'test', 'test', 'Testing');

        $translations = $this->translation->allTranslationsFor('en');

        $this->assertEquals(['group' => ['test' => ['hello' => 'Hello', 'whats_up' => 'What\'s up!', 'test' => 'Testing']], 'single' => ['single' => ['Hello' => 'Hello', 'What\'s up' => 'What\'s up!']]], $translations->toArray());
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
        $this->translation->addSingleTranslation('en', 'single', 'Test', 'Testing');

        $translations = $this->translation->allTranslationsFor('en');

        $this->assertEquals(['single' => ['single' => ['Hello' => 'Hello', 'What\'s up' => 'What\'s up!', 'Test' => 'Testing']], 'group' => ['test' => ['hello' => 'Hello', 'whats_up' => 'What\'s up!',]]], $translations->toArray());
    }

    /** @test */
    public function it_can_get_a_collection_of_group_names_for_a_given_language(): void
    {
        $groups = $this->translation->getGroupsFor('en');

        $this->assertEquals(['test'], $groups->toArray());
    }

    /** @test */
    public function it_can_merge_a_language_with_the_base_language(): void
    {
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

        unlink(__DIR__.'/fixtures/lang/es/test.php');
    }

    /** @test */
    public function it_can_add_a_vendor_namespaced_translations(): void
    {
        $this->translation->addGroupTranslation('es', 'translation_test::test', 'hello', 'Hola!');
        $this->translation->addGroupTranslation('es', 'translation_test::test', 'nested.hello', 'Hola!');

        $this->assertEquals([
            'group' => [
                'translation_test::test' => [
                    'hello' => 'Hola!',
                    'nested' => [
                        'hello' => 'Hola!',
                    ],
                ],
            ],
            'single' => [],
        ], $this->translation->allTranslationsFor('es')->toArray());
    }

    /** @test */
    public function it_can_add_a_nested_translation(): void
    {
        $this->translation->addGroupTranslation('en', 'test', 'test.nested', 'Nested!');

        $this->assertEquals([
            'test' => [
                'hello' => 'Hello',
                'test' => [
                    'nested' => 'Nested!',
                ],
                'whats_up' => 'What\'s up!',
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
                'test' => [
                    'hello' => ['en' => 'Hello', 'es' => ''],
                    'whats_up' => ['en' => "What's up!", 'es' => ''],
                ],
                'translation_test::test' => [
                    'hello' => ['en' => 'Hello', 'es' => 'Hola!'],
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
    public function a_list_of_languages_can_be_viewed(): void
    {
        $this->get(config('translation.ui_url'))
            ->assertSee('en');
    }

    /** @test */
    public function the_language_creation_page_can_be_viewed(): void
    {
        $this->get(config('translation.ui_url').'/create')
            ->assertSee('Add a new language');
    }

    /** @test */
    public function a_language_can_be_added(): void
    {
        $this->post(config('translation.ui_url'), ['locale' => 'de'])
            ->assertRedirect();

        $this->assertTrue(file_exists(__DIR__.'/fixtures/lang/de.json'));
        $this->assertTrue(file_exists(__DIR__.'/fixtures/lang/de'));

        rmdir(__DIR__.'/fixtures/lang/de');
        unlink(__DIR__.'/fixtures/lang/de.json');
    }

    /** @test */
    public function a_list_of_translations_can_be_viewed(): void
    {
        $this->get(config('translation.ui_url').'/en/translations')
            ->assertSee('hello')
            ->assertSee('whats_up');
    }

    /** @test */
    public function the_translation_creation_page_can_be_viewed(): void
    {
        $this->get(config('translation.ui_url').'/'.config('app.locale').'/translations/create')
            ->assertSee('Add a translation');
    }

    /** @test */
    public function a_new_translation_can_be_added(): void
    {
        $this->post(config('translation.ui_url').'/en/translations', ['key' => 'joe', 'value' => 'is cool'])
            ->assertRedirect();
        $translations = $this->translation->getSingleTranslationsFor('en');

        $this->assertEquals([
            'single' => [
                'Hello' => 'Hello',
                'What\'s up' => 'What\'s up!',
                'joe' => 'is cool',
            ],
        ], $translations->toArray());
    }

    /** @test */
    public function a_translation_can_be_updated(): void
    {
        $this->post(config('translation.ui_url').'/en', ['group' => 'test', 'key' => 'hello', 'value' => 'Hello there!'])
            ->assertStatus(200)
            ->assertSee(json_encode(['success' => true]));
        $translations = $this->translation->getGroupTranslationsFor('en');

        $this->assertEquals([
            'test' => [
                'hello' => 'Hello there!',
                'whats_up' => 'What\'s up!',
            ],
        ], $translations->toArray());
    }

    private function restoreFixtureFolder(): void
    {
        $this->filesystem->delete(app()['path.lang'].'/es/test.php');
        $this->filesystem->delete(app()['path.lang'].'/es.json');
        $this->filesystem->deleteDirectory(app()['path.lang'].'/vendor');

        file_put_contents(
            app()['path.lang'].'/en.json',
            json_encode((object) ['Hello' => 'Hello', 'What\'s up' => 'What\'s up!'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        file_put_contents(
            app()['path.lang'].'/en/test.php',
            "<?php\n\nreturn ".var_export(['hello' => 'Hello', 'whats_up' => 'What\'s up!'], true).';'.\PHP_EOL
        );
    }
}
