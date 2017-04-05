<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallConfig\FirewallConfigDefinitionAmenderInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallPlugin;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallPluginFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityExtension extends Extension
{
    private $requestMatchers = array();
    private $expressions = array();
    private $contextListeners = array();
    private $firewallPluginFactories = array();
    private $userProviderFactories = array();
    private $expressionLanguage;
    private $accessControlRules;

    /**
     * @deprecated
     */
    public function addSecurityListenerFactory(SecurityFactoryInterface $factory)
    {
        $this->addFirewallPluginFactory($factory);
    }

    public function addFirewallPluginFactory(FirewallPluginFactoryInterface $factory)
    {
        $this->firewallPluginFactories[] = $factory;
    }

    public function addUserProviderFactory(UserProviderFactoryInterface $factory)
    {
        $this->userProviderFactories[] = $factory;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        if (!array_filter($configs)) {
            return;
        }

        $this->firewallPluginFactories = FirewallPlugin::sortFactories($this->firewallPluginFactories);

        $mainConfig = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($mainConfig, $configs);

        if ($config['access_control']) {
            // When there are global access control rules, set them to be used by firewall plugins
            $this->accessControlRules = $config['access_control'];
        }

        // load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('security.xml');
        $loader->load('security_listeners.xml');
        $loader->load('security_rememberme.xml');
        $loader->load('templating_php.xml');
        $loader->load('templating_twig.xml');
        $loader->load('collectors.xml');
        $loader->load('guard.xml');

        if ($container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')) {
            $loader->load('security_debug.xml');
        }

        if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
            $container->removeDefinition('security.expression_language');
            $container->removeDefinition('security.access.expression_voter');
        }

        // set some global scalars
        $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        $container->setParameter('security.authentication.manager.erase_credentials', $config['erase_credentials']);
        $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_strategy']);
        $container
            ->getDefinition('security.access.decision_manager')
            ->addArgument($config['access_decision_manager']['strategy'])
            ->addArgument($config['access_decision_manager']['allow_if_all_abstain'])
            ->addArgument($config['access_decision_manager']['allow_if_equal_granted_denied']);
        $container->setParameter('security.access.always_authenticate_before_granting', $config['always_authenticate_before_granting']);
        $container->setParameter('security.authentication.hide_user_not_found', $config['hide_user_not_found']);

        $this->createFirewalls($config, $container);
        $this->createAccessMap($config, $container);
        $this->createRoleHierarchy($config, $container);

        if ($config['encoders']) {
            $this->createEncoders($config['encoders'], $container);

            if (class_exists(Application::class)) {
                $loader->load('console.xml');
                $container->getDefinition('security.console.user_password_encoder_command')->replaceArgument(1, array_keys($config['encoders']));
            }
        }

        // load ACL
        if (isset($config['acl'])) {
            $this->aclLoad($config['acl'], $container);
        }

        if (PHP_VERSION_ID < 70000) {
            // add some required classes for compilation
            $this->addClassesToCompile(array(
                'Symfony\Component\Security\Http\Firewall',
                'Symfony\Component\Security\Core\User\UserProviderInterface',
                'Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager',
                'Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage',
                'Symfony\Component\Security\Core\Authorization\AccessDecisionManager',
                'Symfony\Component\Security\Core\Authorization\AuthorizationChecker',
                'Symfony\Component\Security\Core\Authorization\Voter\VoterInterface',
                'Symfony\Bundle\SecurityBundle\Security\FirewallConfig',
                'Symfony\Bundle\SecurityBundle\Security\FirewallContext',
                'Symfony\Component\HttpFoundation\RequestMatcher',
            ));
        }
    }

    private function aclLoad($config, ContainerBuilder $container)
    {
        if (!interface_exists('Symfony\Component\Security\Acl\Model\AclInterface')) {
            throw new \LogicException('You must install symfony/security-acl in order to use the ACL functionality.');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('security_acl.xml');

        if (isset($config['cache']['id'])) {
            $container->setAlias('security.acl.cache', $config['cache']['id']);
        }
        $container->getDefinition('security.acl.voter.basic_permissions')->addArgument($config['voter']['allow_if_object_identity_unavailable']);

        // custom ACL provider
        if (isset($config['provider'])) {
            $container->setAlias('security.acl.provider', $config['provider']);

            return;
        }

        $this->configureDbalAclProvider($config, $container, $loader);
    }

    private function configureDbalAclProvider(array $config, ContainerBuilder $container, $loader)
    {
        $loader->load('security_acl_dbal.xml');

        if (null !== $config['connection']) {
            $container->setAlias('security.acl.dbal.connection', sprintf('doctrine.dbal.%s_connection', $config['connection']));
        }

        $container
            ->getDefinition('security.acl.dbal.schema_listener')
            ->addTag('doctrine.event_listener', array(
                'connection' => $config['connection'],
                'event' => 'postGenerateSchema',
                'lazy' => true,
            ));

        $container->getDefinition('security.acl.cache.doctrine')->addArgument($config['cache']['prefix']);

        $container->setParameter('security.acl.dbal.class_table_name', $config['tables']['class']);
        $container->setParameter('security.acl.dbal.entry_table_name', $config['tables']['entry']);
        $container->setParameter('security.acl.dbal.oid_table_name', $config['tables']['object_identity']);
        $container->setParameter('security.acl.dbal.oid_ancestors_table_name', $config['tables']['object_identity_ancestors']);
        $container->setParameter('security.acl.dbal.sid_table_name', $config['tables']['security_identity']);
    }

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function createRoleHierarchy($config, ContainerBuilder $container)
    {
        if (!isset($config['role_hierarchy']) || 0 === count($config['role_hierarchy'])) {
            $container->removeDefinition('security.access.role_hierarchy_voter');

            return;
        }

        $container->setParameter('security.role_hierarchy.roles', $config['role_hierarchy']);
        $container->removeDefinition('security.access.simple_role_voter');
    }

    private function createAccessMap($config, ContainerBuilder $container)
    {
        if (!$config['access_control']) {
            return;
        }

        if (PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Security\\Http\\AccessMap',
            ));
        }

        foreach ($config['access_control'] as $access) {
            $matcher = $this->createRequestMatcher(
                $container,
                $access['path'],
                $access['host'],
                $access['methods'],
                $access['ips']
            );

            $attributes = $access['roles'];
            if ($access['allow_if']) {
                $attributes[] = $this->createExpression($container, $access['allow_if']);
            }

            $container->getDefinition('security.access_map')
                ->addMethodCall('add', array($matcher, $attributes, $access['requires_channel']));
        }
    }

    private function createFirewalls($config, ContainerBuilder $container)
    {
        if (!isset($config['firewalls'])) {
            return;
        }

        $firewalls = $config['firewalls'];
        $providerIds = $this->createUserProviders($config, $container);

        // make the ContextListener aware of the configured user providers
        $definition = $container->getDefinition('security.context_listener');
        $arguments = $definition->getArguments();
        $userProviders = array();
        foreach ($providerIds as $userProviderId) {
            $userProviders[] = new Reference($userProviderId);
        }
        $arguments[1] = $userProviders;
        $definition->setArguments($arguments);

        // load firewall map
        $mapDef = $container->getDefinition('security.firewall.map');
        $map = $authenticationProviders = $contextRefs = array();
        foreach ($firewalls as $name => $firewall) {
            $configId = 'security.firewall.map.config.' . $name;

            list($matcher, $listeners, $exceptionListener) = $this->createFirewall($container, $name, $firewall, $authenticationProviders, $providerIds, $configId);

            $contextId = 'security.firewall.map.context.' . $name;
            $context = $container->setDefinition($contextId, new ChildDefinition('security.firewall.context'));
            $context
                ->replaceArgument(0, $listeners)
                ->replaceArgument(1, $exceptionListener)
                ->replaceArgument(2, new Reference($configId));

            $contextRefs[$contextId] = new Reference($contextId);
            $map[$contextId] = $matcher;
        }
        $mapDef->replaceArgument(0, ServiceLocatorTagPass::register($container, $contextRefs));
        $mapDef->replaceArgument(1, new IteratorArgument($map));

        // add authentication providers to authentication manager
        $authenticationProviders = array_map(function ($id) {
            return new Reference($id);
        }, array_values(array_unique($authenticationProviders)));
        $container
            ->getDefinition('security.authentication.manager')
            ->replaceArgument(0, new IteratorArgument($authenticationProviders));
    }

    private function createFirewall(ContainerBuilder $container, $id, $firewall, &$authenticationProviders, $providerIds, $configId)
    {
        $firewallConfigDefinition = $container->setDefinition($configId, new ChildDefinition('security.firewall.config'));
        $firewallConfigDefinition->replaceArgument(0, $id);
        $firewallConfigDefinition->replaceArgument(1, $firewall['user_checker']);

        // Matcher
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = new Reference($firewall['request_matcher']);
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
            $host = isset($firewall['host']) ? $firewall['host'] : null;
            $methods = isset($firewall['methods']) ? $firewall['methods'] : array();
            $matcher = $this->createRequestMatcher($container, $pattern, $host, $methods);
        }


        $firewallConfigDefinition->replaceArgument(2, $matcher ? (string)$matcher : null);
        $firewallConfigDefinition->replaceArgument(3, $firewall['security']);

        // Security disabled?
        if (false === $firewall['security']) {
            return array($matcher, array(), null);
        }

        $firewallConfigDefinition->replaceArgument(4, $firewall['stateless']);

        // Provider id (take the first registered provider if none defined)
        if (isset($firewall['provider'])) {
            $defaultProvider = $this->getUserProviderId($firewall['provider']);
        } else {
            $defaultProvider = reset($providerIds);
        }

        $firewallConfigDefinition->replaceArgument(5, $defaultProvider);

        // Register listeners
        $listeners = array();
        $listenerKeys = array();

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        $contextKey = null;
        // Context serializer listener
        if (false === $firewall['stateless']) {
            $contextKey = isset($firewall['context']) ? $firewall['context'] : $id;
            $listeners[] = new Reference($this->createContextListener($container, $contextKey));
        }

        $firewallConfigDefinition->replaceArgument(6, $contextKey);

        // Logout listener
        if (isset($firewall['logout'])) {
            $listenerKeys[] = 'logout';
            $listenerId = 'security.logout_listener.' . $id;
            $listener = $container->setDefinition($listenerId, new ChildDefinition('security.logout_listener'));
            $listener->replaceArgument(3, array(
                'csrf_parameter' => $firewall['logout']['csrf_parameter'],
                'csrf_token_id' => $firewall['logout']['csrf_token_id'],
                'logout_path' => $firewall['logout']['path'],
            ));
            $listeners[] = new Reference($listenerId);

            // add logout success handler
            if (isset($firewall['logout']['success_handler'])) {
                $logoutSuccessHandlerId = $firewall['logout']['success_handler'];
            } else {
                $logoutSuccessHandlerId = 'security.logout.success_handler.' . $id;
                $logoutSuccessHandler = $container->setDefinition($logoutSuccessHandlerId, new ChildDefinition('security.logout.success_handler'));
                $logoutSuccessHandler->replaceArgument(1, $firewall['logout']['target']);
            }
            $listener->replaceArgument(2, new Reference($logoutSuccessHandlerId));

            // add CSRF provider
            if (isset($firewall['logout']['csrf_token_generator'])) {
                $listener->addArgument(new Reference($firewall['logout']['csrf_token_generator']));
            }

            // add session logout handler
            if (true === $firewall['logout']['invalidate_session'] && false === $firewall['stateless']) {
                $listener->addMethodCall('addHandler', array(new Reference('security.logout.handler.session')));
            }

            // add cookie logout handler
            if (count($firewall['logout']['delete_cookies']) > 0) {
                $cookieHandlerId = 'security.logout.handler.cookie_clearing.' . $id;
                $cookieHandler = $container->setDefinition($cookieHandlerId, new ChildDefinition('security.logout.handler.cookie_clearing'));
                $cookieHandler->addArgument($firewall['logout']['delete_cookies']);

                $listener->addMethodCall('addHandler', array(new Reference($cookieHandlerId)));
            }

            // add custom handlers
            foreach ($firewall['logout']['handlers'] as $handlerId) {
                $listener->addMethodCall('addHandler', array(new Reference($handlerId)));
            }

            // register with LogoutUrlGenerator
            $container
                ->getDefinition('security.logout_url_generator')
                ->addMethodCall('registerListener', array(
                    $id,
                    $firewall['logout']['path'],
                    $firewall['logout']['csrf_token_id'],
                    $firewall['logout']['csrf_parameter'],
                    isset($firewall['logout']['csrf_token_generator']) ? new Reference($firewall['logout']['csrf_token_generator']) : null,
                    false === $firewall['stateless'] && isset($firewall['context']) ? $firewall['context'] : null,
                ));
        }

        // Determine default entry point
        $configuredEntryPoint = isset($firewall['entry_point']) ? $firewall['entry_point'] : null;

        if ($this->accessControlRules) {
            $firewall['access_control'] = $this->accessControlRules;
        }

        // Plugin listeners
        list($authListeners, $defaultEntryPoint) = $this->createListeners($container, $id, $firewall, $firewallConfigDefinition, $authenticationProviders, $defaultProvider, $configuredEntryPoint);

        $firewallConfigDefinition->replaceArgument(7, $configuredEntryPoint ?: $defaultEntryPoint);

        $listeners = array_merge($listeners, $authListeners);

        // Exception listener
        $exceptionListener = new Reference($this->createExceptionListener($container, $firewall, $id, $configuredEntryPoint ?: $defaultEntryPoint, $firewall['stateless']));

        $firewallConfigDefinition->replaceArgument(8, isset($firewall['access_denied_handler']) ? $firewall['access_denied_handler'] : null);
        $firewallConfigDefinition->replaceArgument(9, isset($firewall['access_denied_url']) ? $firewall['access_denied_url'] : null);

        $container->setAlias('security.user_checker.' . $id, new Alias($firewall['user_checker'], false));

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

        $firewallConfigDefinition->replaceArgument(10, $listenerKeys);
        $firewallConfigDefinition->replaceArgument(11, $allowsAnonymous);

        return array($matcher, $listeners, $exceptionListener);
    }

    private function createContextListener($container, $contextKey)
    {
        if (isset($this->contextListeners[$contextKey])) {
            return $this->contextListeners[$contextKey];
        }

        $listenerId = 'security.context_listener.' . count($this->contextListeners);
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.context_listener'));
        $listener->replaceArgument(2, $contextKey);

        return $this->contextListeners[$contextKey] = $listenerId;
    }

    private function createListeners($container, $id, $firewall, $config, &$authenticationProviders, $defaultProvider, $defaultEntryPoint)
    {
        $listeners = array();
        $hasAuthenticationProvider = false;

        /** @var FirewallPluginFactoryInterface $factory */
        foreach ($this->firewallPluginFactories as $factory) {
            $key = str_replace('-', '_', $factory->getKey());

            if($factory instanceof FirewallConfigDefinitionAmenderInterface){
                $factory->amendFirewallConfigDefinition($id, $firewall, $config);
            }

            if (isset($firewall[$key])) {
                $userProvider = isset($firewall[$key]['provider']) ? $this->getUserProviderId($firewall[$key]['provider']) : $defaultProvider;

                $plugin = FirewallPlugin::normalize($factory->create($container, $id, $firewall[$key], $userProvider, $defaultEntryPoint));

                if ($plugin->getAuthenticationEntryPoint() !== null) {
                    $defaultEntryPoint = $plugin->getAuthenticationEntryPoint();
                }

                $listeners[] = new Reference($plugin->getListenerId());

                if ($plugin->getAuthenticationProviderId() !== null) {
                    $hasAuthenticationProvider = true;
                    $authenticationProviders[] = $plugin->getAuthenticationProviderId();
                }
            }
        }

        if (false === $hasAuthenticationProvider) {
            throw new InvalidConfigurationException(sprintf('No authentication listener registered for firewall "%s".', $id));
        }

        return array($listeners, $defaultEntryPoint);
    }

    private function createEncoders($encoders, ContainerBuilder $container)
    {
        $encoderMap = array();
        foreach ($encoders as $class => $encoder) {
            $encoderMap[$class] = $this->createEncoder($encoder, $container);
        }

        $container
            ->getDefinition('security.encoder_factory.generic')
            ->setArguments(array($encoderMap));
    }

    private function createEncoder($config, ContainerBuilder $container)
    {
        // a custom encoder service
        if (isset($config['id'])) {
            return new Reference($config['id']);
        }

        // plaintext encoder
        if ('plaintext' === $config['algorithm']) {
            $arguments = array($config['ignore_case']);

            return array(
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => $arguments,
            );
        }

        // pbkdf2 encoder
        if ('pbkdf2' === $config['algorithm']) {
            return array(
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => array(
                    $config['hash_algorithm'],
                    $config['encode_as_base64'],
                    $config['iterations'],
                    $config['key_length'],
                ),
            );
        }

        // bcrypt encoder
        if ('bcrypt' === $config['algorithm']) {
            return array(
                'class' => 'Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder',
                'arguments' => array($config['cost']),
            );
        }

        // run-time configured encoder
        return $config;
    }

    // Parses user providers and returns an array of their ids
    private function createUserProviders($config, ContainerBuilder $container)
    {
        $providerIds = array();
        foreach ($config['providers'] as $name => $provider) {
            $id = $this->createUserDaoProvider($name, $provider, $container);
            $providerIds[] = $id;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    private function createUserDaoProvider($name, $provider, ContainerBuilder $container)
    {
        $name = $this->getUserProviderId($name);

        // Doctrine Entity and In-memory DAO provider are managed by factories
        foreach ($this->userProviderFactories as $factory) {
            $key = str_replace('-', '_', $factory->getKey());

            if (!empty($provider[$key])) {
                $factory->create($container, $name, $provider[$key]);

                return $name;
            }
        }

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, new Alias($provider['id'], false));

            return $provider['id'];
        }

        // Chain provider
        if (isset($provider['chain'])) {
            $providers = array();
            foreach ($provider['chain']['providers'] as $providerName) {
                $providers[] = new Reference($this->getUserProviderId($providerName));
            }

            $container
                ->setDefinition($name, new ChildDefinition('security.user.provider.chain'))
                ->addArgument($providers);

            return $name;
        }

        throw new InvalidConfigurationException(sprintf('Unable to create definition for "%s" user provider', $name));
    }

    private function getUserProviderId($name)
    {
        return 'security.user.provider.concrete.' . strtolower($name);
    }

    private function createExceptionListener($container, $config, $id, $defaultEntryPoint, $stateless)
    {
        $exceptionListenerId = 'security.exception_listener.' . $id;
        $listener = $container->setDefinition($exceptionListenerId, new ChildDefinition('security.exception_listener'));
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(4, null === $defaultEntryPoint ? null : new Reference($defaultEntryPoint));
        $listener->replaceArgument(8, $stateless);

        // access denied handler setup
        if (isset($config['access_denied_handler'])) {
            $listener->replaceArgument(6, new Reference($config['access_denied_handler']));
        } elseif (isset($config['access_denied_url'])) {
            $listener->replaceArgument(5, $config['access_denied_url']);
        }

        return $exceptionListenerId;
    }

    private function createExpression($container, $expression)
    {
        if (isset($this->expressions[$id = 'security.expression.' . sha1($expression)])) {
            return $this->expressions[$id];
        }

        $container
            ->register($id, 'Symfony\Component\ExpressionLanguage\SerializedParsedExpression')
            ->setPublic(false)
            ->addArgument($expression)
            ->addArgument(serialize($this->getExpressionLanguage()->parse($expression, array('token', 'user', 'object', 'roles', 'request', 'trust_resolver'))->getNodes()));

        return $this->expressions[$id] = new Reference($id);
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

        return $this->requestMatchers[$id] = new Reference($id);
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__ . '/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/security';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        // first assemble the factories
        return new MainConfiguration($this->firewallPluginFactories, $this->userProviderFactories);
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
