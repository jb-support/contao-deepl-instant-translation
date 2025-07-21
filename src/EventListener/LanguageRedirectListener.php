<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\TranslationSettingsRegistry;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Doctrine\DBAL\Connection;

class LanguageRedirectListener
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ensure this is the main request and not a sub-request
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        $settings = $this->getModuleSettings($this->connection->createQueryBuilder());
        $originalLang = $settings['original_language'];
        $enabledLanguages = $settings['enabled_languages'];

        //GET parameter > string in url > original language

        $langNew = $request->request->get('lang');
        $langInUrl = explode('/', $pathInfo)[1];
        $langInUrl = preg_match('/^[a-z]{2}$/', $langInUrl) ? $langInUrl : '';

        $lang = $originalLang;
        $strip = false;

        if ($langNew && preg_match('/^[a-z]{2}$/', $langNew)) {
            $lang = $langNew;
            $strip = true;
        } else if ($langInUrl && preg_match('/^[a-z]{2}$/', $langInUrl)) {
            $lang = $langInUrl;
            $strip = true;
        }

        $maybeLang = substr($pathInfo, 1, 2);
        if ($maybeLang == $lang && $maybeLang != $originalLang) {
            $strip = true;
        }


        if ($lang == $originalLang && !empty($langInUrl)) {
            $request->attributes->set('language_prefix', $lang);
            if ($langInUrl !== $lang) {
                $newPath = preg_replace('/^\/[a-z]{2}\//', '/' . $lang . '/', $pathInfo, 1);
            } else if ($langInUrl == $originalLang) {
                $newPath = substr($pathInfo, strlen($langInUrl) + 1);
                if ($newPath === '') {
                    $newPath = '/';
                }
            }

            $response = new RedirectResponse($newPath);
            $event->setResponse($response);
            return;
        } else if ($lang == $originalLang && empty($langInUrl)) {
            $request->attributes->set('language_prefix', $lang);
            return;
        }


        $newReqPath = $pathInfo;
        if (in_array($lang, $enabledLanguages)) {
            if ($strip) {
                $newReqPath = substr($pathInfo, strlen($lang) + 1);
                if ($newReqPath === '') {
                    $newReqPath = '/';
                }

                $request->server->set('REQUEST_URI', $newReqPath);
                $request->server->set('PATH_INFO', $newReqPath);
                $request->server->set('ORIGINAL_PATH_INFO', $pathInfo);
            }

            if ($langInUrl !== $lang) {
                $newUrl = preg_replace(
                    '#^/' . preg_quote($langInUrl, '#') . '(?=/|$)#',
                    '/' . $lang,
                    $pathInfo,
                    1
                );

                if (strpos($newUrl, '/' . $lang . '/') !== 0) {
                    $newUrl = '/' . $lang . '/' . ltrim($newUrl, '/');
                }

                $response = new RedirectResponse($newUrl);
                $event->setResponse($response);
                return;
            }

            $request->attributes->set('language_prefix', $lang);

            $reflection = new \ReflectionObject($request);
            if ($reflection->hasProperty('pathInfo')) {
                $property = $reflection->getProperty('pathInfo');
                $property->setAccessible(true);
                $property->setValue($request, $newReqPath);
            }

            return;
        }
    }

    private function getModuleSettings($qb)
    {
        if (!isset($_COOKIE['original_language']) || !isset($_COOKIE['enabled_languages'])) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('*')
                ->from('tl_module')
                ->where('type = :type')
                ->setParameter('type', 'language_switcher_module');

            $languageSwitcherModule = $qb->executeQuery()->fetchAssociative();

            setcookie('original_language', $languageSwitcherModule['original_language'], time() + 3600 * 24 * 30, '/');
            setcookie('enabled_languages', $languageSwitcherModule['languages'], time() + 3600 * 24 * 30, '/');

            $out = [
                'original_language' => $languageSwitcherModule['original_language'],
                'enabled_languages' => array_merge([$languageSwitcherModule['original_language']], unserialize($languageSwitcherModule['languages'])),
            ];

            return $out;
        } else {
            return [
                'original_language' => $_COOKIE['original_language'],
                'enabled_languages' => array_merge([$_COOKIE['original_language']], unserialize($_COOKIE['enabled_languages'])),
            ];
        }
    }
}
