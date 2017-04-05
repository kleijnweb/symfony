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
interface FirewallPluginFactoryInterface
{
    /**
     * Configures the container services required to use the authentication listener.
     *
     * @param ContainerBuilder $container
     * @param string           $id           The unique id of the firewall
     * @param array            $config       The options array for the listener
     * @param string           $userProvider The service id of the user provider
     * @param string           $defaultEntryPoint
     *
     * @return array|FirewallPlugin When array, tuple containing three values:
     *               - the authentication provider id (or NULL)
     *               - the firewall listener id
     *               - the authentication entry point id (or NULL)
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint);

    /**
     * Defines the position at which the provider is called.
     *
     * @return string
     */
    public function getPosition();

    /**
     * Defines the configuration key used to reference the provider
     * in the firewall configuration.
     *
     * @return string
     */
    public function getKey();

    /**
     * @param NodeDefinition $builder
     * @return mixed
     */
    public function addConfiguration(NodeDefinition $builder);
}
