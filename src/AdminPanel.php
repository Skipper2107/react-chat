<?php
/**
 * Created by PhpStorm.
 * User: skipper
 * Date: 06.08.17
 * Time: 10:49
 */

namespace Chat;


class AdminPanel
{
    const USER_LIST = '/list';
    const DISCONNECT = '/drop';
    const STOP = '/restart';
    const PRIVATE = '/private';
    const ALL = '/emit';

    protected $pool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->pool = $connectionPool;
    }

    public function acceptData($data)
    {
        $parts = explode('_', $data);
        if (!(bool)count($parts)) {
            return;
        }
        switch ($parts[0]) {
            case self::USER_LIST:
                $this->all();
                break;
            case self::DISCONNECT:
                $this->disconnect($parts[1] ?? null);
                break;
            case self::STOP:
                $this->stop();
                break;
            case self::PRIVATE:
                $this->private($parts[1] ?? null, $parts[2] ?? null);
                break;
            case self::ALL:
                $this->emit($parts[1] ?? null);
                break;
            default:
                echo $this->pool->prepareServerEcho('Invalid command');
        }
    }

    private function all()
    {
        $text = '';
        /** @var Connection $connection */
        foreach ($this->pool as $connection) {
            $text .= PHP_EOL . $connection->getSignature();
        }
        echo $this->pool->prepareServerEcho($text);
    }

    private function disconnect($signature)
    {
        $connections = $this->findConnectionBySignature($signature);
        foreach ($connections as $connection) {
            $this->pool->sendAsServer('User ' . $connection->getSignature() . ' has been removed',
                'You are being vanished from server', $connection);
            $connection->getRootConnection()->emit('close');
        }
    }

    private function findConnectionBySignature($signature)
    {
        $connections = [];
        foreach ($this->pool as $connection) {
            if ($connection->getSignature() == $signature) {
                $connections[] = $connection;
            }
        }
        return $connections;
    }

    private function stop()
    {
        $this->pool->restart();
    }

    private function private ($destination, $text)
    {
        $connections = $this->findConnectionBySignature($destination);
        foreach ($connections as $connection) {
            $this->pool->sendAsServer(null, $text, $connection);
        }
    }

    private function emit($msg)
    {
        /** @var Connection $connection */
        foreach ($this->pool as $connection) {
            $this->pool->sendAsServer(null, $msg, $connection);
        }
    }
}