<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\EventListener;

use ProudNerds\PnCategoryFilter\Event\AfterRecordsFetchedEvent;
use ProudNerds\PnCategoryFilter\Service\PageContentHydrationService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Built-in listener that enriches pages records with content extracted from related
 * tt_content rows (teaser for display, _pageContent for search).
 *
 * Opt-in via plugin.tx_pncategoryfilter.settings.pageContent.enabled (TypoScript).
 * No-op when no pages records are present or when the feature is disabled.
 */
#[AsEventListener(identifier: 'pn-category-filter/hydrate-page-content')]
final class HydratePageContent
{
    public function __construct(
        private readonly PageContentHydrationService $hydrationService,
    ) {}

    public function __invoke(AfterRecordsFetchedEvent $event): void
    {
        $settings = $event->getSettings();
        $config = $settings['pageContent'] ?? [];

        if (empty($config['enabled'])) {
            return;
        }

        if (!in_array('pages', $event->getTables(), true)) {
            return;
        }

        $records = $this->hydrationService->hydrate($event->getRecords(), $config);
        $event->setRecords($records);
    }
}
