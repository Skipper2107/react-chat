<?php
/**
 * Created by PhpStorm.
 * User: skipper
 * Date: 05.08.17
 * Time: 18:13
 */

namespace Chat\Utils;


class Config
{
    const DEFAULT_COLOR = 'off';
    protected $configPool;

    public function __construct()
    {
        $this->configPool = new \stdClass();
    }

    public function getColor($key)
    {
        $config = $this->resolveUserConfig($key);
        if (isset($config->color)) {
            return $config->color;
        }
        $config->color = self::DEFAULT_COLOR;
        return self::DEFAULT_COLOR;
    }

    private function resolveUserConfig($key)
    {
        if (isset($this->configPool->{$key})) {
            return $this->configPool->{$key};
        }
        $this->configPool->{$key} = new \stdClass();
        return $this->configPool->{$key};
    }

    public function getName($key)
    {
        $config = $this->resolveUserConfig($key);
        if (isset($config->name)) {
            return $config->name;
        }
        $config->name = null;
        return null;
    }

    public function setName($key, $name)
    {
        $config = $this->resolveUserConfig($key);
        $config->name = $name;
    }

    public function setColor($key, $color)
    {
        $config = $this->resolveUserConfig($key);
        $config->color = $color;
    }

    public function flush()
    {
        $this->configPool = new \stdClass();
    }
}