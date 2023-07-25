<?php

declare(strict_types=1);

namespace Totaldev\TdClient;

use JsonSerializable;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
interface ClientInterface
{
    /**
     * Receives incoming updates and request responses from the TDLib client.
     *
     * @param float $timeout the maximum number of seconds allowed for this function to wait for new data
     *
     * @return array
     *
     * @throws JsonException
     * @throws AdapterException
     */
    public function receive(float $timeout): ?array;

    /**
     * Sends request to the TDLib client.
     *
     * @param array|JsonSerializable $request
     *
     * @throws JsonException
     * @throws AdapterException
     */
    public function send($request): void;

    /**
     * Synchronously executes TDLib request. Only a few requests can be executed synchronously. Can be executed before
     * initialisation.
     *
     * @param array|JsonSerializable $request
     *
     * @return array
     *
     * @throws JsonException
     * @throws AdapterException
     */
    public function execute($request): ?array;
}