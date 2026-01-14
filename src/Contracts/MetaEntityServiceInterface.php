<?php

namespace LinkORB\Bundle\WikiBundle\Contracts;

use LinkORB\Bundle\MetaEntityBundle\Entity\MetaUserEntity;

interface MetaEntityServiceInterface
{
    /**
     * @return MetaUserEntity[]
     */
    public function getUserRecentByBusinessKey(string $username, string $class, int $maybe_limit): array;

    /**
     * @return MetaUserEntity[]
     */
    public function getFavoriteByBusinessKey(string $username, string $class, int $maybe_limit): array;

    public function toggleFavorite(string $username, string $businessKey): void;

    public function ensureMetaUserEntity(string $username, string $businessKey): MetaUserEntity;
}
