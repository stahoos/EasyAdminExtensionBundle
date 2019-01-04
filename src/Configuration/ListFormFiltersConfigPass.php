<?php

namespace AlterPHP\EasyAdminExtensionBundle\Configuration;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminAutocompleteType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

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
        if (isset($backendConfig['entities']) && \is_array($backendConfig['entities'])) {
            $this->processObjectListFormFilters('entity', $backendConfig['entities']);
        }

        if (isset($backendConfig['documents']) && \is_array($backendConfig['documents'])) {
            $this->processObjectListFormFilters('document', $backendConfig['documents']);
        }

        return $backendConfig;
    }

    private function processObjectListFormFilters(string $objectType, array &$objectConfigs)
    {
        foreach ($objectConfigs as $objectName => $objectConfig) {
            if (!isset($objectConfig['list']['form_filters'])) {
                continue;
            }

            $formFilters = [];

            foreach ($objectConfig['list']['form_filters'] as $key => $formFilter) {
                // Detects invalid config node
                if (!\is_string($formFilter) && !\is_array($formFilter)) {
                    throw new \RuntimeException(
                        \sprintf(
                            'The values of the "form_filters" option for the list view of the "%s" object of type "%s" can only be strings or arrays.',
                            $objectConfig['class'],
                            $objectType
                        )
                    );
                }

                // Key mapping
                if (\is_string($formFilter)) {
                    $filterConfig = ['property' => $formFilter];
                } else {
                    if (!\array_key_exists('property', $formFilter)) {
                        if (\is_string($key)) {
                            $formFilter['property'] = $key;
                        } else {
                            throw new \RuntimeException(
                                \sprintf(
                                    'One of the values of the "form_filters" option for the "list" view of the "%s" object of type "%s" does not define the mandatory option "property".',
                                    $objectConfig['class'],
                                    $objectType
                                )
                            );
                        }
                    }

                    $filterConfig = $formFilter;
                }

                if ('entity' === $objectType) {
                    $this->configureEntityFormFilter(
                        $objectConfig['class'],
                        $filterConfig,
                        $backendConfig['translation_domain'] ?? 'EasyAdminBundle'
                    );
                }

                // If type is not configured at this steps => not guessable
                if (!isset($filterConfig['type'])) {
                    continue;
                }

                $formFilters[$filterConfig['property']] = $filterConfig;
            }

            // set form filters config and form !
            $objectConfigs[$objectName]['list']['form_filters'] = $formFilters;
        }
    }

    private function configureEntityFormFilter(string $entityClass, array &$filterConfig, string $translationDomain)
    {
        // No need to guess type
        if (isset($filterConfig['type'])) {
            return;
        }

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
            $this->configureEntityPropertyFilter(
                $entityClass, $entityMetadata->getFieldMapping($filterConfig['property']), $filterConfig, $translationDomain
            );
        } elseif ($entityMetadata->hasAssociation($filterConfig['property'])) {
            $this->configureEntityAssociationFilter(
                $entityClass, $entityMetadata->getAssociationMapping($filterConfig['property']), $filterConfig
            );
        }
    }

    private function configureEntityPropertyFilter(string $entityClass, array $fieldMapping, array &$filterConfig, string $translationDomain)
    {
        switch ($fieldMapping['type']) {
            case 'boolean':
                $filterConfig['type'] = ChoiceType::class;
                $defaultFilterConfigTypeOptions = [
                    'choices' => [
                        'list_form_filters.default.boolean.true' => true,
                        'list_form_filters.default.boolean.false' => false,
                    ],
                    'choice_translation_domain' => $translationDomain,
                ];
                break;
            case 'string':
                $filterConfig['type'] = ChoiceType::class;
                $defaultFilterConfigTypeOptions = [
                    'multiple' => true,
                    'choices' => $this->getChoiceList($entityClass, $filterConfig['property'], $filterConfig),
                    'attr' => ['data-widget' => 'select2'],
                    'choice_translation_domain' => $translationDomain,
                ];
                break;
            default:
                return;
        }

        // Merge default type options when defined
        if (null !== $defaultFilterConfigTypeOptions) {
            $filterConfig['type_options'] = \array_merge(
                $defaultFilterConfigTypeOptions,
                $filterConfig['type_options'] ?? []
            );
        }
    }

    private function configureEntityAssociationFilter(string $entityClass, array $associationMapping, array &$filterConfig)
    {
        // To-One (EasyAdminAutocompleteType)
        if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
            $filterConfig['type'] = EasyAdminAutocompleteType::class;
            $filterConfig['type_options'] = \array_merge(
                [
                    'class' => $associationMapping['targetEntity'],
                    'multiple' => true,
                    'attr' => ['data-widget' => 'select2'],
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
