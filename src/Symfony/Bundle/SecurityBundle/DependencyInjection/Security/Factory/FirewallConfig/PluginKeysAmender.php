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
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class PluginKeysAmender implements FirewallConfigDefinitionAmenderInterface
{
    private $firewallPluginFactories;

    /**
     * PluginKeysAmender constructor.
     * @param $firewallPluginFactories
     */
    public function __construct($firewallPluginFactories)
    {
        $this->firewallPluginFactories = $firewallPluginFactories;
    }

    /**
     * @param Definition $definition
     * @return mixed
     */
    public function amendFirewallConfigDefinition($id, array &$firewall, Definition $definition)
    {
        $allowsAnonymous = false;
        foreach ($this->firewallPluginFactories as $factory) {
            /** @var FirewallPluginFactoryInterface $factory */
            if($factory->getPosition() === FirewallPlugin::POSITION_AUTHENTICATION_ANON){
                $allowsAnonymous = true;
            }
            $key = str_replace('-', '_', $factory->getKey());
            if (array_key_exists($key, $firewall)) {
                $listenerKeys[] = $key;
            }
        }

        $config->replaceArgument(10, $listenerKeys);
        $config->replaceArgument(11, $allowsAnonymous);
    }
}
