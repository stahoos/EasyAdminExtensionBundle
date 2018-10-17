<?php

namespace AlterPHP\EasyAdminExtensionBundle\EventListener;

use AlterPHP\EasyAdminMongoOdmBundle\Event\EasyAdminMongoOdmEvents;
use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;

/**
 * Apply filters on list/search queryBuilder.
 */
class MongoOdmPostQueryBuilderSubscriber extends AbstractPostQueryBuilderSubscriber
{
    protected const APPLIABLE_OBJECT_TYPE = 'document';

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            EasyAdminMongoOdmEvents::POST_LIST_QUERY_BUILDER => ['onPostListQueryBuilder'],
            EasyAdminMongoOdmEvents::POST_SEARCH_QUERY_BUILDER => ['onPostSearchQueryBuilder'],
        ];
    }

    /**
     * Applies request filters on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param array        $filters
     */
    protected function applyRequestFilters(QueryBuilder $queryBuilder, array $filters = [])
    {
        foreach ($filters as $field => $value) {
            // Empty string and numeric keys is considered as "not applied filter"
            if (\is_int($field) || '' === $value) {
                continue;
            }
            // Checks if filter is directly appliable on queryBuilder
            if (!$this->isFilterAppliable($queryBuilder, $field)) {
                continue;
            }

            $this->filterQueryBuilder($queryBuilder, $field, $value);
        }
    }

    /**
     * Applies form filters on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param array        $filters
     */
    protected function applyFormFilters(QueryBuilder $queryBuilder, array $filters = [])
    {
        foreach ($filters as $field => $value) {
            $value = $this->filterEasyadminAutocompleteValue($value);
            // NULL, empty string and numeric keys is considered as "not applied filter"
            if (null === $value || '' === $value || \is_int($field)) {
                continue;
            }
            // Checks if filter is directly appliable on queryBuilder
            if (!$this->isFilterAppliable($queryBuilder, $field)) {
                continue;
            }

            $this->filterQueryBuilder($queryBuilder, $field, $value);
        }
    }

    /**
     * Filters queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $field
     * @param mixed        $value
     */
    protected function filterQueryBuilder(QueryBuilder $queryBuilder, string $field, $value)
    {
        switch (true) {
            // Multiple values leads to IN statement
            case $value instanceof Collection:
            case \is_array($value):
                $filterExpr = $queryBuilder->expr()->field($field)->in($value);
                break;
            // Special value for NULL evaluation
            case '_NULL' === $value:
                $filterExpr = $queryBuilder->expr()->field($field)->equals(null);
                break;
            // Special value for NOT NULL evaluation
            case '_NOT_NULL' === $value:
                $filterExpr = $queryBuilder->expr()->field($field)->notEqual(null);
                break;
            // Default is equality
            default:
                $filterExpr = $queryBuilder->expr()->field($field)->equals($value);
                break;
        }

        if (isset($filterExpr)) {
            $queryBuilder->addAnd($filterExpr);
        }
    }

    /**
     * Checks if filter is directly appliable on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $field
     *
     * @return bool
     */
    protected function isFilterAppliable(QueryBuilder $queryBuilder, string $field): bool
    {
        return true;
    }
}
