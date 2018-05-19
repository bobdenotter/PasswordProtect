<?php

namespace Bolt\Extension\Bobdenotter\PasswordProtect\Handler;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Bolt\Storage;
use Twig_Markup;

class Checker
{

    /** @var Request $request */
    protected $request;

    /** @var array $config */
    protected $config;

    /** @var Session $session */
    protected $session;

    /** @var Storage $storage */
    protected $storage;

    /** @var Application $app */
    protected $app;

    /**
     * Checker constructor.
     *
     * @param Application $app
     * @param array $config
     */
    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Check the content type of the request to see if it is password protected.
     *
     * @param Request $request
     */
    public function checkContentTypeOnRequest(Request $request)
    {
        //get the path, typically /members-only/home
        $path = $request->get('contenttypeslug');

        if ($path == null) {
            return;
        }

        $restrictContenttype = (array) $this->config['contenttype'];

        foreach($this->app['config']->get('contenttypes') as $key => $ct) {
            //check if the slug(singular or multiple) of this contenttype matches
            //the one of our request. If not we should skip this round
            if ($ct['slug'] != $path && $ct['singular_slug'] != $path){
                continue;
            }

            //Check if members-only is the same contenttype in our config file
            if ((in_array($ct['slug'], $restrictContenttype)) || (in_array($ct['singular_slug'], $restrictContenttype))) {
                $this->checkSessionAndRedirect();
            }
        }

    }

    /**
     * Function to check if session is set, otherwise redirect and login
     *
     * @return \Twig_Markup
     */
    protected function checkSessionAndRedirect()
    {
        if ($this->app['session']->get('passwordprotect') == 1) {
            return new Twig_Markup("<!-- Password protection OK! -->", 'UTF-8');
        } else {
            $redirectto = $this->app['storage']->getContent($this->config['redirect'], ['returnsingle' => true]);
            $returnto = $this->app['request_stack']->getCurrentRequest()->getRequestUri();

            // Redirect to new page.
            if ($redirectto && (parse_url($redirectto->link(), PHP_URL_PATH) != parse_url($returnto, PHP_URL_PATH))) {
                $response = new RedirectResponse($redirectto->link(). "?returnto=" . urlencode($returnto));
                $response->send();
                die();
            }

            // If we _should_ redirect, but the page doesn't exist, spit out a message.
            if (!$redirectto) {
                echo "<p>Can not redirect to <tt>" . $this->config['redirect'] ."</tt>. Make sure the record exists, and is published.<p>";
                die();
            }
        }
    }

    /**
     * Check if we're currently allowed to view the page. If not, redirect to
     * the password page.
     *
     * @return \Twig_Markup
     */
    public function passwordProtect()
    {
        $this->checkSessionAndRedirect();
    }

    /**
     * Check if users can be logged on.
     *
     * @return boolean
     */
    public function checkLogin($data)
    {
        if (empty($data['password'])) {
            return false;
        }

        // If we only use the password, the 'users' array is just one element.
        if ($this->config['password_only']) {
            $visitors = ['visitor' => $this->config['password']];
            $data['username'] = 'visitor';
        } else {
            $visitors = $this->config['visitors'];
        }

        foreach ($visitors as $visitor => $password) {
            if (($data['username'] === $visitor) &&
                (password_verify($data['password'], $password))) {
                return $visitor;
            }
        }

        // If we get here, no dice.
        return false;
    }
}
