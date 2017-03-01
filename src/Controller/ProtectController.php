<?php

namespace Bolt\Extension\Bolt\PasswordProtect\Controller;

use PasswordLib\PasswordLib;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

class ProtectController implements ControllerProviderInterface
{
    /** @var Application $app */
    protected $app;

    /** @var array $config */
    protected $config;

    /**
     * ProtectController constructor.
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
     * @param Application $app
     * @return mixed
     */
    public function connect(Application $app)
    {
        $controller = $app['controllers_factory'];

        $controller->match('/generatepasswords', [$this, 'generatepasswords']);
        $controller->match('/changePassword', [$this, 'changePassword']);

        //This must be ran, current user is not set at this time.
        $controller->before([$this, 'before']);

        return $controller;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return null|RedirectResponse
     */
    public function before(Request $request, Application $app)
    {
        if (!$app['users']->isAllowed('dashboard')) {
            /** @var UrlGeneratorInterface $generator */
            $generator = $app['url_generator'];
            return new RedirectResponse($generator->generate('dashboard'), Response::HTTP_SEE_OTHER);
        }

        return null;
    }

    /**
     * @return \Twig_Markup
     */
    public function generatepasswords()
    {
        // Set up the form.
        $form = $this->app['form.factory']->createBuilder('form');
        $form->add('password', 'text');
        $form = $form->getForm();

        $password = false;

        if ($this->app['request']->getMethod() == 'POST') {
            $form->bind($this->app['request']);
            $data = $form->getData();
            if ($form->isValid()) {
                $password = password_hash($data['password'], PASSWORD_BCRYPT);
            }
        }

        // Render the form, and show it it the visitor.
        $context = [
            'form' => $form->createView(),
            'password' => $password,
            'input' => isset($data['password']) ? $data['password'] : ''
        ];

        $html = $this->app['twig']->render('passwordgenerate.twig', $context);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Displays a page that allows the user to change the password without editing the YML files.
     *
     * @return \Twig_Markup
     */
    public function changePassword()
    {
        // Set up the form.
        $form = $this->app['form.factory']->createBuilder('form');
        $form->add('password', 'password');
        $form = $form->getForm();

        $configData = $this->read();

        $plainPassword = false;
        $oldPassword = false;

        if (isset($configData['password']) && $this->config['encryption'] === 'plaintext') {
            $oldPassword = $configData['password'];
        }

        if ($this->app['request']->getMethod() == 'POST') {
            $form->bind($this->app['request']);
            $data = $form->getData();
            if ($form->isValid()) {

                if (isset($configData['password'])) {
                    $plainPassword = $data['password'];
                    $oldPassword = $plainPassword;
                    $hashedPassword = $this->passwordGenerator($plainPassword);
                    $configData['password'] = $hashedPassword;
                    $this->write($configData);
                }

            }
        }

        $context = [
            'form' => $form->createView(),
            'password' => $plainPassword,
            'oldPassword' => $oldPassword
        ];

        // Render the form, and show it it the visitor.
        $html = $this->app['twig']->render('changepassword.twig', $context);

        return new \Twig_Markup($html, 'UTF-8');

    }

    /**
     * Generate a hashed password based upon the users encryption type.
     * @param $password
     * @return string
     */
    protected function passwordGenerator($password)
    {
        switch($this->config['encryption']) {
            case 'plaintext':
                $password = $password;
                break;
            case 'md5':
                $password = md5($password);
                break;
            case 'password_hash':
                $hasher   = new PasswordLib();
                $password = $hasher->createPasswordHash($password, '$2y$');
                break;
        }

        return $password;
    }

    /**
     * Handles reading the Bolt Forms yml file.
     *
     * @return array The parsed data
     */
    protected function read()
    {
        $file = $this->app['resources']->getPath('config/extensions/passwordprotect.bolt.yml');
        $yaml = file_get_contents($file);
        $parser = new Parser();
        $data = $parser->parse($yaml);
        return $data;
    }

    /**
     * Internal method that handles writing the data array back to the YML file.
     *
     * @param array $data
     *
     * @return bool True if successful
     */
    protected function write($data)
    {
        $dumper = new Dumper();
        $dumper->setIndentation(2);
        $yaml = $dumper->dump($data, 9999);
        $file = $this->app['resources']->getPath('config/extensions/passwordprotect.bolt.yml');
        try {
            $response = @file_put_contents($file, $yaml);
        } catch (\Exception $e) {
            $response = null;
        }
        return $response;
    }
}
