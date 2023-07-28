<?php

declare(strict_types=1);

namespace Totaldev\TgClient;

use Totaldev\TgSchema\Error;
use Totaldev\TgSchema\LogStreamDefault;
use Totaldev\TgSchema\LogStreamEmpty;
use Totaldev\TgSchema\LogStreamFile;
use Totaldev\TgSchema\SetLogStream;
use Totaldev\TgSchema\SetLogVerbosityLevel;
use Totaldev\TgSchema\TdFunction;
use Totaldev\TgSchema\TdObject;
use Totaldev\TgSchema\TdSchemaRegistry;
use Totaldev\TgSchema\UpdateOption;
use Totaldev\TgClient\Exception\AdapterException;
use Totaldev\TgClient\Exception\ErrorReceivedException;
use Totaldev\TgClient\Exception\JsonException;
use Totaldev\TgClient\Exception\QueryTimeoutException;
use Totaldev\TgClient\Exception\TgClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class TgClient
{
    private AdapterInterface $adapter;

    private LoggerInterface $logger;

    /** @var TdObject[] */
    private array $packetBacklog;

    public function __construct(AdapterInterface $adapter, LoggerInterface $logger = null)
    {
        $this->adapter = $adapter;
        $this->logger = $logger ?? new NullLogger();
        $this->packetBacklog = [];
    }

    /**
     * Sends packet to TdLib marked with extra identifier and loops till received marked response back or timeout
     * occurs. Stores all in between packets in backlog.
     *
     * @param TdFunction $packet request packet to send to TdLib
     * @param int $timeout the maximum number of seconds allowed for this function to wait for a response
     *                                   packet
     * @param float $receiveTimeout the maximum number of seconds allowed for this function to wait for new data
     *
     * @throws AdapterException
     * @throws ErrorReceivedException
     * @throws JsonException
     * @throws QueryTimeoutException
     */
    public function query(TdFunction $packet, int $timeout = 10, float $receiveTimeout = 0.1): TdObject
    {
        if (null === $packet->getTdExtra()) {
            $packet->setTdExtra(spl_object_hash($packet));
        }

        $extra = $packet->getTdExtra();
        $this->send($packet);

        $startTime = time();
        $obj = null;
        while (true) {
            $obj = $this->receive($receiveTimeout, false);

            if (null === $obj) {
                if ((time() - $startTime) > $timeout) {
                    throw new QueryTimeoutException($packet);
                }

                continue;
            }

            if ($extra === $obj->getTdExtra()) {
                break;
            } else {
                $this->packetBacklog[] = $obj;
            }

            if ((time() - $startTime) > $timeout) {
                throw new QueryTimeoutException($packet);
            }

            usleep(10000);
        }

        return $obj;
    }

    /**
     * @param float $timeout the maximum number of seconds allowed for this function to wait for new data
     * @param bool $processBacklog should process backlog packets
     *
     * @throws AdapterException
     * @throws ErrorReceivedException
     * @throws JsonException
     */
    public function receive(float $timeout, bool $processBacklog = true): ?TdObject
    {
        if (count($this->packetBacklog) > 0 && $processBacklog) {
            return array_shift($this->packetBacklog);
        }

        $response = $this->adapter->receive($timeout);

        if (null === $response) {
            return null;
        }

        $object = TdSchemaRegistry::fromArray($response);

        $this->logger->debug(
            sprintf('Received packet "%s" from TdLib', $object->getTdTypeName()),
            ['packet' => $object]
        );

        if ($object instanceof Error) {
            throw new ErrorReceivedException($object);
        }

        return $object;
    }

    /**
     * Sends packet to TdLib.
     *
     * @param TdFunction $packet request packet to send to TdLib
     *
     * @throws AdapterException
     * @throws JsonException
     */
    public function send(TdFunction $packet): void
    {
        $this->logger->debug(
            sprintf('Sending packet "%s" to TdLib', $packet->getTdTypeName()),
            ['packet' => $packet]
        );

        $this->adapter->send($packet);
    }

    /**
     * @param string $file path to the file to where the internal TDLib log will be written
     * @param int $maxLogFileSize the maximum size of the file to where the internal TDLib log is written before the
     *                               file will be auto-rotated
     *
     * @return $this
     *
     * @throws AdapterException
     * @throws JsonException
     */
    public function setLogToFile(string $file, int $maxLogFileSize = PHP_INT_MAX): self
    {
        $this->adapter->execute(
            new SetLogStream(
                new LogStreamFile($file, $maxLogFileSize)
            )
        );

        return $this;
    }

    /**
     * @return $this
     *
     * @throws AdapterException
     * @throws JsonException
     */
    public function setLogToNone(): self
    {
        $this->adapter->execute(
            new SetLogStream(
                new LogStreamEmpty()
            )
        );

        return $this;
    }

    /**
     * @return $this
     *
     * @throws AdapterException
     * @throws JsonException
     */
    public function setLogToStderr(): self
    {
        $this->adapter->execute(
            new SetLogStream(
                new LogStreamDefault()
            )
        );

        return $this;
    }

    /**
     * @param int $level New value of the verbosity level for logging. Value 0 corresponds to fatal errors, value 1
     *                   corresponds to errors, value 2 corresponds to warnings and debug warnings, value 3 corresponds
     *                   to informational, value 4 corresponds to debug, value 5 corresponds to verbose debug, value
     *                   greater than 5 and up to 1023 can be used to enable even more logging.
     *
     * @return $this
     *
     * @throws AdapterException
     * @throws JsonException
     */
    public function setLogVerbosityLevel(int $level): self
    {
        $this->adapter->execute(
            new SetLogVerbosityLevel($level)
        );

        return $this;
    }

    /**
     * @throws AdapterException
     * @throws JsonException
     * @throws TgClientException
     */
    public function verifyVersion(): void
    {
        /** @var UpdateOption $response */
        $response = $this->receive(10);

        if (!($response instanceof UpdateOption)) {
            throw new TgClientException(sprintf('First packet supposed to be "UpdateOption" received "%s"', $response->getTdTypeName()));
        }

        $clientVersion = $response->getValue()->getValue();
        $schemaVersion = TdSchemaRegistry::VERSION;

        if ($schemaVersion !== $clientVersion) {
            throw new TgClientException(sprintf('Client TdLib version "%s" doesnt match Schema version "%s"', $clientVersion, $schemaVersion));
        }
    }
}
