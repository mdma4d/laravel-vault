<?php
namespace Mdma4d\Vault;

use RuntimeException;

class VaultException extends RuntimeException 
{

    public $response;

    public function __construct($message, $code = null, $response = null)
    {
        parent::__construct($message, $code);
        $this->response = $response;
    }

    public function response()
    {
        return $this->response;
    }

}

