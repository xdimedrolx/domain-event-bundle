<?php

/**
 * GpsLab component.
 *
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 * @license http://opensource.org/licenses/MIT
 */

namespace GpsLab\Bundle\DomainEvent\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class EventListenerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('domain_event.locator')) {
            return;
        }

        $current_locator = $container->findDefinition('domain_event.locator');
        $symfony_locator = $container->findDefinition('domain_event.locator.symfony');
        $container_locator = $container->findDefinition('domain_event.locator.container');

        if ($current_locator === $symfony_locator || $current_locator === $container_locator) {
            $this->registerListeners($container, $current_locator);
            $this->registerSubscribers($container, $current_locator);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition       $current_locator
     */
    private function registerListeners(ContainerBuilder $container, Definition $current_locator): void
    {
        foreach ($container->findTaggedServiceIds('domain_event.listener') as $id => $attributes) {
            foreach ($attributes as $attribute) {
                $method = !empty($attribute['method']) ? $attribute['method'] : '__invoke';
                $current_locator->addMethodCall('registerService', [$attribute['event'], $id, $method]);
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition       $current_locator
     */
    private function registerSubscribers(ContainerBuilder $container, Definition $current_locator): void
    {
        foreach ($container->findTaggedServiceIds('domain_event.subscriber') as $id => $attributes) {
            $subscriber = $container->findDefinition($id);
            $current_locator->addMethodCall('registerSubscriberService', [$id, $subscriber->getClass()]);
        }
    }
}
