<?php

declare(strict_types=1);

namespace Totaldev\TgClient\Exception;

use Exception;
use JsonSerializable;
use Throwable;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class JsonException extends Exception
{
    public function __construct(
        string                              $message = "",
        int                                 $code = 0,
        ?Throwable                          $previous = null,
        private array|JsonSerializable|null $object = null
    ) {
        $message .= ' Object: ' . var_export($object, true);
        parent::__construct($message, $code, $previous);
    }
}
