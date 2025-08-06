<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\PageModel;
use Contao\Environment;
use Contao\System;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;

class ModifyFrontendPageListener
{
    private string $DEEPL_KEY;
    private Config $config;

    public function __construct()
    {
        $this->config = new Config();
        $this->DEEPL_KEY = $this->config->getDeeplKey() ?? "";
    }

    public function modifyTemplate(string $buffer, string $template): string
    {
        if ($template === 'fe_page' && !empty($this->DEEPL_KEY)) {
            $enabledLanguages = $this->config->getEnabledLanguages();
            $originalLanguage = $this->config->getOriginalLanguage();
            $showInUrl = $this->config->getShowInUrl();

            $request = System::getContainer()->get('request_stack')->getCurrentRequest();
            $domain = $request->getSchemeAndHttpHost();
            $pathInfo = $request->getPathInfo();
            $lang = $request->attributes->get('lang_code') ?? $originalLanguage;

            if ($showInUrl) {
                $buffer = $this->addLanghref($domain, $pathInfo, $enabledLanguages, $buffer);
            }

            if (empty($enabledLanguages) || !in_array($lang, $enabledLanguages)) {
                return $buffer;
            }

            if ($lang && $lang != $originalLanguage) {
                global $objPage;
                $page_id = $objPage->id;

                $dom = new \DOMDocument();
                @$dom->loadHTML($buffer);
                $xpath = new \DOMXPath($dom);
                $nodes = $xpath->query('//text()');
                $linkNodes = $xpath->query('//a[@href] | //*[@data-href]');
                $hrefs = [];

                $addToUrl = $showInUrl && $lang !== $originalLanguage;
                foreach ($linkNodes as $linkNode) {
                    $href = $linkNode->getAttribute('href');

                    if(!$href) {
                        $href = $linkNode->getAttribute('data-href');
                        $datahref = true;
                    }

                    $title = $linkNode->getAttribute('title');

                    if ($title) {
                        $translatedTitle = TranslationController::translateText($title, $lang, $page_id);
                        $linkNode->setAttribute('title', $translatedTitle);
                    }

                    if ($href && $addToUrl) {
                        if (str_contains($href, 'http') || str_contains($href, 'mailto:') || str_contains($href, 'tel:') || str_contains($href, 'javascript:') || str_contains($href, '/api/')) {
                            continue;
                        }

                        if ($href == Environment::get('base')) {
                            $href = Environment::get('base') . $lang . '/';
                        } else if (str_starts_with($href, Environment::get('base'))) {
                            $href = str_replace(Environment::get('base'), Environment::get('base') . $lang . '/', ltrim($href, "/"));
                        } else {
                            if (!preg_match('#^/?' . preg_quote($lang, '#') . '(/|$)#', ltrim($href, "/"))) {
                                $href = $lang . '/' . ltrim($href, "/");
                            }
                        }

                        if($datahref) {
                            $linkNode->setAttribute('data-href', $href);
                        } else {
                            $linkNode->setAttribute('href', $href);
                        }
                    }
                }

                $inputNodes = $xpath->query('//input[@placeholder] | //textarea[@placeholder]');
                foreach ($inputNodes as $inputNode) {
                    $placeholder = $inputNode->getAttribute('placeholder');
                    if ($placeholder) {
                        $translatedPlaceholder = TranslationController::translateText($placeholder, $lang, $page_id);
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

                        $nodeValue = $node->nodeValue;
                        if (preg_match('/[A-Za-z]/', $nodeValue)) {
                            $numbers = [];
                            $nodeValue = preg_replace_callback('/\d+/', function ($matches) use (&$numbers) {
                                $numbers[] = $matches[0];
                                return '###';
                            }, $nodeValue);

                            $emails = [];
                            $nodeValue = preg_replace_callback('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', function ($matches) use (&$emails) {
                                $emails[] = $matches[0];
                                return '#email#';
                            }, $nodeValue);

                            $translatedText = TranslationController::translateText($nodeValue, $lang, $page_id);

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

    private function addLanghref($domain, $pathInfo, $enabledLanguages, $buffer)
    {
        $hreflangLinks = ['<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(rtrim($domain, '/') . '/' . ltrim($pathInfo, '/')) . '">'];

        foreach ($enabledLanguages as $label) {
            $href = rtrim($domain, '/') . '/' . $label . '/' . ltrim($pathInfo, '/');
            $hreflangLinks[] = '<link rel="alternate" hreflang="' . htmlspecialchars($label) . '" href="' . htmlspecialchars($href) . '">';
        }
        $hreflangLinks = implode("\n", $hreflangLinks);
        $buffer = str_replace('</head>', $hreflangLinks . '</head>', $buffer);

        return $buffer;
    }
}
