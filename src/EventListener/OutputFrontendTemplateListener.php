<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\PageModel;
use Contao\Environment;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;
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

            $lang = $_COOKIE['lang'] ?? $originalLanguage;

            if (empty($enabledLanguages) || !$this->languageEnabled($lang, $enabledLanguages)) {
                return $buffer;
            }

            if ($lang != $originalLanguage) {
                global $objPage;
                $page_id = $objPage->id;

                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($buffer, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();

                $xpath = new \DOMXPath($dom);

                $tagsToExtract = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'label', 'button', 'td', 'th'];

                $queryParts = [];
                foreach ($tagsToExtract as $tag) {
                    $queryParts[] = "//div//{$tag}";
                }

                $queryParts[] = "//div//a[normalize-space(text()) != '']";
                $queryParts[] = "//input[@placeholder] | //textarea[@placeholder]";
                $queryParts[] = "//div//span[not(preceding-sibling::text()[normalize-space()]) and not(following-sibling::text()[normalize-space()])]";
                $queryParts[] = "//head/title";

                $htmlElement = $dom->getElementsByTagName('html')->item(0);
                if ($htmlElement) {
                    $htmlElement->setAttribute('lang', $lang);
                }

                $query = implode(' | ', $queryParts);
                $nodes = $xpath->query($query);

                foreach ($nodes as $node) {
                    $skip = false;
                    $parent = $node->parentNode;

                    while ($parent !== null && $parent->nodeType === XML_ELEMENT_NODE) {
                        if ($parent->hasAttribute('class') && strpos($parent->getAttribute('class'), 'notranslate') !== false) {
                            $skip = true;
                            break;
                        }
                        $parent = $parent->parentNode;
                    }
                    if ($skip) {
                        continue;
                    }

                    $htmlString = $dom->saveHTML($node);
                    $text = TranslationModel::translateText($htmlString, $lang, $originalLanguage, $page_id, $this->DEEPL_KEY);

                    $pattern = '/' . preg_quote($htmlString, '/') . '/i';
                    $buffer = preg_replace($pattern, $text, $buffer, 1);

                    $newNode = new \DOMDocument('1.0', 'UTF-8');
                    libxml_use_internal_errors(true);
                    $newNode->loadHTML('<?xml encoding="UTF-8">' . $text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();

                    if ($newNode->documentElement) {
                        // Import the new node into the main DOM
                        $imported = $dom->importNode($newNode->documentElement, true);
                        $node->parentNode->replaceChild($imported, $node);
                    }

                    $buffer = $dom->saveHTML($dom->documentElement);
                }

                return $buffer;
            } else return $buffer;
        }

        return $buffer;
    }

    private function languageEnabled(string $lang, array $languagesArr): bool
    {
        return isset($languagesArr[$lang]);
    }
}
