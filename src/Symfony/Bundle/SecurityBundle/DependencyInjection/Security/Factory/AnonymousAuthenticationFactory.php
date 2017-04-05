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
class AnonymousAuthenticationFactory implements FirewallPluginFactoryInterface, FirewallConfigDefinitionAmenderInterface
{
    public function getPosition()
    {
        return 'anon';
    }

    public function getKey()
    {
        return 'anonymous';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
            ->scalarNode('secret')->defaultValue(uniqid('', true))->end()
            ->end()
            ->end();
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $listenerId = 'security.authentication.listener.anonymous.' . $id;
        $container
            ->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.anonymous'))
            ->replaceArgument(1, $config['secret']);

        $providerId = 'security.authentication.provider.anonymous.' . $id;
        $container
            ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.anonymous'))
            ->replaceArgument(0, $config['secret']);

        return array($providerId, $listenerId, null);
    }

    /**
     * @param Definition $definition
     * @return mixed
     */
    public function amendFirewallConfigDefinition($id, array &$firewall, Definition $definition)
    {
        return;

        // TODO: Should be able to check if the argument was previously set to TRUE
        $definition->replaceArgument(11, isset($firewall[$this->getKey()]));
    }
}
