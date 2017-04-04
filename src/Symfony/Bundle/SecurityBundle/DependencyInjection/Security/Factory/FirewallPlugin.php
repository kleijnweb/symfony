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
    const POSITION_PRE_AUTHENTICATION = 'pre_auth';
    const POSITION_AUTHENTICATION_FORM = 'form';
    const POSITION_AUTHENTICATION_HTTP = 'http';
    const POSITION_AUTHENTICATION_REMEMBER_ME = 'remember_me';
    const POSITION_AUTHENTICATION_ANON = 'anon';
    const POSITION_POST_AUTHENTICATION = 'post_authentication';

    const POSITION_PRE_AUTHORIZATION = 'pre_authorization';
    const POSITION_AUTHORIZATION_DEFAULT_RBAC = 'default_rbac';
    const POSITION_POST_AUTHORIZATION = 'post_authorization';

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
        foreach($order as $position){
            /** @var FirewallPluginFactoryInterface $factory */
            foreach($factories as $factory){
                if($factory->getPosition() === $position){
                    $map[$factory->getKey()] = $factory;
                }
            }
        }

        return $map;
    }
}
