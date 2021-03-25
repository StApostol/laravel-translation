<?php

namespace JoeDixon\Translation\Console\Commands;

class AddTranslationKeyCommand extends BaseCommand
{
    protected $signature = 'translation:add-translation-key';
    protected $description = 'Add a new language key for the application';

    public function handle(): int
    {
        $language = $this->ask(__('translation::translation.prompt_language_for_key'));

        $type = $this->anticipate(__('translation::translation.prompt_type'), ['single', 'group']);

        // if the group type is selected, prompt for the group key
        if ($type === 'group') {
            $file = $this->ask(__('translation::translation.prompt_group'));
        }

        $key = $this->ask(__('translation::translation.prompt_key'));
        $value = $this->ask(__('translation::translation.prompt_value'));

        // attempt to add the key for single or group and fail gracefully if
        // exception is thrown
        if ($type === 'single') {
            try {
                $this->translation->addSingleTranslation($language, 'single', $key, $value);

                $this->info(__('translation::translation.language_key_added'));

                return self::SUCCESS;
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        if ($type === 'group') {
            try {
                $file = str_replace('.php', '', $file);
                $this->translation->addGroupTranslation($language, $file, $key, $value);

                $this->info(__('translation::translation.language_key_added'));

                return self::SUCCESS;
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->error(__('translation::translation.type_error'));

        return self::FAILURE;
    }
}
