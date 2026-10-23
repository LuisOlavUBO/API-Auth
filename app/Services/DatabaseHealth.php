<?php

namespace App\Services;

use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

/**
 * Checks that the database answers a trivial query.
 */
class DatabaseHealth
{
    public function __construct(protected ConnectionResolverInterface $db) {}

    public function isAvailable(): bool
    {
        try {
            $this->db->connection()->select('SELECT 1 AS ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
