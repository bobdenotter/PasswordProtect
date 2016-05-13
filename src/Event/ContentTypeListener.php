<?php

namespace Bolt\Extension\Bolt\PasswordProtect\Event;

use Symfony\Component\HttpFoundation\Request;

class ContentTypeListener
{

    /** @var array $config */
    protected $config;

    /** @var Request $request */
    protected $request;

    public function __construct(array $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
