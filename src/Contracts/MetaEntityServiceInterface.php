<?php

namespace LinkORB\Bundle\WikiBundle\Contracts;

interface MetaEntityServiceInterface
{
    public function getUserRecentByBusinessKey(string $username, string $class, int $maybe_limit);
    public function getFavoriteByBusinessKey(string $username, string $class, int $maybe_limit);
    public function toggleFavorite(string $username, string $key);
    public function ensureMetaUserEntity(string $username, string $key);
}
