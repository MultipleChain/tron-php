<?php

namespace MultipleChain\Tron;

use Exception;
use MultipleChain\Utils;

final class Transaction
{
    /**
     * @var Provider
     */
    private $provider;
    
    /**
     * Transaction hash
     * @var string
     */
    private $hash;

    /**
     * Transaction data
     * @var object
     */
    private $data;

    /**
     * @param string $hash
     * @param Provider $provider
     * @throws Exception
     */
    public function __construct(string $hash, Provider $provider)
    {
        $this->provider = $provider;

        $this->hash = $hash;

        $this->data = $this->getData();
    }

    /**
     * @return string
     */
    public function getHash() : string
    {
        return $this->hash;
    }

    /**
     * @return object|null
     */
    public function getData() : ?object
    {
        $data = $this->provider->getTransaction($this->getHash());
        $data->info = $this->provider->getTransactionInfo($this->getHash());
        return $this->data = $data;
    }

    /**
     * @return object|null
     */
    public function decodeInput() : ?object
    {
        $input = $this->data->raw_data->contract[0]->parameter->value->data;

        if ($input != '0x') {
            $receiver = substr(substr($input, 0, 72), 30);
            $pattern = '/.+?(?='.$receiver.')/';
            preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE, 0);
            $input = str_replace([$matches[0][0], $receiver], '', $input);
            $receiver = $this->provider->tron->fromHex($receiver);
            $amount = '0x' . ltrim($input, 0);
            return (object) compact('receiver', 'amount');
        } else {
            return null;
        }
    }

    /** 
     * @return int
     */
    public function getConfirmations() : int
    {
        try {
            $currentBlock = $this->provider->getCurrentBlock();
            if ($this->data->info->blockNumber === null) return 0;
            
            $confirmations = $currentBlock->block_header->raw_data->number - $this->data->info->blockNumber;
            return $confirmations < 0 ? 0 : $confirmations;
        } catch (Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return bool
     */
    public function validate() : bool
    {
        $result = null;

        if ($this->data == null) {
            $result = false;
        } else {
            if (isset($this->data->info->blockNumber)) {
                if ($this->data->ret[0]->contractRet == 'REVERT') {
                    $result = false;
                } elseif (isset($this->data->info->result) && $this->data->info->result == 'FAILED') {
                    $result = false;
                } else {
                    $result = true;
                }
            }
        }

        if (is_bool($result)) {
            return $result;
        } else {
            return $this->validate();
        }
    }

    /**
     * @param string receiver 
     * @param int amount 
     * @param string address 
     * @return bool
     */
    public function verifyTokenTransferWithData(string $receiver, float $amount, string $address) : bool
    {
        if ($this->validate()) {
            $decodedInput = $this->decodeInput();
            $token = $this->provider->tron->contract($address);
            
            $data = (object) [
                'receiver' => strtolower($decodedInput->receiver),
                'amount' => Utils::toDec($decodedInput->amount, $token->decimals())
            ];

            if ($data->receiver == strtolower($receiver) && strval($data->amount) == strval($amount)) {
                return true;
            }

            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string receiver 
     * @param int amount 
     * @return bool
     */
    public function verifyCoinTransferWithData(string $receiver, float $amount) : bool 
    {
        if ($this->validate()) {
            $params = $this->data->raw_data->contract[0]->parameter->value;
            $data = (object) [
                "receiver" => strtolower($this->provider->tron->fromHex($params->to_address)),
                "amount" => floatval(Utils::toDec($params->amount, 6))
            ];

            if ($data->receiver == strtolower($receiver) && strval($data->amount) == strval($amount)) {
                return true;
            }

            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string receiver
     * @param int amount
     * @param string|null tokenAddress
     * @return bool
     */
    public function verifyTransferWithData(string $receiver, float $amount, ?string $tokenAddress = null) : bool
    {
        if (!$tokenAddress) {
            return $this->verifyCoinTransferWithData($receiver, $amount);
        } else {
            return $this->verifyTokenTransferWithData($receiver, $amount, $tokenAddress);
        }
    }

    /**
     * @return string
     */
    public function getUrl() 
    {
        $explorerUrl = $this->provider->network->explorer;
        $explorerUrl .= '#/transaction/' . $this->hash;
        return $explorerUrl;
    }
}