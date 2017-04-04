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
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class AccessControlPluginFactory implements FirewallPluginFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        return new FirewallPlugin('security.access_listener');
    }

    public function getPosition()
    {
        return FirewallPlugin::POSITION_AUTHORIZATION_DEFAULT_RBAC;
    }

    public function getKey()
    {
        return 'access_control';
    }

    public function addConfiguration(NodeDefinition $node)
    {

    }
}
