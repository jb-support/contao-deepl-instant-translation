<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Classes;

class TranslationSettingsRegistry
{
    private ?string $deeplKey = null;
    private ?string $originalLanguage = null;
    private ?array $enabledLanguages = [];

    public function setKey(string $key): void
    {
        $this->deeplKey = $key;
    }

    public function getKey(): ?string
    {
        return $this->deeplKey;
    }

    public function setEnabledLanguages(array $languages): void
    {
        $this->enabledLanguages = $languages;
    }

    public function getEnabledLanguages(): ?array
    {
        return $this->enabledLanguages;
    }

    public function setOriginalLanguage(string $language): void
    {
        $this->originalLanguage = $language;
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }
}
