<?php
/**
 * Created by PhpStorm.
 * User: skipper
 * Date: 05.08.17
 * Time: 17:46
 */

namespace Chat;


use Chat\Utils\ColorDeduction;
use Chat\Utils\Config;
use React\Socket\ConnectionInterface;

class Connection
{
    /** @var ConnectionInterface $connection */
    protected $connection;
    /** @var string $key */
    protected $key;
//    /** @var string $address */
//    protected $address;
    /** @var Config $config */
    protected $config;

    public function __construct(ConnectionInterface $conn, Config $config)
    {
//        $this->key = $conn->getRemoteAddress();
        $this->key = parse_url($conn->getRemoteAddress(), PHP_URL_HOST);
        $this->config = $config;
        $this->connection = $conn;
    }

    public function makePretty($data): string
    {
        return ColorDeduction::paint($this->getSignature() . ':' . $data, $this->config->getColor($this->key));
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        $sender = is_null($this->config->getName($this->key)) ? $this->key : $this->config->getName($this->key) . '@' . $this->key;
        return '[' . $sender . ']';
    }

    /**
     * @return ConnectionInterface
     */
    public function getRootConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}