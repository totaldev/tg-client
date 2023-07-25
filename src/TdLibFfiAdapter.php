<?php

declare(strict_types=1);

namespace Totaldev\TdClient;

use FFI;
use JsonException as PHPJsonException;
use Totaldev\TdClient\Exception\AdapterException;
use Totaldev\TdClient\Exception\JsonException;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class TdLibFfiAdapter implements AdapterInterface
{
    private const TDLIB_HEADER_FILE = <<<HEADER
void *td_json_client_create();
void td_json_client_send(void *client, const char *request);
const char *td_json_client_receive(void *client, double timeout);
const char *td_json_client_execute(void *client, const char *request);
void td_json_client_destroy(void *client);
HEADER;

    private FFI $ffi;

    private FFI\CData $client;

    /**
     * @param string|null $libFile An optional file path/name to `libtdjson.so` library
     *
     * @throws AdapterException
     */
    public function __construct(string $libFile = null)
    {
        $libFile = $libFile ?? $this->getLibFilename();

        try {
            $this->ffi = FFI::cdef(static::TDLIB_HEADER_FILE, $libFile);
        } catch (FFI\Exception $exception) {
            throw new AdapterException(sprintf('Failed loading TdLib library "%s"', $libFile));
        }
    }

    private function getLibFilename(): string
    {
        switch (PHP_OS_FAMILY) {
            case 'Darwin':
                return 'libtdjson.dylib';

            case 'Windows':
                return 'tdjson.dll';

            case 'Linux':
                return 'libtdjson.so';
            default:
                throw new AdapterException('Please specify tdjson library file');
        }
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
    public function receive(float $timeout): ?array
    {
        $response = $this->ffi->td_json_client_receive($this->getClient(), $timeout);

        if (null === $response) {
            return null;
        }

        try {
            return json_decode($response, true, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage());
        }
    }

    private function getClient(): FFI\CData
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
    public function send($request): void
    {
        try {
            $json = json_encode($request, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage());
        }

        $this->ffi->td_json_client_send($this->getClient(), $json);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($request): ?array
    {
        $json = json_encode($request, JSON_THROW_ON_ERROR);

        $response = $this->ffi->td_json_client_execute(null, $json);

        if (null === $response) {
            return null;
        }

        try {
            return json_decode($response, true, JSON_THROW_ON_ERROR);
        } catch (PHPJsonException $e) {
            throw new JsonException($e->getMessage());
        }
    }
}
