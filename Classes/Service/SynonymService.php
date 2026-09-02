<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SynonymService
 *
 * Fetches synonyms from the Solr Managed Resources API so the
 * PHP-side search in pn_category_filter can expand search terms
 * with their configured synonyms.
 *
 * Synonyms are stored inside Solr (not in the TYPO3 database).
 * They are managed via the EXT:solr backend module
 * "Search > Core Optimization > Synonyms" and persisted as a
 * Managed Resource on the Solr server at:
 *   schema/analysis/synonyms/<managedResourceId>
 *
 * Format returned by getSynonyms():
 *   [
 *     'cursus'  => ['opleiding', 'training', 'workshop'],
 *     'kosten'  => ['prijs', 'tarief', 'uitgaven'],
 *     ...
 *   ]
 *
 * PERFORMANCE:
 * - Synonyms are cached via TYPO3's 'cache_pncategoryfilter' cache for 15 minutes.
 * - An in-process array acts as a second layer so multiple calls within one
 *   request never hit the cache backend at all.
 * - When Solr is unavailable an empty array is returned immediately (no exception).
 */
class SynonymService
{
    /**
     * Cache lifetime in seconds (15 minutes).
     * Synonyms rarely change; a short TTL is enough to pick up updates quickly.
     */
    private const int CACHE_TTL = 900;

    private const string CACHE_TAG = 'solr_synonyms';

    /**
     * In-process cache — avoids hitting the cache backend more than once per request.
     *
     * @var array<string, array<string, string[]>>
     */
    private array $runtimeCache = [];

    private ?FrontendInterface $cache = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Return all synonyms for the given site and language.
     *
     * Returns an array keyed by the base word, each value being a list of synonyms.
     * Returns an empty array when Solr is unavailable or EXT:solr is not installed.
     *
     * @param Site $site     Current TYPO3 site
     * @param int  $language Language UID (default 0)
     * @return array<string, string[]>
     */
    public function getSynonymsForSite(Site $site, int $language = 0): array
    {
        if (!class_exists(ConnectionManager::class)) {
            return [];
        }

        // Resolve the Solr connection first so we can key the cache on the core name.
        // Multiple sites that share the same Solr core (the common
        // single-core setup) will then share one cache entry instead of storing identical
        // data N times.
        // Read the core name from the read endpoint directly — no need to build the full
        // AdminService just to get the cache key.
        try {
            /** @var ConnectionManager $connectionManager */
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $connection = $connectionManager->getConnectionByTypo3Site($site, $language);
            $coreName = $connection->getEndpoint('read')->getCore();
        } catch (\Throwable $e) {
            $this->logger->warning('SynonymService: could not resolve Solr connection', [
                'site' => $site->getIdentifier(),
                'language' => $language,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }

        $cacheKey = 'synonyms_' . md5($coreName);

        // Layer 1: in-process runtime cache (free — no I/O at all)
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        // Layer 2: TYPO3 persistent cache (typically APCu/Redis/file — very fast)
        $persistentCache = $this->getCache();
        if ($persistentCache !== null) {
            $cached = $persistentCache->get($cacheKey);
            if (is_array($cached)) {
                $this->runtimeCache[$cacheKey] = $cached;
                return $cached;
            }
        }

        // Layer 3: live Solr HTTP call — reuse the connection resolved above,
        // no second getConnectionByTypo3Site() call needed.
        try {
            $synonyms = $connection->getAdminService()->getSynonyms();
        } catch (\Throwable $e) {
            $this->logger->warning('SynonymService: could not fetch synonyms from Solr', [
                'site' => $site->getIdentifier(),
                'language' => $language,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }

        if ($persistentCache !== null) {
            $persistentCache->set($cacheKey, $synonyms, [self::CACHE_TAG], self::CACHE_TTL);
        }

        $this->runtimeCache[$cacheKey] = $synonyms;

        return $synonyms;
    }

    /**
     * Expand a single search term with its synonyms.
     *
     * Given 'cursus' and a synonym map that contains
     * 'cursus' => ['opleiding', 'training', 'workshop'],
     * this returns ['cursus', 'opleiding', 'training', 'workshop'].
     *
     * The original word is always included as the first element.
     *
     * @param string                  $word       The search word (already lowercase)
     * @param array<string, string[]> $synonymMap Result of getSynonymsForSite()
     * @return string[]
     */
    public function expandWordWithSynonyms(string $word, array $synonymMap): array
    {
        $terms = [$word];

        if (isset($synonymMap[$word])) {
            foreach ($synonymMap[$word] as $synonym) {
                $synonym = mb_strtolower(trim($synonym), 'UTF-8');
                if ($synonym !== '' && !in_array($synonym, $terms, true)) {
                    $terms[] = $synonym;
                }
            }
        }

        return $terms;
    }

    /**
     * Returns the TYPO3 cache frontend for synonym storage.
     * Falls back to null when the cache is not configured (graceful degradation).
     */
    private function getCache(): ?FrontendInterface
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            /** @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $this->cache = $cacheManager->getCache('pn_category_filter');
        } catch (\Throwable) {
            // Cache not configured — run without persistent caching
            return null;
        }

        return $this->cache;
    }
}
