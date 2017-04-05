<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallConfig;

use Symfony\Component\DependencyInjection\Definition;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
interface FirewallConfigDefinitionAmenderInterface
{
    /**
     * @param Definition $definition
     * @return mixed
     */
    public function amendFirewallConfigDefinition($id, array &$firewall, Definition $definition);
}
