<?php

namespace Bolt\Extension\Bolt\PasswordProtect\Handler;

use Bolt\Twig\Handler\UtilsHandler;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
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

    public function __construct(
        Application $app,
        array $config
    ) {
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

        //Grab key 1 that has members-only
        if ($path !== null) {
            $contenttype = (array) $this->config['contenttype'];

            //Check if members-only is the same contenttype in our config file
            if (in_array($path, $contenttype)) {
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
            (new UtilsHandler($this->app))->redirect($redirectto->link(). "?returnto=" . urlencode($returnto));
            die();
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
            $visitors = array('visitor' => $this->config['password']);
            $data['username'] = 'visitor';
        } else {
            $visitors = $this->config['visitors'];
        }

        foreach ($visitors as $visitor => $password) {
            if ($data['username'] === $visitor) {
                // echo "user match!";
                if (($this->config['encryption'] == 'md5') && (md5($data['password']) === $password)) {
                    return $visitor;
                } elseif (($this->config['encryption'] == 'password_hash') && password_verify($data['password'], $password)) {
                    return $visitor;
                } elseif (($this->config['encryption'] == 'plaintext') && ($data['password'] === $password))  {
                    return $visitor;
                }
            }
        }

        // If we get here, no dice.
        return false;

    }
}
