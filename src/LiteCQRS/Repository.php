<?php

namespace LiteCQRS;

use Rhumsaa\Uuid\Uuid;

interface Repository
{
    /**
     * @param string $className
     * @param int $expectedVersion
     *
     * @return AggregateRoot
     */
    public function find($className, Uuid $uuid, $expectedVersion = null);

    /**
     * @return void
     */
    public function save(AggregateRoot $object);
}
