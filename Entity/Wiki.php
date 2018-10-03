<?php

namespace App\WikiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\WikiBundle\Repository\WikiRepository")
 */
class Wiki
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="App\WikiBundle\Entity\WikiPage", mappedBy="wiki")
     */
    private $wikiPages;

    public function __construct()
    {
        $this->wikiPages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|WikiPage[]
     */
    public function getWikiPages(): Collection
    {
        return $this->wikiPages;
    }

    public function addWikiPage(WikiPage $wikiPage): self
    {
        if (!$this->wikiPages->contains($wikiPage)) {
            $this->wikiPages[] = $wikiPage;
            $wikiPage->setWiki($this);
        }

        return $this;
    }

    public function removeWikiPage(WikiPage $wikiPage): self
    {
        if ($this->wikiPages->contains($wikiPage)) {
            $this->wikiPages->removeElement($wikiPage);
            // set the owning side to null (unless already changed)
            if ($wikiPage->getWiki() === $this) {
                $wikiPage->setWiki(null);
            }
        }

        return $this;
    }
}
