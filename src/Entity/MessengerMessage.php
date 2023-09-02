<?php

namespace SoureCode\Bundle\Worker\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonException;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[ORM\Entity]
#[ORM\Table(name: 'messenger_messages')] // @todo does not work if changed in config
#[ORM\Index(columns: ['queue_name'])]
#[ORM\Index(columns: ['available_at'])]
#[ORM\Index(columns: ['delivered_at'])]
class MessengerMessage
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, length: 20)]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::TEXT)]
    private string $headers;

    #[ORM\Column(type: Types::STRING, length: 190)]
    private string $queueName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeInterface $availableAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeInterface $deliveredAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): MessengerMessage
    {
        $this->body = $body;
        return $this;
    }

    public function getDecodedEnvelope(SerializerInterface|WorkerManager $serializer): Envelope
    {
        if ($serializer instanceof WorkerManager) {
            return $serializer->decodeMessage($this->getEncodedEnvelope());
        }

        return $serializer->decode($this->getEncodedEnvelope());
    }

    public function getEncodedEnvelope(): array
    {
        return [
            'body' => $this->body,
            'headers' => $this->getDecodedHeaders(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function getDecodedHeaders(): array
    {
        return json_decode($this->headers, true, 512, JSON_THROW_ON_ERROR);
    }

    public function getHeaders(): string
    {
        return $this->headers;
    }

    public function setHeaders(string $headers): MessengerMessage
    {
        $this->headers = $headers;
        return $this;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function setQueueName(string $queueName): MessengerMessage
    {
        $this->queueName = $queueName;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): MessengerMessage
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAvailableAt(): DateTimeInterface
    {
        return $this->availableAt;
    }

    public function setAvailableAt(DateTimeInterface $availableAt): MessengerMessage
    {
        $this->availableAt = $availableAt;
        return $this;
    }

    public function getDeliveredAt(): ?DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?DateTimeInterface $deliveredAt): MessengerMessage
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }
}