<?php
/**
 * Created by PhpStorm.
 * User: skipper
 * Date: 05.08.17
 * Time: 17:44
 */

namespace Chat;


use Chat\Utils\ColorDeduction;
use Chat\Utils\Config;

class ConnectionPool
{
    const HELP = '/help';
    const CHANGE_NAME = '/name';
    const CHANGE_COLOR = '/color';

    protected $connections;
    protected $commands;
    protected $config;

    public function __construct(Config $config)
    {
        $this->connections = new \SplObjectStorage();
        $this->commands = $this->getCommands();
        $this->config = $config;
    }

    private function getCommands()
    {
        $class = new \ReflectionClass(static::class);
        $commands = $class->getConstants();
        unset($class);
        return $commands;
    }

    public function add(Connection $connection)
    {
        $connection->getRootConnection()->on('data', function ($data) use ($connection) {
            $data = trim($data);
            if (empty($data)) {
                return;
            }
            if ($this->isCommand($data)) {
                $this->resolveCommand($data, $connection);
            } else {
                $this->sendAll($connection->makePretty($data), $connection);
            }
        });
        $connection->getRootConnection()->on('error', function (\Exception $exception) use ($connection) {
            $this->sendAsServer(null, 'Error' . $exception->getMessage(), $connection);
        });
        $connection->getRootConnection()->on('close', function () use ($connection) {
            $this->connections->detach($connection);
            $this->sendAsServer('User ' . $connection->getSignature() . ' has left :(', 'Goodbye)', $connection);
            $this->log('close', $connection);
            unset($connection);
        });
        $this->connections->attach($connection);
        $this->sendAsServer('User ' . $connection->getSignature() . ' has joined :)', 'Speak up!', $connection);
        $this->log('add', $connection);
    }

    private function isCommand($data): bool
    {
        return strpos($data, '/') === 0;
    }

    private function resolveCommand($data, Connection $connection)
    {
        $parts = explode(' ', $data);
        $key = array_search($parts[0], $this->commands);
        switch ($this->commands[$key] ?? null) {
            case self::HELP:
                $this->help($connection);
                break;
            case self::CHANGE_NAME:
                $this->name($connection, $parts[1] ?? null);
                break;
            case self::CHANGE_COLOR:
                $this->color($connection, $parts[1] ?? null);
                break;
            default:
                $this->sendAsServer(null, 'Invalid Command', $connection);
        }
    }

    private function help(Connection $connection)
    {
        $text = '/help' . PHP_EOL;
        $text .= '/name {new_name}' . PHP_EOL;
        $text .= '/color {red|green|blue|white|yellow}' . PHP_EOL;
        $this->sendAsServer(null, $text, $connection);
    }

    protected function sendAsServer($toAll, $toOne, Connection $except)
    {
        if (!is_null($toAll)) {
            $toAll = $this->prepareServerEcho($toAll);
            $this->sendAll($toAll, $except);
        }
        $toOne = $this->prepareServerEcho($toOne);
        $except->getRootConnection()->write("\r" . $toOne . PHP_EOL);
    }

    private function prepareServerEcho($text): string
    {
        return ColorDeduction::paint('Server says: ' . $text, 'red+yellow_bg');
    }

    protected function sendAll($text, Connection $except)
    {
        $address = $except->getRootConnection()->getRemoteAddress();
        $text = $text . PHP_EOL;
        /** @var Connection $connection */
        foreach ($this->connections as $connection) {
            if ($connection->getRootConnection()->getRemoteAddress() != $address) {
                $connection->getRootConnection()->write("\r" . $text);
            }
        }
    }

    private function name(Connection $connection, $name)
    {
        $key = $connection->getKey();
        $this->config->setName($key, $name);
        $toAll = 'User has changed his name to ' . $this->config->getName($key);
        $toOne = 'You name has been changed';
        $this->sendAsServer($toAll, $toOne, $connection);
    }

    private function color(Connection $connection, $color)
    {
        $this->config->setColor($connection->getKey(), $color);
        $this->sendAsServer(null, 'Color has been changed', $connection);
    }

    private function log($action, Connection $connection)
    {
        $text = $this->prepareServerEcho($action . ' ' . $connection->getSignature());
        echo $text . PHP_EOL;
    }
}