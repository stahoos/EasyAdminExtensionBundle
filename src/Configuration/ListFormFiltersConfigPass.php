<?php

namespace AlterPHP\EasyAdminExtensionBundle\Configuration;

use AlterPHP\EasyAdminExtensionBundle\Model\ListFilter;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\type as DBALType;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminAutocompleteType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * Guess form types for list form filters.
 */
class ListFormFiltersConfigPass implements ConfigPassInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig): array
    {
        if (!isset($backendConfig['entities'])) {
            return $backendConfig;
        }

        foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
            if (!isset($entityConfig['list']['form_filters'])) {
                continue;
            }

            $formFilters = [];

            foreach ($entityConfig['list']['form_filters'] as $i => $formFilter) {
                // Detects invalid config node
                if (!\is_string($formFilter) && !\is_array($formFilter)) {
                    throw new \RuntimeException(
                        \sprintf(
                            'The values of the "form_filters" option for the list view of the "%s" entity can only be strings or arrays.',
                            $entityConfig['class']
                        )
                    );
                }

                // Key mapping
                if (\is_string($formFilter)) {
                    $filterConfig = ['name' => $formFilter, 'property' => $formFilter];
                } else {
                    if (!\array_key_exists('property', $formFilter)) {
                        throw new \RuntimeException(
                            \sprintf(
                                'One of the values of the "form_filters" option for the "list" view of the "%s" entity does not define the mandatory option "property".',
                                $entityConfig['class']
                            )
                        );
                    }

                    $filterConfig = $formFilter;
                    // Auto set name with property value
                    $filterConfig['name'] = $filterConfig['name'] ?? $filterConfig['property'];
                }

                $this->configureFilter(
                    $entityConfig['class'],
                    $filterConfig,
                    $backendConfig['translation_domain'] ?? 'EasyAdminBundle'
                );

                // If type is not configured at this steps => not guessable
                if (!isset($filterConfig['type'])) {
                    continue;
                }

                $formFilters[$filterConfig['name']] = $filterConfig;
            }

            // set form filters config and form !
            $backendConfig['entities'][$entityName]['list']['form_filters'] = $formFilters;
        }

        return $backendConfig;
    }

    private function configureFilter(string $entityClass, array &$filterConfig, string $translationDomain)
    {
        $em = $this->doctrine->getManagerForClass($entityClass);
        $entityMetadata = $em->getMetadataFactory()->getMetadataFor($entityClass);

        // Not able to guess type
        if (
            !$entityMetadata->hasField($filterConfig['property'])
            && !$entityMetadata->hasAssociation($filterConfig['property'])
        ) {
            return;
        }

        if ($entityMetadata->hasField($filterConfig['property'])) {
            $this->configureFieldFilter(
                $entityClass, $entityMetadata->getFieldMapping($filterConfig['property']), $filterConfig, $translationDomain
            );
        } elseif ($entityMetadata->hasAssociation($filterConfig['property'])) {
            $this->configureAssociationFilter(
                $entityClass, $entityMetadata->getAssociationMapping($filterConfig['property']), $filterConfig
            );
        }
    }

    private function configureFieldFilter(
        string $entityClass, array $fieldMapping, array &$filterConfig, string $translationDomain
    ) {
        switch ($fieldMapping['type']) {
            case DBALType::BOOLEAN:
                $filterConfig['operator'] = $filterConfig['operator'] ?? ListFilter::OPERATOR_EQUALS;
                $filterConfig['type'] = $filterConfig['type'] ?? ChoiceType::class;
                $defaultFilterConfigTypeOptions = [
                    'choices' => [
                        'list_form_filters.default.boolean.true' => true,
                        'list_form_filters.default.boolean.false' => false,
                    ],
                    'choice_translation_domain' => 'EasyAdminBundle',
                ];
                break;
            case DBALType::STRING:
                $filterConfig['operator'] = $filterConfig['operator'] ?? ListFilter::OPERATOR_IN;
                $filterConfig['type'] = $filterConfig['type'] ?? ChoiceType::class;
                $defaultFilterConfigTypeOptions = [
                    'multiple' => in_array($filterConfig['operator'], [ListFilter::OPERATOR_IN, ListFilter::OPERATOR_NOTIN]),
                    'placeholder' => '-',
                    'choices' => $this->getChoiceList($entityClass, $filterConfig['property'], $filterConfig),
                    'attr' => ['data-widget' => 'select2'],
                    'choice_translation_domain' => $translationDomain,
                ];
                break;
            case DBALType::SMALLINT:
            case DBALType::INTEGER:
            case DBALType::BIGINT:
                $filterConfig['operator'] = $filterConfig['operator'] ?? ListFilter::OPERATOR_EQUALS;
                $filterConfig['type'] = $filterConfig['type'] ?? IntegerType::class;
                $defaultFilterConfigTypeOptions = [];
                break;
            case DBALType::DECIMAL:
            case DBALType::FLOAT:
                $filterConfig['operator'] = $filterConfig['operator'] ?? ListFilter::OPERATOR_EQUALS;
                $filterConfig['type'] = $filterConfig['type'] ?? NumberType::class;
                $defaultFilterConfigTypeOptions = [];
                break;
            default:
                return;
        }

        // Auto set multiple on ChoiceType when operator requires array
        if (ChoiceType::class === $filterConfig['type']) {
            $defaultFilterConfigTypeOptions['choices'] = $defaultFilterConfigTypeOptions['choices'] ?? $this->getChoiceList($entityClass, $filterConfig['property'], $filterConfig);

            if (in_array($filterConfig['operator'], [ListFilter::OPERATOR_IN, ListFilter::OPERATOR_NOTIN])) {
                $defaultFilterConfigTypeOptions['multiple'] = $defaultFilterConfigTypeOptions['multiple'] ?? true;
            }
        }

        // Merge default type options when defined
        if (null !== $defaultFilterConfigTypeOptions) {
            $filterConfig['type_options'] = \array_merge(
                $defaultFilterConfigTypeOptions,
                $filterConfig['type_options'] ?? []
            );
        }
    }

    private function configureAssociationFilter(string $entityClass, array $associationMapping, array &$filterConfig)
    {
        // To-One (EasyAdminAutocompleteType)
        if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
            $filterConfig['operator'] = $filterConfig['operator'] ?? ListFilter::OPERATOR_IN;
            $filterConfig['type'] = $filterConfig['type'] ?? EasyAdminAutocompleteType::class;
            $filterConfig['type_options'] = \array_merge(
                [
                    'class' => $associationMapping['targetEntity'],
                    'multiple' => true,
                ],
                $filterConfig['type_options'] ?? []
            );
        }
    }

    private function getChoiceList(string $entityClass, string $property, array &$filterConfig)
    {
        if (isset($filterConfig['type_options']['choices'])) {
            $choices = $filterConfig['type_options']['choices'];
            unset($filterConfig['type_options']['choices']);

            return $choices;
        }

        if (!isset($filterConfig['type_options']['choices_static_callback'])) {
            throw new \RuntimeException(
                \sprintf(
                    'Choice filter field "%s" for entity "%s" must provide either a static callback method returning choice list or choices option.',
                    $property,
                    $entityClass
                )
            );
        }

        $callableParams = [];
        if (\is_string($filterConfig['type_options']['choices_static_callback'])) {
            $callable = [$entityClass, $filterConfig['type_options']['choices_static_callback']];
        } else {
            $callable = [$entityClass, $filterConfig['type_options']['choices_static_callback'][0]];
            $callableParams = $filterConfig['type_options']['choices_static_callback'][1];
        }
        unset($filterConfig['type_options']['choices_static_callback']);

        return \forward_static_call_array($callable, $callableParams);
    }
}
