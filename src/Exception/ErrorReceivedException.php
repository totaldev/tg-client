<?php

declare(strict_types=1);

namespace Totaldev\TgClient\Exception;

use Totaldev\TgSchema\Error\Error;

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

    public function getEnumEquals(ErrorReceivedEnum $enum): bool
    {
        return ErrorReceivedEnum::compareRawCode($this->getError()->getMessage(), $enum);
    }

    public function getEnumMessage(): ?ErrorReceivedEnum
    {
        return ErrorReceivedEnum::getByRawCode($this->getError()->getMessage());
    }

    public function getError(): Error
    {
        return $this->error;
    }

    public function isEnum(): bool
    {
        return (bool)ErrorReceivedEnum::getEnumFixCode($this->getError()->getMessage());
    }
}
