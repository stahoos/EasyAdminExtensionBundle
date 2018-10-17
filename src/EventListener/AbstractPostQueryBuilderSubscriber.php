<?php

namespace AlterPHP\EasyAdminExtensionBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

abstract class AbstractPostQueryBuilderSubscriber implements EventSubscriberInterface
{
    /**
     * @var \AlterPHP\EasyAdminExtensionBundle\Helper\ListFormFiltersHelper
     */
    protected $listFormFiltersHelper;

    /**
     * ListFormFiltersExtension constructor.
     *
     * @param \AlterPHP\EasyAdminExtensionBundle\Helper\ListFormFiltersHelper $listFormFiltersHelper
     */
    public function __construct($listFormFiltersHelper)
    {
        $this->listFormFiltersHelper = $listFormFiltersHelper;
    }

    /**
     * Called on POST_LIST_QUERY_BUILDER event.
     *
     * @param GenericEvent $event
     */
    public function onPostListQueryBuilder(GenericEvent $event)
    {
        $queryBuilder = $event->getArgument('query_builder');

        // Request filters
        if ($event->hasArgument('request')) {
            $this->applyRequestFilters($queryBuilder, $event->getArgument('request')->get('filters', []));
        }

        // List form filters
        if ($event->hasArgument(static::APPLIABLE_OBJECT_TYPE)) {
            $objectConfig = $event->getArgument(static::APPLIABLE_OBJECT_TYPE);
            if (isset($objectConfig['list']['form_filters'])) {
                $listFormFiltersForm = $this->listFormFiltersHelper->getListFormFilters($objectConfig['list']['form_filters']);
                if ($listFormFiltersForm->isSubmitted() && $listFormFiltersForm->isValid()) {
                    $this->applyFormFilters($queryBuilder, $listFormFiltersForm->getData());
                }
            }
        }
    }

    /**
     * Called on POST_SEARCH_QUERY_BUILDER event.
     *
     * @param GenericEvent $event
     */
    public function onPostSearchQueryBuilder(GenericEvent $event)
    {
        $queryBuilder = $event->getArgument('query_builder');

        if ($event->hasArgument('request')) {
            $this->applyRequestFilters($queryBuilder, $event->getArgument('request')->get('filters', []));
        }
    }

    protected function filterEasyadminAutocompleteValue($value)
    {
        if (!\is_array($value) || !isset($value['autocomplete']) || 1 !== \count($value)) {
            return $value;
        }

        return $value['autocomplete'];
    }
}
