<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Service\PageTranslator;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;

class ModifyFrontendPageListener
{
    public function __construct(
        private Config $config,
        private RequestStack $requestStack,
        private PageTranslator $translator
    ) {}

    public function onOutputFrontendTemplate(string $buffer, string $template): string
    {
        if ($template !== 'fe_page' || $this->config->getDisabled()) {
            return $buffer;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $buffer;
        }

        $originalLanguage = $this->config->getOriginalLanguage();
        $lang = $request->attributes->get('lang_code', $originalLanguage);
        $enabledLanguages = $this->config->getEnabledLanguages();

        if (!$lang || empty($enabledLanguages) || !in_array($lang, $enabledLanguages, true) || $lang === $originalLanguage) {
            return $buffer;
        }


        /** @var PageModel|null $pageModel */
        $pageModel = $request->attributes->get('pageModel');
        if (!$pageModel) {
            return $buffer;
        }

        $bufferToTranslate = mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8');

        $translatedHtml = $this->translator->translatePageContent(
            $bufferToTranslate,
            $lang,
            $pageModel->id,
            $this->config->getTranslationProvider()
        );

        $alias = $pageModel->alias !== 'index' ? '/' . $pageModel->alias : '/';
        $translation = PageTranslationModel::findOneBy(['alias = ? AND language = ?'], [$alias, $lang]);

        $isNew = false;

        if (!$translation) {
            $translation = new PageTranslationModel();
            $translation->alias = $alias;
            $translation->language = $lang;
            $translation->hash = hash('md5', $translatedHtml);
            $translation->translated = "1";
            $isNew = true;
        }


        if ($isNew) {
            $translation->tstamp = time();
            $translation->save();
        }

        TranslationController::translateAlias($translation);

        return $translatedHtml;
    }
}
