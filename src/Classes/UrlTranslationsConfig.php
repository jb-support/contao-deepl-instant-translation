<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Classes;

class UrlTranslationsConfig
{
    private array $config;
    private string $configPath;

    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 5) . '/system/config/url_translations.php';
    }

    public function getUrlTranslations(): array
    {
        if (file_exists($this->configPath)) {
            $config = @include($this->configPath);
            return $config['url_translations'] ?? [];
        }
        return [];
    }

    public function saveUrlTranslations(array $translations): void
    {
        $config = file_exists($this->configPath) ? @include($this->configPath) : [];
        $config['url_translations'] = $translations;
        file_put_contents($this->configPath, '<?php return ' . var_export($config, true) . ';');
    }
}
