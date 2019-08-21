<?php

namespace Sprint\Migration;

use ReflectionClass;
use ReflectionException;
use Sprint\Migration\Exceptions\RestartException;


trait ExchangeTrait
{
    /**
     * @var array
     */
    protected $params = [];

    /**
     * @throws RestartException
     */
    public function restart()
    {
        Throw new RestartException();
    }

    /**
     * @return array
     */
    public function getRestartParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setRestartParams($params = [])
    {
        $this->params = $params;
    }

    /**
     * @param $name
     * @return string
     */
    public function getResource($name)
    {
        try {
            $classInfo = new ReflectionClass($this);
            $file = dirname($classInfo->getFileName()) . '/' . $classInfo->getShortName() . '_files/' . $name;
            $file = is_file($file) ? $file : '';
        } catch (ReflectionException $e) {
            $file = '';
        }

        $this->exitIfEmpty($file, 'resource not found');
        return $file;
    }

    public function getClassName()
    {
        try {
            $classInfo = new ReflectionClass($this);
            $name = $classInfo->getShortName();
        } catch (ReflectionException $e) {
            $name = '';
        }

        $this->exitIfEmpty($name, 'class not found');
        return $name;
    }
}