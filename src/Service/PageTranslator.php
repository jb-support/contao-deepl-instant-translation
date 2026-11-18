<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Service;

use DOMDocument;
use DOMXPath;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\UrlTranslationsConfig;

class PageTranslator
{
    private $config;
    private $dom;
    private $xpath;

    private $batch = [];
    private $nodeMap = [];

    private $inlineTags = [
        'a',
        'abbr',
        'acronym',
        'b',
        'bdo',
        'big',
        'br',
        'cite',
        'code',
        'dfn',
        'em',
        'i',
        'kbd',
        'q',
        'samp',
        'small',
        'span',
        'strong',
        'sub',
        'sup',
        'time',
        'tt',
        'var',
        '#text'
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function translatePageContent(string $html, string $lang, int $pageId, string $provider): string
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $this->dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $this->xpath = new DOMXPath($this->dom);

        $this->prepareBatch();

        $translatedBatch = TranslationController::translateBatch(
            $this->batch,
            $lang,
            $pageId,
            $provider
        );

        $this->applyTranslations($translatedBatch);

        $this->processLocalLinks($lang);

        $htmlElement = $this->dom->getElementsByTagName('html')->item(0);
        if ($htmlElement) {
            $htmlElement->setAttribute('lang', $lang);
        }

        return $this->dom->saveHTML();
    }

