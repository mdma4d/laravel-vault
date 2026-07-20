<?php
namespace Mdma4d\Vault;

use RuntimeException;

class VaultException extends RuntimeException 
{

    public $response;

    public function __construct($message, $code = 0, $response = null)
    {
        parent::__construct($message, (int) $code);
        $this->response = $response;
    }

    public function response()
    {
        return $this->response;
    }

}

