<?php

declare(strict_types=1);

namespace Totaldev\TgClient\Exception;

use Totaldev\Schema\Error;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class ErrorReceivedException extends TgClientException
{
    private Error $error;

    public function __construct(Error $error)
    {
        $this->error = $error;

        parent::__construct(
            sprintf('Received Error Packet %d: "%s"', $error->getCode(), $error->getMessage()),
            $error->getCode()
        );
    }

    public function getError(): Error
    {
        return $this->error;
    }
}
