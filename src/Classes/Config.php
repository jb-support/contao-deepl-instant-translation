<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Classes;

class Config
{
    private const FIELDS = [
        'deepl_key',
        'original_language',
        'languages',
        'show_modal',
        'in_url',
        'agent_redirect',
        'element_type',
        'element_label_type',
    ];

    private array $config;
    private string $configPath;

    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 5) . '/system/config/translation_config.php';

        if (file_exists($this->configPath)) {
            $this->config = @include($this->configPath);
        } else {
            $this->config = [];
        }
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

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    public function getApiUrl(): string
    {
        return $this->getIsProPlan() ? "https://api.deepl.com/v2/translate" : "https://api-free.deepl.com/v2/translate";
    }

    public function getRedirectAgent(): bool
    {
        return $this->config['agent_redirect'] ?? false;
    }

    public function getFields(): array
    {
        return self::FIELDS;
    }
}
