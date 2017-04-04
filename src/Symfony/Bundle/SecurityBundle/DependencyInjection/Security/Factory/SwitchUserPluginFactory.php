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

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class SwitchUserPluginFactory implements FirewallPluginFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $switchUserListenerId = 'security.authentication.switchuser_listener.' . $id;
        $listener = $container->setDefinition($switchUserListenerId, new ChildDefinition('security.authentication.switchuser_listener'));
        $listener->replaceArgument(1, new Reference($userProvider));
        $listener->replaceArgument(2, new Reference('security.user_checker.' . $id));
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(6, $config['parameter']);
        $listener->replaceArgument(7, $config['role']);

        return new FirewallPlugin($switchUserListenerId);
    }

    public function getPosition()
    {
        return FirewallPlugin::POSITION_PRE_AUTHORIZATION;
    }

    public function getKey()
    {
        return 'switch_user';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
            ->scalarNode('provider')->end()
            ->scalarNode('parameter')->defaultValue('_switch_user')->end()
            ->scalarNode('role')->defaultValue('ROLE_ALLOWED_TO_SWITCH')->end()
            ->end();
    }
}
