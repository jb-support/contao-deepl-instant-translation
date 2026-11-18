<?php
namespace JBSupport\ContaoDeeplInstantTranslationBundle\Service;

use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\UrlTranslationsConfig;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\Framework\ContaoFramework;

class UrlIndexerService
{
    private ContaoFramework $framework;
    private UrlTranslationsConfig $urlTranslationsConfig;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->urlTranslationsConfig = new UrlTranslationsConfig();
    }

    public function crawl(): array
    {
        $this->framework->initialize();
        $pages = PageTranslationModel::findAll();

        if (!$pages) {
            return ['error' => 'No pages found in the database.'];
        }

        $fortranslate = [];
        foreach($pages as $page) {
            $fortranslate[$page->alias] = $page->alias;
        }

        foreach($fortranslate as $alias) {
            $translatedAlias = TranslationController::fetchGPTAliasTranslation($alias);
            $fortranslate[$alias] = $translatedAlias;
        }

        foreach($pages as $page) {
            $translatedAlias = $fortranslate[$page->alias] ?? $page->alias;
            $page->alias_translation = $translatedAlias;
            $page->save();
        }

        $this->urlTranslationsConfig->saveUrlTranslations($fortranslate);
        return ['urls' => $pages->count()];
    }
}