<?php
namespace JBSupport\ContaoDeeplInstantTranslationBundle\Service;

use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\Framework\ContaoFramework;

class SitemapCrawlerService
{
    private Config $config;
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->config = new Config();
        $this->framework = $framework;
    }

public function crawl(string $host): array
{
    $this->framework->initialize();
    $urls = [];
    $sitemapUrl = rtrim($host, '/') . '/sitemap.xml';

    $body = $this->fetchUrl($sitemapUrl, $httpCode);
    if ($httpCode !== 200 || empty($body)) {
        return ['error' => sprintf('Failed to fetch sitemap %s', $sitemapUrl)];
    }

    $foundUrls = $this->parseSitemap($body);
    $languages = $this->config->getEnabledLanguages();
    array_shift($languages); // Remove original language (e.g., 'de')

    $finalUrls = $foundUrls;
    $translatedCount = 0;
    $translatedLimit = 1000;
    foreach ($foundUrls as $index => $url) {
        if ($translatedCount > $translatedLimit) break;

        foreach ($languages as $lang) {
            $time = new \DateTime();
            $target = $this->injectLanguageIntoUrl($url, $lang);
            echo $time->format('Y-m-d H:i:s') . ": Crawling $target...\n";
            // --- THROTTLING ---

            $translation = PageTranslationModel::findOneBy(['alias = ? AND language = ?'], [$this->getPathFromUrl($url), $lang]);
            if($translation && $translation->tstamp > time() - 21 * 24 * 3600) { // If translation exists and is younger than 3 days, skip crawling
                echo $time->format('Y-m-d H:i:s') . ": Skipping $target (recently translated)\n";
                continue;
            } else {
                $translatedCount++;
                sleep(1);
            }

            $body = $this->fetchUrl($target, $code);
            echo $time->format('Y-m-d H:i:s') . ": Received HTTP $code for $target\n";

            if (!$translation) {
                $translation = new PageTranslationModel();
                $translation->alias = $this->getPathFromUrl($url);
                $translation->language = $lang;
                $hash = hash('md5', $body ?? '');
                $translation->hash = $hash;
                $translation->translated = "1";
            }
            $translation->tstamp = time();
            $translation->save();

            if ($code === 200) {
                $finalUrls[] = $target;
            }
        }
        echo $time->format('Y-m-d H:i:s') . ": ($translatedCount / $translatedLimit pages translated)\n";
    }

    return ['sitemap' => $sitemapUrl, 'urls' => array_unique($finalUrls)];
}

    /**
     * Correctly places the language code after the domain
     */
    private function injectLanguageIntoUrl(string $url, string $lang): string
    {
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return $scheme . $host . '/' . trim($lang, '/') . $path;
    }


    private function getPathFromUrl(string $url): string
    {
        $parts = parse_url($url);
        return $parts['path'] ?? '/';
    }


    private function fetchUrl(string $url, &$httpCode): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: timepartner-crawler/1.0']);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $body;
    }

    private function parseSitemap(string $body): array
    {
        $found = [];
        if (preg_match_all('/<loc>(.*?)<\/loc>/s', $body, $m)) {
            foreach ($m[1] as $loc) {
                $found[] = trim($loc);
            }
        }
        return $found;
    }
}