<?php

declare(strict_types=1);

namespace Totaldev\TgClient;

use FFI;
use JsonException as PHPJsonException;
use JsonSerializable;
use Totaldev\TgClient\Exception\AdapterException;
use Totaldev\TgClient\Exception\JsonException;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class TdLibFfiAdapter implements AdapterInterface
{
    private const string TDLIB_HEADER_FILE = <<<HEADER
void *td_json_client_create();
void td_json_client_send(void *client, const char *request);
const char *td_json_client_receive(void *client, double timeout);
const char *td_json_client_execute(void *client, const char *request);
void td_json_client_destroy(void *client);
void td_set_log_verbosity_level(int new_verbosity_level);
HEADER;

    private FFI\CData $client;

    private FFI $ffi;

    /**
     * @param string|null $libFile An optional file path/name to `libtdjson.so` library
     *
     * @throws AdapterException
     */
    public function __construct(string $libFile = null, int $logLevel = 4)
    {
        $libFile = $libFile ?? $this->getLibFilename();

        try {
            $this->ffi = FFI::cdef(static::TDLIB_HEADER_FILE, $libFile);
        } catch (FFI\Exception $e) {
            throw new AdapterException(
                sprintf('Failed loading TdLib library "%s"' . PHP_EOL . $e->getMessage(), $libFile)
            );
        }
        $this->setVerbosityLevel($logLevel);
    }

    public function __destruct()
    {
        if (isset($this->client)) {
            $this->ffi->td_json_client_destroy($this->client);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array|JsonSerializable $request): ?array
    {
        try {
            $json = json_encode($request, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage());
        }

        $response = $this->ffi->td_json_client_execute(null, $json);

        if (null === $response) {
            return null;
        }

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage(), $e->getCode(), null, $response);
        }
    }

    public function getClient(): FFI\CData
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $this->client = $this->ffi->td_json_client_create();

        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(float $timeout): ?array
    {
        $response = $this->ffi->td_json_client_receive($this->getClient(), $timeout);

        if (null === $response) {
            return null;
        }

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage(), $e->getCode(), null, $response);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(array|JsonSerializable $request): static
    {
        try {
            $json = json_encode($request, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage(), $e->getCode(), null, $request);
        }

        $this->ffi->td_json_client_send($this->getClient(), $json);

        return $this;
    }

    public function setVerbosityLevel(int $logLevel): static
    {
        $this->ffi->td_set_log_verbosity_level($logLevel);

        return $this;
    }

    /**
     * @throws AdapterException
     */
    private function getLibFilename(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'libtdjson.dylib',
            'Windows' => 'tdjson.dll',
            'Linux' => 'libtdjson.so',
            default => throw new AdapterException('Please specify tdjson library file'),
        };
    }
}
