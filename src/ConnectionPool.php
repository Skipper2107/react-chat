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

class ConnectionPool implements \Iterator
{
    const HELP = '/help';
    const CHANGE_NAME = '/name';
    const CHANGE_COLOR = '/color';
    const NOTIFY = '/private';

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
            $connection->getRootConnection()->close();
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
            case self::NOTIFY:
                $this->private($connection, $parts[1] ?? null, $parts[2] ?? null);
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

    public function sendAsServer($toAll, $toOne, Connection $except)
    {
        if (!is_null($toAll)) {
            $toAll = $this->prepareServerEcho($toAll);
            $this->sendAll($toAll, $except);
        }
        $toOne = $this->prepareServerEcho($toOne);
        $except->getRootConnection()->write("\r" . $toOne . PHP_EOL);
    }

    public function prepareServerEcho($text): string
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

    private function private (Connection $connection, $receiver, $msg)
    {
        $text = 'Private from ' . $connection->getSignature() . ': ';
        /** @var Connection $innerConnection */
        foreach ($this->connections as $innerConnection) {
            if ($innerConnection->getSignature() == $receiver) {
                $this->sendAsServer(null, $text . $msg, $innerConnection);
            }
        }
    }

    private function log($action, Connection $connection)
    {
        $text = $this->prepareServerEcho($action . ' ' . $connection->getSignature());
        echo $text . PHP_EOL;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->connections->current();
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        $this->connections->next();
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->connections->key();
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->connections->valid();
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->connections->rewind();
    }

    public function restart()
    {
        $this->connections->removeAll($this->connections);
        $this->config->flush();
    }
}