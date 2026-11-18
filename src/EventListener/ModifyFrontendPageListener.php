<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;


use DOMDocument;
use DOMXPath;
use Contao\PageModel;
use Contao\Environment;
use Contao\System;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Service\PageTranslator;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;


class ModifyFrontendPageListener
{
    private Config $config;

    private $dom;
    private $xpath;

    private $batch = [];
    private $nodeMap = [];
    private $restorationMap = [];

    public function __construct()
    {
        $this->config = new Config();
    }

    public function modifyTemplate(string $buffer, string $template): string
    {
        $buffer = mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8');

        if ($template != "fe_page" || $this->config->getDisabled()) {
            return $buffer;
        }

        $enabledLanguages = $this->config->getEnabledLanguages();
        $originalLanguage = $this->config->getOriginalLanguage();
        $showInUrl = $this->config->getShowInUrl();
        $translationProvider = $this->config->getTranslationProvider();

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        $lang = $request->attributes->get('lang_code') ?? $originalLanguage;

        $existingLanguages = [];
        $translations = PageTranslationModel::findBy(['alias = ?'], [$request->getPathInfo()]);
        if ($translations) {
            $languages = $translations->fetchEach("language");
            array_unshift($languages, $originalLanguage);
            $showLanguages = array_intersect($enabledLanguages, $languages);
        }

        if (!$lang || empty($enabledLanguages) || !in_array($lang, $enabledLanguages) || $lang == $originalLanguage ) {
            return $buffer;
        }

        global $objPage;
        $pageId = $objPage->id;

        $translator = new PageTranslator($this->config);
        $translatedHtml = $translator->translatePageContent($buffer, $lang, $pageId, $translationProvider);

        $alias = $objPage->alias != "index" ? "/" . $objPage->alias : "/";

        $translation = PageTranslationModel::findOneBy(['alias = ? AND language = ?'], [$alias, $lang]);
        if (!$translation) {
            $translation = new PageTranslationModel();
            $translation->alias = $alias;
            $translation->language = $lang;
            $hash = hash('md5', $translatedHtml);
            $translation->hash = $hash;
            $translation->translated = "1";
        }

        $translation->tstamp = time();
        $translation->save();

        $aliasTranslation = TranslationController::translateAlias($translation);

        return $translatedHtml;
    }
}
