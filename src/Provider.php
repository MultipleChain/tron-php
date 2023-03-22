<?php

namespace MultipleChain\Tron;

use Exception;
use \IEXBase\TronAPI\Tron;
use \IEXBase\TronAPI\Provider\HttpProvider;
use \IEXBase\TronAPI\Exception\TronException;

final class Provider
{
    /**
     * @var Tron
     */
    public $tron;

    /**
     *
     * @var array
     */
    private $networks = [
        "mainnet" => [
            "host" => "https://api.trongrid.io/",
            "explorer" => "https://tronscan.io/"
        ],
        "testnet" => [
            "host" => "https://api.nileex.io/",
            "explorer" => "https://nile.tronscan.org/"
        ]
    ];

    /**
     * @var object
     */
    public $network;

    /**
     * @param bool $testnet
     * @throws Exception
     */
    public function __construct(bool $testnet = false) 
    {
        $this->network = (object) $this->networks[$testnet ? 'testnet' : 'mainnet'];

        $fullNode = new HttpProvider($this->network->host);
        $solidityNode = new HttpProvider($this->network->host);
        $eventServer = new HttpProvider($this->network->host);

        try {
            $this->tron = new Tron($fullNode, $solidityNode, $eventServer);
        } catch (TronException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @return object|null
     * @throws Exception
     */
    public function __call(string $method, array $params = [])
    {
        if (preg_match('/^[a-zA-Z0-9]+$/', $method) === 1) {
            return json_decode(json_encode($this->tron->$method(...$params)), false);
        } else {
            throw new Exception('Invalid method name');
        }
    }
    
    /**
     * @param string $hash
     * @return Transaction
     */
    public function Transaction(string $hash) : Transaction
    {
        return new Transaction($hash, $this);
    }
}