<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * {@see $recipient} and {@see $sender} are typed against {@see NotificationRecipientInterface},
 * not a concrete class — the host app's real recipient entity is wired in via Doctrine's
 * `resolve_target_entities`. This keeps the bundle usable by any host app regardless of how it
 * models recipients, while still getting a real foreign key (JOINs, an indexed column, cascade
 * delete on the recipient side).
 */
#[ORM\Index(fields: ['title'])]
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'ea_extensions_notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'notifications')]
    private ?NotificationTreatment $treatment = null;

    #[ORM\ManyToOne(targetEntity: NotificationRecipientInterface::class)]
    #[ORM\JoinColumn(name: 'recipient_id', nullable: false, onDelete: 'CASCADE')]
    private NotificationRecipientInterface $recipient;

    #[ORM\ManyToOne(targetEntity: NotificationRecipientInterface::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: true)]
    private ?NotificationRecipientInterface $sender = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contentKey = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contentParams = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $component = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $componentParams = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buttonLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buttonUrl = null;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $read = false;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    private bool $displayInNewCard = false;

    /**
     * Transient: read only by {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager::send()}
     * to decide whether every recipient shares the same {@see NotificationTreatment} row, or
     * each gets its own clone.
     */
    private bool $treatmentShared = false;

    public function __construct(string $type, string $title)
    {
        $this->type = $type;
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTreatment(): ?NotificationTreatment
    {
        return $this->treatment;
    }

    public function setTreatment(?NotificationTreatment $treatment): self
    {
        $this->treatment = $treatment;

        return $this;
    }

    public function addTreatment(): self
    {
        $this->treatment = new NotificationTreatment();

        return $this;
    }

    public function getRecipient(): NotificationRecipientInterface
    {
        return $this->recipient;
    }

    public function setRecipient(NotificationRecipientInterface $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getSender(): ?NotificationRecipientInterface
    {
        return $this->sender;
    }

    public function setSender(?NotificationRecipientInterface $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContentKey(): ?string
    {
        return $this->contentKey;
    }

    public function setContentKey(?string $contentKey): self
    {
        $this->contentKey = $contentKey;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentParams(): ?array
    {
        return $this->contentParams;
    }

    /**
     * @param array<string, mixed>|null $contentParams
     */
    public function setContentParams(?array $contentParams): self
    {
        $this->contentParams = $contentParams;

        return $this;
    }

    public function getComponent(): ?string
    {
        return $this->component;
    }

    public function setComponent(?string $component): self
    {
        $this->component = $component;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getComponentParams(): ?array
    {
        return $this->componentParams;
    }

    /**
     * @param array<string, mixed>|null $componentParams
     */
    public function setComponentParams(?array $componentParams): self
    {
        $this->componentParams = $componentParams;

        return $this;
    }

    public function getButtonLabel(): ?string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(?string $buttonLabel): self
    {
        $this->buttonLabel = $buttonLabel;

        return $this;
    }

    public function getButtonUrl(): ?string
    {
        return $this->buttonUrl;
    }

    public function setButtonUrl(?string $buttonUrl): self
    {
        $this->buttonUrl = $buttonUrl;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function setRead(bool $read): self
    {
        $this->read = $read;

        return $this;
    }

    public function isTreatmentShared(): bool
    {
        return $this->treatmentShared;
    }

    public function shareTreatment(): self
    {
        $this->treatmentShared = true;

        return $this;
    }

    public function isDisplayInNewCard(): bool
    {
        return $this->displayInNewCard;
    }

    public function setDisplayInNewCard(bool $displayInNewCard): self
    {
        $this->displayInNewCard = $displayInNewCard;

        return $this;
    }
}
