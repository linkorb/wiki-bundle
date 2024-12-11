<?php

namespace LinkORB\Bundle\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository")
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
     * @ORM\ManyToOne(targetEntity="LinkORB\Bundle\WikiBundle\Entity\Wiki", inversedBy="wikiPages")
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

    /**
     * @ORM\Column(type="integer", options={"default" : 0 }, nullable=true)
     */
    private $parent_id;

    /* private variable */
    private $points;

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
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * Set the value of points.
     *
     * @return self
     */
    public function setPoints($points)
    {
        $this->points = $points;

        return $this;
    }
}
