<?php

/**
 * @file
 */

namespace Phloem\Action;

use Phloem\Actions\NullAction;
use Phloem\Actions\Structural\SeriesAction;
use Phloem\Exception\ActionFactoryException;
use Phloem\Exception\ExecutionException;
use Phloem\Expression\Context;
use Phloem\Phloem;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;

/**
 * Class Factory
 *
 * @package Phloem
 */
class Factory
{

    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    protected $actions = [
      'command' => 'Phloem\\Actions\\Command\\CommandAction',
      'echo' => 'Phloem\\Actions\\Output\\EchoAction',
      'if' => 'Phloem\\Actions\\Structural\\IfAction',
      'include' => 'Phloem\\Actions\\File\\IncludeAction',
      'loop' => 'Phloem\\Actions\\Structural\\LoopAction',
      'noop' => 'Phloem\\Actions\\NullAction',
      'null' => 'Phloem\\Actions\\NullAction',
      'series' => 'Phloem\\Actions\\Structural\\SeriesAction',
      'set' => 'Phloem\\Actions\\Structural\\SetAction',
      'task' => 'Phloem\\Actions\\Structural\\TaskAction',
      'unset' => 'Phloem\\Action\\UnsetAction',
      'while' => 'Phloem\\Actions\\Structural\\WhileAction',
    ];

    /**
     * Factory constructor.
     *
     * @param \Psr\Container\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Get the dependency injection container.
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     * Get the action by $name.
     *
     * @param string $name
     *
     * @return \Phloem\Action\ActionInterface
     *
     * @throws \Phloem\Exception\ActionFactoryException
     */
    public function getAction($name) {
        // Default to actions defined by this factory.
        if (array_key_exists($name, $this->actions)) {
            // Use lazy-loading for the actions.
            if (is_string($this->actions[$name])) {
                $class = $this->actions[$name];
                return new $class();
            }

            // Use lazy-loading via callable.
            if ($this->actions[$name] instanceof \Closure) {
                return $this->actions[$name]($this);
            }

            // We have the action already, so return a clone..
            if ($this->actions[$name] instanceof ActionInterface) {
                return clone($this->actions[$name]);
            }

            throw new ActionFactoryException("{$name} does not have a valid creation factory.");
        }

        throw new ActionFactoryException("{$name} action is not defined.");
    }

    /**
     * Set an action by $name.
     *
     * @param string $name
     * @param $action
     *
     * @return static
     */
    public function setAction($name, $action)
    {
        $this->actions[$name] = $action;
        return $this;
    }

    /**
     * Indicate an action has been defined for $name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAction($name) {
        return array_key_exists($name, $this->actions);
    }

    /**
     * Creates an action execution tree.
     *
     * @param $config
     *
     * @return \Phloem\Action\ActionInterface
     *
     * @throws \Phloem\Exception\ConfigException
     * @throws \Phloem\Exception\ActionFactoryException
     */
    public function process($config) {
        $container = $this->getContainer();

        // Configuration is a string without configuration, so a simple action.
        if (is_string($config)) {
            $action = $this->getAction($config);
            $action->setup($container, []);
            return $action;
        }

        // Otherwise ensure the configuration is an array.
        if (!is_array($config)) {
            throw new ActionFactoryException('Configuration not valid.');
        }

        // No configuration so it's a null action.
        if (!count($config)) {
            return new NullAction();
        }

        // The config is a sequential array, so use a series.
        if (array_keys($config) === range(0, count($config) - 1)) {
            $action = new SeriesAction();
            $action->setup($container, ['series' => $config]);
            return $action;
        }

        // The first key of the object is the action.
        $key = array_keys($config)[0];
        if ($this->hasAction($key)) {
            $action = $this->getAction($key);
            $action->setup($container, $config);
            return $action;
        }

        throw new ActionFactoryException('Unable to locate the appropriate action for the config.');
    }


    /**
     * Evaluates an expression string.
     *
     * @param \Phloem\Action\ActionInterface $action
     * @param string|\Xylemical\Expressions\Token[] $string
     * @param \Phloem\Expression\Context $context
     *
     * @return string
     *
     * @throws \Phloem\Exception\ExecutionException
     */
    public function evaluate(ActionInterface $action, $string, Context $context)
    {
        try {
            // Parse only when needed.
            if (is_string($string)) {
                $tokens = $this->getContainer()
                               ->get(Phloem::PARSER)
                               ->parse($string);

            }
            // Otherwise assume we've correctly passed the token list.
            else {
                $tokens = $string;
            }
            return $this->getContainer()
                        ->get(Phloem::EVALUATOR)
                        ->evaluate($tokens, $context);
        }
        catch (ContainerExceptionInterface $e) {
            throw new ExecutionException($action, $e->getMessage(), $e->getCode(), $e);
        }
        catch (\Exception $e) {
            throw new ExecutionException($action, $e->getMessage(), $e->getCode(), $e);
        }
    }
}
