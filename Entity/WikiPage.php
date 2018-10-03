<?php

namespace App\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\WikiBundle\Repository\WikiPageRepository")
 */
class WikiPage
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\WikiBundle\Entity\Wiki", inversedBy="wikiPages")
     * @ORM\JoinColumn(nullable=false)
     */
    private $wiki;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $data;

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
}
