<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\PageModel;
use Contao\Environment;
use Contao\System;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\TranslationSettingsRegistry;

class OutputFrontendTemplateListener
{
    private TranslationSettingsRegistry $registry;
    private string $DEEPL_KEY;

    public function __construct(TranslationSettingsRegistry $registry)
    {
        $this->registry = $registry;
        $this->DEEPL_KEY = $this->registry->getKey() ?? "";
    }

    public function modifyTemplate(string $buffer, string $template): string
    {
        if ($template === 'fe_page' && !empty($this->DEEPL_KEY)) {
            $enabledLanguages = $this->registry->getEnabledLanguages();
            $originalLanguage = $this->registry->getOriginalLanguage();

            $request = System::getContainer()->get('request_stack')->getCurrentRequest();
            $domain = $request->getSchemeAndHttpHost();
            $lang = $request->attributes->get('language_prefix') ?? $originalLanguage;

            $buffer = $this->addLanghref($domain, $enabledLanguages, $buffer);

            if (empty($enabledLanguages) || !$this->languageEnabled($lang, $enabledLanguages)) {
                return $buffer;
            }

            if ($lang && $lang != $originalLanguage) {
                global $objPage;
                $page_id = $objPage->id;

                $dom = new \DOMDocument();
                @$dom->loadHTML($buffer);
                $xpath = new \DOMXPath($dom);
                $nodes = $xpath->query('//text()');
                $linkNodes = $xpath->query('//a[@href]');
                $hrefs = [];

                foreach ($linkNodes as $linkNode) {
                    $href = $linkNode->getAttribute('href');
                    if ($href) {
                        if (!str_contains($href, 'http')) {
                            $href = str_replace($lang . '/', '', $href);
                            $href = $lang . '/' . $href;
                        } else if ($href == Environment::get('base')) {
                            $href = Environment::get('base') . $lang . '/';
                        } else if (str_starts_with($href, Environment::get('base'))) {
                            $href = str_replace(Environment::get('base'), Environment::get('base') . $lang . '/', $href);
                        }
                        $hrefs[] = $href;
                    }
                }

                foreach ($linkNodes as $index => $linkNode) {
                    if (isset($hrefs[$index])) {
                        $linkNode->setAttribute('href', $hrefs[$index]);
                    }
                }

                $inputNodes = $xpath->query('//input[@placeholder] | //textarea[@placeholder]');
                foreach ($inputNodes as $inputNode) {
                    $placeholder = $inputNode->getAttribute('placeholder');
                    if ($placeholder) {
                        $translatedPlaceholder = TranslationController::translateText($placeholder, $lang, $originalLanguage, $page_id, $this->DEEPL_KEY);
                        $inputNode->setAttribute('placeholder', $translatedPlaceholder);
                    }
                }

                foreach ($nodes as $node) {
                    $parentNode = $node->parentNode;
                    $notranslate = false;

                    while ($parentNode && $parentNode->nodeName !== 'html') {
                        if ($parentNode->hasAttribute('class') && strpos($parentNode->getAttribute('class'), 'notranslate') !== false) {
                            $notranslate = true;
                            break;
                        }

                        $parentNode = $parentNode->parentNode;
                    }

                    if ($notranslate) {
                        continue;
                    }

                    if ($node->parentNode->nodeName !== 'script' && $node->parentNode->nodeName !== 'style') {

                        if ($node->parentNode->nodeName === 'span' || $node->parentNode->nodeName === 'strong') {
                            $nodeValue = '#markup#' . $nodeValue . '#markup#';
                        }

                        if (preg_match('/[A-Za-z]/', $node->nodeValue)) {
                            $numbers = [];
                            $nodeValue = preg_replace_callback('/\d+/', function ($matches) use (&$numbers) {
                                $numbers[] = $matches[0];
                                return '###';
                            }, $node->nodeValue);

                            $emails = [];
                            $nodeValue = preg_replace_callback('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', function ($matches) use (&$emails) {
                                $emails[] = $matches[0];
                                return '#email#';
                            }, $nodeValue);

                            $translatedText = TranslationController::translateText($nodeValue, $lang, $originalLanguage, $page_id, $this->DEEPL_KEY);

                            $translatedText = preg_replace_callback('/###/', function () use (&$numbers) {
                                return array_shift($numbers);
                            }, $translatedText);

                            $translatedText = preg_replace_callback('/#email#/', function () use (&$emails) {
                                return array_shift($emails);
                            }, $translatedText);

                            $node->nodeValue = $translatedText;
                        }
                    }
                }

                $htmlElement = $dom->getElementsByTagName('html')->item(0);
                if ($htmlElement) {
                    $htmlElement->setAttribute('lang', $lang);
                }

                $buffer = $dom->saveHTML();
            }
        }

        return $buffer;
    }

    private function languageEnabled(string $lang, array $languagesArr): bool
    {
        return isset($languagesArr[$lang]);
    }

    private function addLanghref($domain, $enabledLanguages, $buffer)
    {
        $hreflangLinks = ['<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(rtrim($domain, '/') . '/') . '">'];

        foreach ($enabledLanguages as $code => $label) {
            $href = rtrim($domain, '/') . '/' . $code . '/';
            $hreflangLinks[] = '<link rel="alternate" hreflang="' . htmlspecialchars($code) . '" href="' . htmlspecialchars($href) . '">';
        }
        $hreflangLinks = implode("\n", $hreflangLinks);
        $buffer = str_replace('</head>', $hreflangLinks . '</head>', $buffer);

        return $buffer;
    }
}
