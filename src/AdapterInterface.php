<?php

declare(strict_types=1);

namespace Totaldev\TgClient;

use JsonSerializable;
use Totaldev\TgClient\Exception\AdapterException;
use Totaldev\TgClient\Exception\JsonException;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
interface AdapterInterface
{
    /**
     * Synchronously executes TDLib request. Only a few requests can be executed synchronously. Can be executed before
     * initialisation.
     *
     * @throws JsonException
     */
    public function execute(array|JsonSerializable $request): ?array;

    /**
     * Receives incoming updates and request responses from the TDLib client.
     *
     * @param float $timeout the maximum number of seconds allowed for this function to wait for new data
     *
     * @throws JsonException
     */
    public function receive(float $timeout): ?array;

    /**
     * Sends request to the TDLib client.
     *
     * @throws JsonException
     */
    public function send(array|JsonSerializable $request): static;
}
