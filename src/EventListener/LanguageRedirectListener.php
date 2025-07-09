<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Exception\RedirectResponseException;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

class LanguageRedirectListener
{
    private array $supportedLanguages = [];
    private string $defaultLanguage = 'en';

    public function __construct()
    {
        $this->supportedLanguages = Settings::getLanguages();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        $cookielang = $request->cookies->get('lang', $this->defaultLanguage);
        $path = $request->getPathInfo();

        if (str_contains($path, '/contao/')) {
            return; // Skip Contao backend requests
        }

        // Check if the path starts with a supported language (e.g., /en, /de)
        $segments = explode('/', ltrim($path, '/'));
        $langInUrl = $segments[0] ?? '';

        if (in_array($langInUrl, array_keys($this->supportedLanguages), true)) {
            // If the language in URL is different from the cookie, replace it and redirect
            if ($langInUrl !== $cookielang) {
                $remainingSegments = array_slice($segments, 1);
                $newPath = '/' . $cookielang;
                if (!empty($remainingSegments)) {
                    $newPath .= '/' . implode('/', $remainingSegments);
                }
                $response = new RedirectResponse($newPath);
                $response->headers->setCookie(
                    new Cookie('lang', $cookielang, strtotime('+1 year'), '/')
                );
                $event->setResponse($response);
                return;
            }

            // Strip the language from the URL and set the cookie
            $request->attributes->set('language_prefix', $langInUrl);
            setcookie('lang', $langInUrl, time() + 365 * 24 * 60 * 60, '/');

            // Remove the language prefix from the path, keep the rest
            $remainingSegments = array_slice($segments, 1);
            $newPath = '/' . ltrim(implode('/', $remainingSegments), '/');
            $this->overridePathInfo($request, $newPath);
            return;
        } else {
            // Add the cookie language to the URL and redirect
            $redirectUrl = '/' . $cookielang . $path;
            $response = new RedirectResponse($redirectUrl);
            $response->headers->setCookie(
                new Cookie('lang', $cookielang, strtotime('+1 year'), '/')
            );
            $event->setResponse($response);
            return;
        }
    }

    private function overridePathInfo(Request $request, string $newPath): void
    {
        $refObject = new \ReflectionObject($request);
        if ($refObject->hasProperty('pathInfo')) {
            $prop = $refObject->getProperty('pathInfo');
            $prop->setAccessible(true);
            $prop->setValue($request, $newPath);
        }

        $request->server->set('REQUEST_URI', $newPath);
    }
}
