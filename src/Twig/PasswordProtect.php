<?php

namespace Bolt\Extension\Bobdenotter\PasswordProtect\Twig;

use Bolt\Collection\Bag;
use Bolt\Extension\Bobdenotter\PasswordProtect\Handler\Checker;
use Silex\Application;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig_Loader_Filesystem;
use Twig_Environment;
use Twig_Markup;

class PasswordProtect
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
     * @param Bag         $config
     */
    public function __construct(Application $app, Bag $config)
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
        $notices = [];

        // If the config still specifies either 'plain text' or 'md5', tell the user to update,
        // since only password_hash is supported now.
        if ($this->config['encryption'] !== 'password_hash') {
            $message = 'This extension only supports hashing with the <tt>password_hash</tt> mechanism. Please update your configuration file.';
            $notices[] = sprintf("<p class='message message-wrong'>%s</p>", $message);
        }

        // Fetch the labels
        $labels = $this->config->get('labels');

        // Set up the form.
        $form = $this->app['form.factory']->createBuilder('form');

        if ($this->config['password_only'] == false) {
            $form->add('username', 'text', ['label'=>$labels['username']]);
        }

        $form->add('password', 'password', ['label'=>$labels['password']]);
        $form = $form->getForm();

        $request = $this->app['request_stack']->getCurrentRequest();

        if ($request->getMethod() != 'POST') {
            if ($this->passwordProtectUsername()) {
                // Print a "Already logged in" message..
                $notices[] = sprintf("<p class='message'>%s</p>", $this->config['labels']['message_alreadyloggedin']);
            } else {
                // Print a "Please log on" message..
                $notices[] = sprintf("<p class='message'>%s</p>", $this->config['labels']['message_default']);
            }
        } else {
            $form->handleRequest($request);

            $data = $form->getData();

            if ($form->isValid() && $this->app['passwordprotect.handler.checker']->checkLogin($data)) {
                // Set the session var, so we're authenticated..
                $this->app['session']->set('passwordprotect', 1);
                $this->app['session']->set('passwordprotect_name', $this->app['passwordprotect.handler.checker']->checkLogin($data));

                // Print a friendly message..
                $notices[] = sprintf("<p class='message message-correct'>%s</p>", $this->config['labels']['message_correct']);

                $returnto = $request->get('returnto');

                // And back we go, to the page we originally came from..
                if (!empty($returnto)) {
                    $redirect = new RedirectResponse($returnto);
                    echo($redirect->getContent());
                }

            } else {
                // Remove the session var, so we can test 'logging off'..
                $this->app['session']->remove('passwordprotect');
                $this->app['session']->remove('passwordprotect_name');

                // Print a friendly message..
                if (!empty($data['password'])) {
                    $notices[] = sprintf("<p class='message message-wrong'>%s</p>", $this->config['labels']['message_wrong']);
                }

            }
        }

        if (!empty($this->config['form'])) {
            $formView = $this->config['form'];
        } else {
            $formView = 'passwordform.twig';
        }

        // Render the form, and show it it the visitor.
        $twigData = [
            'form' => $form->createView(),
            'notice' => new \Twig_Markup(implode('', $notices), 'UTF-8'),
            'labels' => $labels
        ];

        $html = $this->app['twig']->render($formView, $twigData);

        return new Twig_Markup($html, 'UTF-8');
    }

    public function passwordProtect()
    {
        $this->app['passwordprotect.handler.checker']->passwordProtect();
    }

    public function passwordProtectUsername()
    {
        return $this->app['session']->get('passwordprotect_name', null);
    }
}
