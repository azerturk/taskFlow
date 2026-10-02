<?php

namespace Modules\Tasks\Support;

final class TaskRankSequence
{
    private const STEP = 1000;

    /** @param iterable<int> $ranks */
    public static function next(iterable $ranks): int
    {
        $maximum = 0;

        foreach ($ranks as $rank) {
            $maximum = max($maximum, $rank);
        }

        return $maximum + self::STEP;
    }

    /**
     * @param  list<int>  $orderedIds
     * @param  list<int>  $reservedRanks
     * @return array<int, int>
     */
    public static function rebalance(array $orderedIds, array $reservedRanks = []): array
    {
        $reserved = array_fill_keys($reservedRanks, true);
        $ranks = [];
        $next = self::STEP;

        foreach ($orderedIds as $id) {
            while (isset($reserved[$next])) {
                $next += self::STEP;
            }

            $ranks[$id] = $next;
            $next += self::STEP;
        }

        return $ranks;
    }
}
