<?php

namespace LinkORB\Bundle\WikiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\Yaml\Yaml;

#[ORM\Table('linkorb_wiki_wiki')]
#[ORM\Entity(repositoryClass: WikiRepository::class)]
class Wiki
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 64)]
    private $name;

    #[ORM\Column(type: 'string', length: 255)]
    private $description;

    #[ORM\OneToMany(targetEntity: WikiPage::class, mappedBy: 'wiki')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private $wikiPages;

    /**
     * @deprecated
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $read_role;

    /**
     * @deprecated
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $write_role;


    #[ORM\Column(type: 'text', length: 255, nullable: true)]
    private string|null $access_control_expression = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private $config;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $lastPullAt;

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

    /**
     * @param string|null $read_role
     * @return $this
     * @deprecated
     */
    public function setReadRole(?string $read_role): self
    {
        $this->read_role = $read_role;

        return $this;
    }

    /**
     * @return string|null
     * @deprecated
     */
    public function getReadRole(): ?string
    {
        return $this->read_role;
    }

    /**
     * @param string|null $write_role
     * @return $this
     * @deprecated
     */
    public function setWriteRole(?string $write_role): self
    {
        $this->write_role = $write_role;

        return $this;
    }

    /**
     * @return string|null
     * @deprecated
     */
    public function getWriteRole(): ?string
    {
        return $this->write_role;
    }

    public function setAccessControlExpression(?string $access_control_expression): self
    {
        $this->access_control_expression = $access_control_expression;
        return $this;
    }

    public function getAccessControlExpression(): ?string
    {
        return $this->access_control_expression;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfigArray()
    {
        return Yaml::parse($this->config ?? '') ?? [];
    }

    public function getLastPullAt(): ?int
    {
        return $this->lastPullAt;
    }

    public function setLastPullAt(?int $lastPullAt): self
    {
        $this->lastPullAt = $lastPullAt;

        return $this;
    }

    public function isReadOnly(): bool
    {
        return (bool) !empty($this->getConfigArray()['read-only']) ? $this->getConfigArray()['read-only'] : false;
    }
}
