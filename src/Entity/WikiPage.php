<?php

namespace LinkORB\Bundle\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;

#[ORM\Table('linkorb_wiki_wiki_page')]
#[ORM\Entity(repositoryClass: WikiPageRepository::class)]
class WikiPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wiki::class, inversedBy: 'wikiPages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Wiki $wiki = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $data = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $parent_id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $context = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $aiGenerated = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $twigEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $generated = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $generatedAt = null;

    /* private variable */
    private int $points;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWiki(): ?Wiki
    {
        return $this->wiki;
    }

    public function setWiki(?Wiki $wiki): self
    {
        $this->wiki = $wiki;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parent_id;
    }

    public function setParentId(?int $parent_id): self
    {
        $this->parent_id = $parent_id;

        return $this;
    }

    private $childPages = [];

    /**
     * Get the value of childPages.
     */
    public function getChildPages(): array
    {
        return $this->childPages;
    }

    /**
     * Set the value of childPages.
     */
    public function setChildPages(array $childPages): self
    {
        $this->childPages = $childPages;

        return $this;
    }

    /**
     * Get the value of points.
     */
    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->aiGenerated;
    }

    public function setAiGenerated(bool $aiGenerated): self
    {
        $this->aiGenerated = $aiGenerated;

        return $this;
    }

    public function isTwigEnabled(): bool
    {
        return $this->twigEnabled;
    }

    public function setTwigEnabled(bool $twigEnabled): self
    {
        $this->twigEnabled = $twigEnabled;

        return $this;
    }

    public function getGenerated(): ?string
    {
        return $this->generated;
    }

    public function setGenerated(?string $generated): self
    {
        $this->generated = $generated;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGeneratedArray(): array
    {
        if (!$generated = $this->getGenerated()) {
            return [];
        }
        $data = json_decode($generated, true);
        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function getGeneratedAt(): ?int
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?int $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }
}
