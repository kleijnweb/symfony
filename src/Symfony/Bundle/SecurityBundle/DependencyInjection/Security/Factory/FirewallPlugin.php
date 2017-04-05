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

/**
 * Contains Dependency Injection and execution order information for firewall plugins
 *
 * @author John Kleijn <john@kleijnweb.nl>
 */
class FirewallPlugin
{
    const POSITION_MATCH_REQUEST = 'match_reuqest';

    const POSITION_PRE_AUTHENTICATION = 'pre_auth';
    const POSITION_AUTHENTICATION_FORM = 'form';
    const POSITION_AUTHENTICATION_HTTP = 'http';
    const POSITION_AUTHENTICATION_REMEMBER_ME = 'remember_me';
    const POSITION_AUTHENTICATION_ANON = 'anon';
    const POSITION_POST_AUTHENTICATION = 'post_authentication';

    const POSITION_PRE_AUTHORIZATION = 'pre_authorization';
    const POSITION_AUTHORIZATION_DEFAULT_RBAC = 'default_rbac';
    const POSITION_POST_AUTHORIZATION = 'post_authorization';

    private $listenerId;
    private $authenticationProviderId;
    private $authenticationEntryPoint;

    /**
     * @param string $listenerId
     * @param string $authenticationProviderId
     * @param string $authenticationEntryPoint
     */
    public function __construct($listenerId, $authenticationProviderId = null, $authenticationEntryPoint = null)
    {
        $this->listenerId = $listenerId;
        $this->authenticationProviderId = $authenticationProviderId;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
    }

    /**
     * @param FirewallPlugin|array $returnValue
     * @return FirewallPlugin
     */
    public static function normalize($returnValue)
    {
        if ($returnValue instanceof FirewallPlugin) {
            return $returnValue;
        }

        return self::fromTuple($returnValue);
    }

    /**
     * @param array $tuple Tuple containing three values:
     *                     1. the authentication provider id (or NULL)
     *                     2. the firewall listener id
     *                     3. the authentication entry point id (or NULL)
     *
     * @return FirewallPlugin
     */
    public static function fromTuple(array $tuple)
    {
        return new self($tuple[1], $tuple[0], $tuple[2]);
    }

    /**
     * Sort the factories by position
     *
     * @param array $factories
     * @return array
     */
    public static function sortFactories(array $factories)
    {
        $order = [
            self:: POSITION_PRE_AUTHENTICATION,
            self:: POSITION_AUTHENTICATION_FORM,
            self:: POSITION_AUTHENTICATION_HTTP,
            self:: POSITION_AUTHENTICATION_REMEMBER_ME,
            self:: POSITION_AUTHENTICATION_ANON,
            self:: POSITION_POST_AUTHENTICATION,
            self:: POSITION_PRE_AUTHORIZATION,
            self:: POSITION_AUTHORIZATION_DEFAULT_RBAC,
            self:: POSITION_POST_AUTHORIZATION
        ];

        $map = [];
        foreach ($order as $position) {
            /** @var FirewallPluginFactoryInterface $factory */
            foreach ($factories as $factory) {
                if ($factory->getPosition() === $position) {
                    $map[$factory->getKey()] = $factory;
                }
            }
        }

        return $map;
    }

    /**
     * @return string
     */
    public function getListenerId()
    {
        return $this->listenerId;
    }

    /**
     * @return string
     */
    public function getAuthenticationProviderId()
    {
        return $this->authenticationProviderId;
    }

    /**
     * @return string
     */
    public function getAuthenticationEntryPoint()
    {
        return $this->authenticationEntryPoint;
    }
}
