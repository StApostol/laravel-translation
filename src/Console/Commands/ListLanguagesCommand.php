<?php

namespace JoeDixon\Translation\Console\Commands;

class ListLanguagesCommand extends BaseCommand
{
    protected $signature = 'translation:list-languages';
    protected $description = 'List all of the available languages in the application';

    public function handle(): int
    {
        $headers = [__('translation::translation.language_name'), __('translation::translation.language')];
        $languages = $this->translation->allLanguages()->toArray();
        $mappedLanguages = [];

        foreach ($languages as $language => $name) {
            $mappedLanguages[] = [$name, $language];
        }

        // return a table of results
        $this->table($headers, $mappedLanguages);

        return self::SUCCESS;
    }
}
