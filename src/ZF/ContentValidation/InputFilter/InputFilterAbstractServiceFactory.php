<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ContentValidation\InputFilter;

use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Factory;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Validator\ValidatorPluginManager;

class InputFilterAbstractServiceFactory implements AbstractFactoryInterface
{
    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @param  ServiceLocatorInterface $services
     * @param  string                  $cName
     * @param  string                  $rName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $services, $cName, $rName)
    {
        if (!$services->has('Config')) {
            return false;
        }

        $config = $services->get('Config');
        if (!isset($config['input_filters'][$rName])
            || !is_array($config['input_filters'][$rName])
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param  ServiceLocatorInterface                $services
     * @param  string                                 $cName
     * @param  string                                 $rName
     * @return \Laminas\InputFilter\InputFilterInterface
     */
    public function createServiceWithName(ServiceLocatorInterface $services, $cName, $rName)
    {
        $allConfig = $services->get('Config');
        $config    = $allConfig['input_filters'][$rName];

        $factory   = $this->getInputFilterFactory($services);

        return $factory->createInputFilter($config);
    }

    protected function getInputFilterFactory(ServiceLocatorInterface $services)
    {
        if ($this->factory instanceof Factory) {
            return $this->factory;
        }

        $this->factory = new Factory();
        $this->factory
            ->getDefaultFilterChain()
            ->setPluginManager($this->getFilterPluginManager($services));
        $this->factory
            ->getDefaultValidatorChain()
            ->setPluginManager($this->getValidatorPluginManager($services));

        return $this->factory;
    }

    protected function getFilterPluginManager(ServiceLocatorInterface $services)
    {
        if ($services->has('FilterManager')) {
            return $services->get('FilterManager');
        }

        return new FilterPluginManager();
    }

    protected function getValidatorPluginManager(ServiceLocatorInterface $services)
    {
        if ($services->has('ValidatorManager')) {
            return $services->get('ValidatorManager');
        }

        return new ValidatorPluginManager();
    }
}
