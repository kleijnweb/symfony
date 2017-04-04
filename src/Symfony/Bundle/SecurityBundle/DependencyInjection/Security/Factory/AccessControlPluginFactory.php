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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class AccessControlPluginFactory implements FirewallPluginFactoryInterface
{
    private $requestMatchers = array();
    private $expressionLanguage;

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
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
        $builder = $node
            ->fixXmlConfig('user_provider')
            ->children()
        ;

        $builder
            ->scalarNode('secret')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('token_provider')->end()
            ->arrayNode('user_providers')
            ->beforeNormalization()
            ->ifString()->then(function ($v) { return array($v); })
            ->end()
            ->prototype('scalar')->end()
            ->end()
            ->scalarNode('catch_exceptions')->defaultTrue()->end()
        ;

        foreach ($this->options as $name => $value) {
            if (is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }

    private function createRequestMatcher($container, $path = null, $host = null, $methods = array(), $ip = null, array $attributes = array())
    {
        if ($methods) {
            $methods = array_map('strtoupper', (array) $methods);
        }

        $serialized = serialize(array($path, $host, $methods, $ip, $attributes));
        $id = 'security.request_matcher.'.md5($serialized).sha1($serialized);

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
            ->setArguments($arguments)
        ;

        return $this->requestMatchers[$id] = new Reference($id);
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }
}
