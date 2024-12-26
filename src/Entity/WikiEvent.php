<?php

namespace LinkORB\Bundle\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository")]
class WikiEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 32)]
    private $type;

    #[ORM\Column(type: 'integer')]
    private $created_at;

    #[ORM\Column(type: 'string', length: 64)]
    private $created_by;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $wiki_page_id;

    #[ORM\Column(type: 'text', nullable: true)]
    private $data;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $wiki_id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): ?int
    {
        return $this->created_at;
    }

    public function setCreatedAt(int $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    public function setCreatedBy(string $created_by): self
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getWikiPageId(): ?int
    {
        return $this->wiki_page_id;
    }

    public function setWikiPageId(?int $wiki_page_id): self
    {
        $this->wiki_page_id = $wiki_page_id;

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

    public function getWikiId(): ?int
    {
        return $this->wiki_id;
    }

    public function setWikiId(?int $wiki_id): self
    {
        $this->wiki_id = $wiki_id;

        return $this;
    }

    public function getDataArray(): ?array
    {
        return json_decode($this->data, true);
    }
}
