<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallConfig\FirewallConfigDefinitionAmenderInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class StatefulAuthorizationPluginFactory implements FirewallPluginFactoryInterface, FirewallConfigDefinitionAmenderInterface
{
    private static $count = 0;

    /**
     * {@inheritdoc}
     */
    public function getPosition()
    {
        return FirewallPlugin::POSITION_POST_AUTHENTICATION;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'stateful';
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $id
     * @param array            $config
     * @param string           $userProvider
     * @param string           $defaultEntryPoint
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $contextKey = $config['context'] ?: $id;
        $listenerId = 'security.context_listener.' . (++self::$count);
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.context_listener'));
        $listener->replaceArgument(2, $contextKey);

        return new FirewallPlugin($listenerId);
    }

    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
            ->scalarNode('context')->end()
            ->end();
    }

    /**
     * @param Definition $definition
     * @return mixed
     */
    public function amendFirewallConfigDefinition($id, array &$firewall, Definition $definition)
    {
        return;
        $contextKey = null;
        if (false === $firewall['stateless']) {
            $contextKey = isset($firewall['context']) ? $firewall['context'] : $id;
            $firewall['stateful'] = ['context' => $contextKey];
        }

        $definition->replaceArgument(6, $contextKey);
    }
}
