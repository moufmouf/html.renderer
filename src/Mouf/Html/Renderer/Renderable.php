<?php

namespace Mouf\Html\Renderer;

use Mouf;
use Mouf\RootContainer\RootContainer;

/**
 * Classes using this trait will have an automatic implementation of the toHtml method provided that
 * will call the default rendering system.
 *
 * @author David Négrier <david@mouf-php.com>
 */
trait Renderable
{

    /**
     * @var string
     */
    protected $context = null;

    /**
     * Returns an array containing all the public and protected properties.
     *
     * @return array
     */
    public function getPrivateProperties()
    {
        return get_object_vars($this);
    }

    /**
     * Set the context, this is a string that is appended the template file name
     * @param string $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    public function toHtml()
    {
        RootContainer::get('defaultRenderer')->render($this, $this->context);
    }
}
