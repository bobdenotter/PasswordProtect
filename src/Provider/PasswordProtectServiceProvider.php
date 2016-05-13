<?php


namespace Bolt\Extension\Bolt\PasswordProtect\Provider;

use Bolt\Extension\Bolt\PasswordProtect\Handler\Checker;
use Bolt\Extension\Bolt\PasswordProtect\Twig\PasswordProtectExtension;
use Silex\Application;
use Silex\ServiceProviderInterface;

class PasswordProtectServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['passwordprotect.handler.checker'] = $app->share(
            function ($app) {
                $request = $this->app['request_stack']->getCurrentRequest();
                $config = $app['extensions']->get('Bolt/PasswordProtect')->getConfig();
                $session = $this->app['session'];
                $storage = $this->app['storage'];

                return new Checker($request, $config, $session, $storage);
            }
        );

        $app['passwordprotect.twig'] = $app->share(
            function ($app) {
                $form = $app['form.factory'];
                $config = $app['extensions']->get('Bolt/PasswordProtect')->getConfig();
                $checker = $app['passwordprotect.handler.checker'];
                $request = $this->app['request_stack']->getCurrentRequest();
                $session = $this->app['session'];
                $twigFileSystem = $this->app['twig.loader.filesystem'];
                $view = $this->app['twig'];

                return new PasswordProtectExtension(
                    $form,
                    $config,
                    $checker,
                    $request,
                    $session,
                    $twigFileSystem,
                    $view
                );
            }
        );
    }

    public function boot(Application $app)
    {

    }

}
