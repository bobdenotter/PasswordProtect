<?php
// PasswordProtect Extension for Bolt

namespace Bolt\Extension\Bobdenotter\PasswordProtect;

use Bolt\Collection\Bag;
use Bolt\Extension\Bobdenotter\PasswordProtect\Controller\ProtectController;
use Bolt\Extension\Bobdenotter\PasswordProtect\Handler\Checker;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class PasswordProtectExtension extends SimpleExtension
{
    public function registerServices(Application $app)
    {
        $config = $this->getConfig();

        $app['passwordprotect.handler.checker'] = $app->share(
            function ($app) use ($config) {
                return new Checker($app, $config);
            }
        );

        $app['passwordprotect.twig'] = $app->share(
            function ($app) use ($config) {
                return new Twig\PasswordProtect($app, $config);
            }
        );

        // Set the sandbox policy, but only for Bolt 3.3 and up.
        if (isset($app['twig.sandbox.policy'])) {
            $app['twig.sandbox.policy'] = $app->share(
                $app->extend('twig.sandbox.policy', function ($policy) {
                    $policy->addAllowedFunction('form_widget');
                    $policy->addAllowedMethod('formview', 'isrendered');
                    $policy->addAllowedMethod('formview', 'ismethodrendered');
                    $policy->addAllowedMethod('formview', 'setmethodrendered');
                    $policy->addAllowedProperty('formview', 'parent');
                    $policy->addAllowedProperty('formview', 'vars');

                    return $policy;
                })
            );
        }

        if ($config->get('contenttype')) {
            $app->before(function (Request $request) use ($app) {
                return $app['passwordprotect.handler.checker']->checkContentTypeOnRequest($request);
            }, Application::LATE_EVENT);
        }
    }

    protected function registerTwigPaths()
    {
        return ['templates'];
    }

    protected function registerTwigFunctions()
    {
        $app = $this->getContainer();

        return [
            'passwordprotect' => [
                [$app['passwordprotect.twig'],'passwordProtect'],
                ['is_safe' => ['html'], 'safe' => true]
            ],
            'passwordform' => [
                [$app['passwordprotect.twig'], 'passwordForm'],
                ['is_safe' => ['html'], 'safe' => true]
            ],
            'passwordprotect_username' => [
                [$app['passwordprotect.twig'],'passwordProtectUsername'],
                ['is_safe' => ['html'], 'safe' => true]
            ]
        ];
    }

    protected function getDefaultConfig()
    {
        return [
            'encryption'                        => 'password_hash',
            'permission'                        => 'files:config',
            'allow_setting_password_in_backend' => false,
            'labels' => [
                'username' => "Username",
                'password' => "Password",
                'login' => "Log on!",
                'logout' => "Log off!",
                'alreadyloggedin' => "You are already logged on.",
                'message_correct' => "Thank you for providing the correct password. You now have access to the protected pages.",
                'message_wrong' => "The password was not correct. Try again."
            ]
        ];
    }

    protected function getConfig()
    {
        return $this->config = new Bag(parent::getConfig());
    }

    protected function registerMenuEntries()
    {
        $config = $this->getConfig();
        $app = $this->getContainer();

        $prefix = $app['url_generator']->generate('dashboard');

        $menuEntries = [];

        if ($config->get('allow_setting_password_in_backend')) {
            $menuEntries[] = (new MenuEntry('passwordProtect', $prefix . '/protect/changePassword'))
                ->setLabel('PasswordProtect - Set Password')
                ->setIcon('fa:lock')
                ->setPermission($config->get('permission'));
        }

        $menuEntries[] = (new MenuEntry('generatePasswordHash', $prefix . '/protect/generatepasswords'))
            ->setLabel('Generate Password')
            ->setIcon('fa:lock')
            ->setPermission($config->get('permission'));

        return $menuEntries;
    }

    protected function registerBackendControllers()
    {
        return [
            '/protect' => new ProtectController($this->getContainer(), $this->getConfig()),
        ];
    }
}