    private function prepareBatch(): void
    {
        $metaQueries = [
            '//meta[@name="description"]',
            '//meta[@property="og:description"]',
            '//meta[@name="twitter:description"]',
            '//meta[@property="og:title"]',
            '//meta[@name="twitter:title"]'
        ];

        foreach ($this->xpath->query('//meta[@name="robots"]') as $node) {
            $node->setAttribute('content', 'noindex,nofollow');
        }

        foreach ($this->xpath->query('//link[@rel="canonical"]') as $node) {
            $node->parentNode->removeChild($node);
        }

        foreach ($metaQueries as $query) {
            foreach ($this->xpath->query($query) as $node) {
                $this->addToBatch($node->getAttribute('content'), [
                    'type' => 'attr',
                    'node' => $node,
                    'attr' => 'content'
                ]);
            }
        }

        foreach ($this->xpath->query('//title') as $node) {
            $this->addToBatch($node->textContent, [
                'type' => 'prop',
                'node' => $node,
                'prop' => 'textContent'
            ]);
        }

        $attrQueries = ['//input[@placeholder]' => 'placeholder', '//textarea[@placeholder]' => 'placeholder', '//img[@alt]' => 'alt'];
        foreach ($attrQueries as $query => $attr) {
            foreach ($this->xpath->query($query) as $node) {
                $this->addToBatch($node->getAttribute($attr), [
                    'type' => 'attr',
                    'node' => $node,
                    'attr' => $attr
                ]);
            }
        }

        foreach ($this->xpath->query('//a[@title]') as $node) {
            $this->addToBatch($node->getAttribute('title'), [
                'type' => 'attr',
                'node' => $node,
                'attr' => 'title'
            ]);
        }

        $body = $this->dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $this->extractTranslationUnits($body);
        }
    }

    private function extractTranslationUnits($node): void
    {
        $ignoredTags = ['script', 'style', 'code', 'noscript', 'iframe', 'svg'];

        if (in_array(strtolower($node->nodeName), $ignoredTags)) return;

        if ($node->nodeType === XML_ELEMENT_NODE && $node->hasAttribute('class')) {
            if (strpos($node->getAttribute('class'), 'notranslate') !== false) return;
        }

        if ($this->isLeafBlock($node)) {
            $innerHtml = $this->getInnerHtml($node);
            if (trim(strip_tags($innerHtml)) !== '') {
                $this->addToBatch($innerHtml, [
                    'type' => 'html_block',
                    'node' => $node
                ]);
            }
            return;
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $this->extractTranslationUnits($child);
                } elseif ($child->nodeType === XML_TEXT_NODE) {
                    $text = trim($child->nodeValue);
                    if (!empty($text)) {
                        $this->addToBatch($text, [
                            'type' => 'text_node',
                            'node' => $child
                        ]);
                    }
                }
            }
        }
    }

    private function addToBatch(string $text, array $context): void
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $squashed = preg_replace('/[\p{Z}\s]+/u', '', $stripped);

        if ($squashed === '') {
            return;
        }

        $alphaOnly = preg_replace('/[\d\s\p{P}\p{S}]/u', '', $stripped);
        $alphaOnly = preg_replace('/\b(h|m|min|s|kg|g|mg|l|ml|km|cm|mm|€)\b/ui', '', $alphaOnly);

        if (trim($alphaOnly) === '') {
            return;
        }

        $extractedNumbers = [];

        if ($context['type'] === 'html_block') {
            $tempDoc = new DOMDocument();
            @$tempDoc->loadHTML('<?xml encoding="utf-8" ?><div>' . $text . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $tempXpath = new DOMXPath($tempDoc);

            foreach ($tempXpath->query('.//text()') as $textNode) {
                $maskedValue = preg_replace_callback('/\d+(?:[.,]\d+)?/', function ($matches) use (&$extractedNumbers) {
                    $extractedNumbers[] = $matches[0];
                    return '@@NUM@@';
                }, $textNode->nodeValue);

                $textNode->nodeValue = $maskedValue;
            }

            $text = "";
            foreach ($tempDoc->getElementsByTagName('div')->item(0)->childNodes as $child) {
                $text .= $tempDoc->saveHTML($child);
            }

            $text = str_replace('@@NUM@@', '<num />', $text);
        } else {
            $text = preg_replace_callback('/\d+(?:[.,]\d+)?/', function ($matches) use (&$extractedNumbers) {
                $extractedNumbers[] = $matches[0];
                return '<num />';
            }, $text);
        }

        $context['extracted_numbers'] = $extractedNumbers;

        if ($context['type'] === 'attr' && $context['attr'] === 'title') {
            if (preg_match('/als\s+(.+?)\s+in\s+(.+)/i', $text, $matches)) {
                $text = str_replace($matches[1], '<job />', $text);
                $text = str_replace($matches[2], '<city />', $text);
            }
        }

        $id = uniqid('t_');
        $this->batch[$id] = $text;
        $this->nodeMap[$id] = $context;
    }

    private function applyTranslations(array $translatedBatch): void
    {
        // Build an allowed tags string for strip_tags (e.g., "<a><abbr><b>...<num>")
        $allowedTagsList = array_diff($this->inlineTags, ['#text']);
        $allowedTagsList[] = 'num'; // Allow your custom number placeholder
        $allowedTags = '<' . implode('><', $allowedTagsList) . '>';

        foreach ($translatedBatch as $id => $translatedText) {
            if (!isset($this->nodeMap[$id])) continue;

            $context = $this->nodeMap[$id];
            $node = $context['node'];
            $type = $context['type'];

            if (!empty($context['extracted_numbers'])) {
                foreach ($context['extracted_numbers'] as $num) {
                    $translatedText = preg_replace('/<num \/>/', $num, $translatedText, 1);
                }
            }

            if ($type === 'attr') {
                $cleanText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5);
                $node->setAttribute($context['attr'], $cleanText);
            } elseif ($type === 'prop') {
                $cleanText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5);
                $node->{$context['prop']} = $cleanText;
            } elseif ($type === 'text_node') {
                $node->nodeValue = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5);
            } elseif ($type === 'html_block') {
                while ($node->hasChildNodes()) {
                    $node->removeChild($node->firstChild);
                }

                // XSS PREVENTION: Strip any tags that DeepL might have unescaped,
                // allowing ONLY the safe inline tags defined in your class.
                $safeTranslatedText = strip_tags($translatedText, $allowedTags);

                $tempDoc = new DOMDocument();
                $htmlWrapper = '<?xml encoding="utf-8" ?><div>' . $safeTranslatedText . '</div>';

                $previousState = libxml_use_internal_errors(true);
                $tempDoc->loadHTML($htmlWrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                libxml_use_internal_errors($previousState);

                $container = $tempDoc->getElementsByTagName('div')->item(0);
                if ($container && $container->hasChildNodes()) {
                    foreach ($container->childNodes as $child) {
                        $importedNode = $this->dom->importNode($child, true);
                        $node->appendChild($importedNode);
                    }
                } else {
                    $node->nodeValue = $safeTranslatedText;
                }
            }
        }
    }

    private function isLeafBlock($node): bool
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return false;
        }

        if (!$node->hasChildNodes()) {
            return false;
        }

        $descendants = $node->getElementsByTagName('*');
        foreach ($descendants as $desc) {
            if (!in_array(strtolower($desc->nodeName), $this->inlineTags)) {
                return false;
            }
        }

        $hasNonEmptyText = false;
        $elementCount = 0;

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $elementCount++;
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->nodeValue) !== '') {
                    $hasNonEmptyText = true;
                }
            }
        }

        if ($elementCount === 0) {
            return false;
        }

        if ($elementCount === 1 && !$hasNonEmptyText) {
            return false;
        }

        return true;
    }

    private function getInnerHtml($node): string
    {
        $innerHTML = "";

        foreach ($node->childNodes as $child) {
            $innerHTML .= $this->dom->saveHTML($child);
        }

        $innerHTML = preg_replace('/\s+/', ' ', $innerHTML);

        return trim($innerHTML);
    }

    private function processLocalLinks(string $lang): void
    {
        $nodes = $this->xpath->query('//form[@action] | //a[@href] | //*[@data-href]');
        foreach ($nodes as $node) {
            if ($node->hasAttribute('class') && strpos($node->getAttribute('class'), 'notranslate') !== false) continue;

            if ($node->nodeName === 'a' || $node->hasAttribute('data-href')) {
                $attr = $node->hasAttribute('href') ? 'href' : 'data-href';
                $url = $node->getAttribute($attr);
                if (!$this->shouldSkipUrl($url, $lang)) {
                    $url = $this->addLangInSlug($url, $lang);
                    $node->setAttribute($attr, $url);
                }
            } elseif ($node->nodeName === 'form') {
                $url = $node->getAttribute('action');
                if ($url) $node->setAttribute('action', $this->addLangInSlug($url, $lang));
            }
        }
    }

    private function shouldSkipUrl($url, $lang): bool
    {
        return str_starts_with($url, '#') ||
            str_contains($url, 'http') ||
            str_contains($url, 'mailto:') ||
            str_contains($url, 'tel:') ||
            str_contains($url, 'javascript:') ||
            str_contains($url, '/api/') ||
            str_starts_with($url, $lang . '/');
    }

    private function addLangInSlug($url, $lang): string
    {
        // $config = new UrlTranslationsConfig();
        // $translations = $config->getUrlTranslations();
        // $newUrl = array_key_exists($url, $translations) ? $translations[$url] : $url;

        // if($_GET["dev"] && $newUrl !== $url) {
        //     dd($url, $newUrl);
        // }

        return '/' . $lang . '/' . ltrim($url, '/');
    }
}
