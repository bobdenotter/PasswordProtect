<?php

namespace Bolt\Extension\Bolt\PasswordProtect\Twig;

use Bolt\Extension\Bolt\PasswordProtect\Handler\Checker;
use Silex\Application;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig_Loader_Filesystem;
use Twig_Environment;
use Twig_Markup;

class PasswordProtectExtension
{
    /** @var FormFactoryInterface $form */
    protected $form;

    /** @var array $config */
    protected $config;

    /** @var Checker $checker */
    protected $checker;

    /** @var Request $request */
    protected $request;

    /** @var Session $session */
    protected $session;

    /** @var Twig_Loader_Filesystem $twigFilesystem */
    protected $twigFilesystem;

    /** @var Twig_Environment $view */
    protected $view;

    /** @var Application $app */
    protected $app;

    /**
     * PasswordProtectExtension constructor.
     *
     * @param Application $app
     * @param array $config
     */
    public function __construct(Application $app, array $config)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * Show the password form. If the visitor gives the correct password, they
     * are redirected to the page they came from, if any.
     *
     * @return \Twig_Markup
     */
    public function passwordForm()
    {
        // Set up the form.
        $form = $this->app['form.factory']->createBuilder('form');

        if ($this->config['password_only'] == false) {
            $form->add('username', 'text');
        }

        $form->add('password', 'password');
        $form = $form->getForm();

        $request = $this->app['request_stack']->getCurrentRequest();

        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);

            $data = $form->getData();

            if ($form->isValid() && $this->app['passwordprotect.handler.checker']->checkLogin($data)) {
                // Set the session var, so we're authenticated..
                $this->app['session']->set('passwordprotect', 1);
                $this->app['session']->set('passwordprotect_name', $this->app['passwordprotect.handler.checker']->checkLogin($data));

                // Print a friendly message..
                printf("<p class='message-correct'>%s</p>", $this->config['message_correct']);

                $returnto = $request->get('returnto');

                // And back we go, to the page we originally came from..
                if (!empty($returnto)) {
                    $redirect = new RedirectResponse($returnto);
                    $redirect->send();
                }

            } else {
                // Remove the session var, so we can test 'logging off'..
                $this->app['session']->remove('passwordprotect');
                $this->app['session']->remove('passwordprotect_name');

                // Print a friendly message..
                if (!empty($data['password'])) {
                    printf("<p class='message-wrong'>%s</p>", $this->config['message_wrong']);
                }

            }
        }

        if (!empty($this->config['form'])) {
            $formView = $this->config['form'];
        } else {
            $formView = 'passwordform.twig';
        }

        // Render the form, and show it it the visitor.
        //$this->twigFilesystem->addPath(__DIR__);
        $html = $this->app['twig']->render($formView, ['form' => $form->createView()]);

        return new Twig_Markup($html, 'UTF-8');
    }

    public function passwordProtect()
    {
        $this->app['passwordprotect.handler.checker']->passwordProtect();
    }
}
