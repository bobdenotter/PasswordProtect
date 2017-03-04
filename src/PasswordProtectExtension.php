<?php
// PasswordProtect Extension for Bolt

namespace Bolt\Extension\Bolt\PasswordProtect;

use Bolt\Extension\Bolt\PasswordProtect\Controller\ProtectController;
use Bolt\Extension\Bolt\PasswordProtect\Handler\Checker;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class PasswordProtectExtension extends SimpleExtension
{
    public function registerServices(Application $app)
    {
        $app['passwordprotect.handler.checker'] = $app->share(
            function ($app) {
                $config = $app['extensions']->get('Bolt/PasswordProtect')->getConfig();

                return new Checker($app, $config);
            }
        );

        $app['passwordprotect.twig'] = $app->share(
            function ($app) {
                $config = $app['extensions']->get('Bolt/PasswordProtect')->getConfig();

                return new Twig\PasswordProtectExtension(
                    $app,
                    $config
                );
            }
        );

        $app['twig.sandbox.policy'] = $app->share(
            $app->extend('twig.sandbox.policy',
                function ($policy) {
                    $policy->addAllowedFunction('form_widget');
                    $policy->addAllowedMethod('formview', 'isrendered');
                    $policy->addAllowedMethod('session', 'get');
                    $policy->addAllowedProperty('app', 'session');
                    $policy->addAllowedProperty('formview', 'parent');
                    $policy->addAllowedProperty('formview', 'vars');

                    return $policy;
                }
            )
        );

        $config = $this->getConfig();

        if (isset($config['contenttype'])) {
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
            ]
        ];
    }

    protected function getDefaultConfig()
    {
        return [
            'encryption' => 'plaintext',
            'permission' => 'files:config'
        ];
    }

    protected function registerMenuEntries()
    {
        $config = $this->getConfig();

        $changePassword = (new MenuEntry('passwordProtect', '/bolt/protect/changePassword'))
            ->setLabel('PasswordProtect - Set Password')
            ->setIcon('fa:lock')
            ->setPermission($config['permission']);

        $findPasswordHash = (new MenuEntry('generatePasswordHash', '/bolt/protect/generatepasswords'))
            ->setLabel('Generate Password')
            ->setIcon('fa:lock')
            ->setPermission($config['permission']);

        return [
            $changePassword,
            $findPasswordHash
        ];
    }

    protected function registerBackendControllers()
    {
        return [
            '/protect' => new ProtectController($this->getContainer(), $this->getConfig()),
        ];
    }
}
