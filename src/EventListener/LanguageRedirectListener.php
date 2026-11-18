<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\UrlTranslationsConfig;

class LanguageRedirectListener implements EventSubscriberInterface
{
    private Config $config;
    private UrlTranslationsConfig $urlTranslationsConfig;

    public function __construct()
    {
        $this->config = new Config();
        $this->urlTranslationsConfig = new UrlTranslationsConfig();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority to catch the request before routing happens
            KernelEvents::REQUEST => ['onKernelRequest', 32],
            // Standard priority to attach cookies to the outgoing response
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->config->getDisabled() || empty($this->config)) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (str_starts_with($pathInfo, '/contao') || str_starts_with($pathInfo, '/api')) {
            return;
        }

        if ($request->query->has('dev')) {
            $request->attributes->set('_set_dev_cookie', (bool) $request->query->get('dev'));
        }
        if ($request->query->has('nodev')) {
            $request->attributes->set('_remove_dev_cookie', true);
        }

        $originalLang = $this->config->getOriginalLanguage();
        $enabledLanguages = $this->config->getEnabledLanguages();
        $showInUrl = $this->config->getShowInUrl();

        $agentLanguage = null;
        if (!$request->cookies->has('lang_code') && $this->config->getRedirectAgent()) {
            $agentLanguage = $request->getPreferredLanguage($enabledLanguages);
            if ($agentLanguage) {
                $request->attributes->set('_set_agent_cookie', $agentLanguage);
            }
        }

        $segments = explode('/', trim($pathInfo, '/'));
        $langInUrl = (isset($segments[0]) && preg_match('/^[a-z]{2}$/', $segments[0])) ? $segments[0] : '';

        $langNew = $agentLanguage ?: $request->request->get('lang');

        if (!$showInUrl) {
            $resolvedLang = $langNew ?: $request->cookies->get('lang_code', $originalLang);
            $request->attributes->set('lang_code', $resolvedLang);
            if ($langNew) {
                $request->attributes->set('_set_lang_cookie', $resolvedLang);
            }
            return;
        }

        $lang = $langNew ?: ($langInUrl ?: ($agentLanguage ?: $originalLang));

        if ($lang === $originalLang) {
            $request->attributes->set('lang_code', $lang);
            $request->attributes->set('_set_lang_cookie', $lang);

            if (!empty($langInUrl) && $langInUrl === $originalLang) {
                $newPath = substr($pathInfo, 3) ?: '/'; // strips "/en"
                $event->setResponse(new RedirectResponse($newPath));
            }
            return;
        }

        if (in_array($lang, $enabledLanguages)) {
            $request->attributes->set('_set_lang_cookie', $lang);

            if ($langInUrl !== $lang) {
                $basePath = !empty($langInUrl) ? substr($pathInfo, 3) : $pathInfo;
                $newUrl = '/' . $lang . '/' . ltrim($basePath, '/');
                $event->setResponse(new RedirectResponse(rtrim($newUrl, '/')));
                return;
            }

            $request->attributes->set('lang_code', $lang);


            $newReqPath = substr($pathInfo, 3) ?: '/';
            $request->server->set('REQUEST_URI', $newReqPath);
        }
    }

    /**
     * This event fires just before the response is sent to the user.
     * We use it to set all the cookies we staged in the Request attributes.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $cookieExpiry = time() + (3600 * 24 * 30);

        if ($request->attributes->has('_set_dev_cookie')) {
            $val = $request->attributes->get('_set_dev_cookie') ? '1' : '';
            $response->headers->setCookie(Cookie::create('dev', $val, $cookieExpiry, '/'));
        }

        if ($request->attributes->has('_remove_dev_cookie')) {
            $response->headers->clearCookie('dev', '/');
        }

        if ($request->attributes->has('_set_agent_cookie')) {
            $response->headers->setCookie(Cookie::create('agent_language', $request->attributes->get('_set_agent_cookie'), $cookieExpiry, '/'));
        }

        if ($request->attributes->has('_set_lang_cookie')) {
            $response->headers->setCookie(Cookie::create('lang_code', $request->attributes->get('_set_lang_cookie'), $cookieExpiry, '/'));
        }
    }
}
