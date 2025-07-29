<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Classes;

class Config
{

    private array $config;

    public function __construct()
    {
        $this->config = @include(__DIR__.'/../../../../../config/translation_extension_config.php') ?? [];
    }

    public function getRaw($field): array
    {
        return $this->config[$field] ?? [];
    }

    public function getDeeplKey(): ?string
    {
        return $this->config['deepl_key'] ?? null;
    }
    public function getOriginalLanguage(): ?string
    {
        return $this->config['original_language'] ?? null;
    }
    public function getEnabledLanguages(): ?array
    {
        $enabledLanguages = unserialize($this->config['languages']) ?? [];
        $enabledLanguages[] = $this->getOriginalLanguage();
        return array_filter(array_unique($enabledLanguages));
    }
    public function getShowInUrl(): ?bool
    {
        return $this->config['in_url'] ?? false;
    }
    public function getIsProPlan(): ?bool
    {
        return $this->config['deepl_pro_plan'] ?? false;
    }

    public function getElementType(): ?string
    {
        return $this->config['element_type'] ?? 'select';
    }

    public function getElementLabelType(): ?string
    {
        return $this->config['element_label_type'] ?? 'long';
    }

    public function getShowModal(): ?bool
    {
        return $this->config['show_modal'] ?? false;
    }
}
