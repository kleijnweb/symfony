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
class RequestMatcherPluginFactory implements FirewallPluginFactoryInterface, FirewallConfigDefinitionAmenderInterface
{
    private $requestMatchers = array();

    /**
     * {@inheritdoc}
     */
    public function getPosition()
    {
        return FirewallPlugin::POSITION_MATCH_REQUEST;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'request_matcher';
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
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = $firewall['request_matcher'];
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
            $host = isset($firewall['host']) ? $firewall['host'] : null;
            $methods = isset($firewall['methods']) ? $firewall['methods'] : array();
            $matcher = $this->createRequestMatcher($container, $pattern, $host, $methods);
        }

        return new FirewallPlugin($listenerId);
    }

    public function addConfiguration(NodeDefinition $builder)
    {
        $builder
            ->children()
            ->scalarNode('pattern')->end()
            ->scalarNode('host')->end()
            ->arrayNode('methods')
            ->beforeNormalization()->ifString()->then(function ($v) {
                return preg_split('/\s*,\s*/', $v);
            })->end()
            ->prototype('scalar')->end()
            ->end();
    }

    /**
     * @param Definition $definition
     * @return mixed
     */
    public function amendFirewallConfigDefinition($id, array &$firewall, Definition $definition)
    {
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = $firewall['request_matcher'];
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
            $host = isset($firewall['host']) ? $firewall['host'] : null;
            $methods = isset($firewall['methods']) ? $firewall['methods'] : array();
            $matcher = $this->createRequestMatcher($container, $pattern, $host, $methods);
        }

    }

    private function createRequestMatcher($container, $path = null, $host = null, $methods = array(), $ip = null, array $attributes = array())
    {
        if ($methods) {
            $methods = array_map('strtoupper', (array)$methods);
        }

        $serialized = serialize(array($path, $host, $methods, $ip, $attributes));
        $id = 'security.request_matcher.' . md5($serialized) . sha1($serialized);

        if (isset($this->requestMatchers[$id])) {
            return $this->requestMatchers[$id];
        }

        // only add arguments that are necessary
        $arguments = array($path, $host, $methods, $ip, $attributes);
        while (count($arguments) > 0 && !end($arguments)) {
            array_pop($arguments);
        }

        $container
            ->register($id, 'Symfony\Component\HttpFoundation\RequestMatcher')
            ->setPublic(false)
            ->setArguments($arguments);

        return $this->requestMatchers[$id] = ($id);
    }
}
