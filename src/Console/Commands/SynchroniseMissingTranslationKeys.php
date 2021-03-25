<?php

namespace JoeDixon\Translation\Console\Commands;

class SynchroniseMissingTranslationKeys extends BaseCommand
{
    protected $signature = 'translation:sync-missing-translation-keys {language?}';
    protected $description = 'Add all of the missing translation keys for all languages or a single language';

    public function handle(): int
    {
        $language = $this->argument('language') ?: null;

        try {
            // if we have a language, pass it in, if not the method will
            // automagically sync all languages
            $this->translation->saveMissingTranslations($language);

            return $this->info(__('translation::translation.keys_synced'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
