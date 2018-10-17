<?php

namespace AlterPHP\EasyAdminExtensionBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;

/**
 * Apply filters on list/search queryBuilder.
 */
class PostQueryBuilderSubscriber extends AbstractPostQueryBuilderSubscriber
{
    protected const APPLIABLE_OBJECT_TYPE = 'entity';

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            EasyAdminEvents::POST_LIST_QUERY_BUILDER => ['onPostListQueryBuilder'],
            EasyAdminEvents::POST_SEARCH_QUERY_BUILDER => ['onPostSearchQueryBuilder'],
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
            if ('' === $value || \is_int($field)) {
                continue;
            }
            // Add root entity alias if none provided
            $field = false === \strpos($field, '.') ? $queryBuilder->getRootAlias().'.'.$field : $field;
            // Checks if filter is directly appliable on queryBuilder
            if (!$this->isFilterAppliable($queryBuilder, $field)) {
                continue;
            }
            // Sanitize parameter name
            $parameter = 'request_filter_'.\str_replace('.', '_', $field);

            $this->filterQueryBuilder($queryBuilder, $field, $parameter, $value);
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
            // Add root entity alias if none provided
            $field = false === \strpos($field, '.') ? $queryBuilder->getRootAlias().'.'.$field : $field;
            // Checks if filter is directly appliable on queryBuilder
            if (!$this->isFilterAppliable($queryBuilder, $field)) {
                continue;
            }
            // Sanitize parameter name
            $parameter = 'form_filter_'.\str_replace('.', '_', $field);

            $this->filterQueryBuilder($queryBuilder, $field, $parameter, $value);
        }
    }

    /**
     * Filters queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $field
     * @param string       $parameter
     * @param mixed        $value
     */
    protected function filterQueryBuilder(QueryBuilder $queryBuilder, string $field, string $parameter, $value)
    {
        switch (true) {
            // Multiple values leads to IN statement
            case $value instanceof Collection:
            case \is_array($value):
                if (0 < \count($value)) {
                    $filterDqlPart = $field.' IN (:'.$parameter.')';
                }
                break;
            // Special value for NULL evaluation
            case '_NULL' === $value:
                $parameter = null;
                $filterDqlPart = $field.' IS NULL';
                break;
            // Special value for NOT NULL evaluation
            case '_NOT_NULL' === $value:
                $parameter = null;
                $filterDqlPart = $field.' IS NOT NULL';
                break;
            // Default is equality
            default:
                $filterDqlPart = $field.' = :'.$parameter;
                break;
        }

        if (isset($filterDqlPart)) {
            $queryBuilder->andWhere($filterDqlPart);
            if (null !== $parameter) {
                $queryBuilder->setParameter($parameter, $value);
            }
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
        $qbClone = clone $queryBuilder;

        try {
            $qbClone->andWhere($field.' IS NULL');

            // Generating SQL throws a QueryException if using wrong field/association
            $qbClone->getQuery()->getSQL();
        } catch (QueryException $e) {
            return false;
        }

        return true;
    }
}
