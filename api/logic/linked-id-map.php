<?php

function buildLinkedIdMapUnified(array $rows): array
{
    $map = [];

    // 1. Через ATTENDEDTRANSFER / BLINDTRANSFER / PARK_END
    foreach ($rows as $row) {
        if (in_array($row['eventtype'], ['ATTENDEDTRANSFER', 'BLINDTRANSFER', 'PARK_END'])) {
            $child = $row['peer'] ?? '';
            $parent = $row['linkedid'] ?? '';
            if ($child && $parent && $child !== $parent) {
                $map[$child] = $parent;
            }
        }
    }

    // 2. Через связи peer → channame (fallback)
    $chanLinkedMap = [];
    $peerToChan = [];

    foreach ($rows as $row) {
        $chan = $row['channame'] ?? '';
        $peer = $row['peer'] ?? '';
        $linkedid = $row['linkedid'] ?? '';

        if ($chan && $linkedid) {
            $chanLinkedMap[$chan] = $linkedid;
        }

        if ($peer && $chan) {
            $peerToChan[$peer][] = $chan;
        }
    }

    foreach ($peerToChan as $peer => $chans) {
        if (!isset($chanLinkedMap[$peer])) continue;
        $parent = $chanLinkedMap[$peer];

        foreach ($chans as $chan) {
            if (!isset($chanLinkedMap[$chan])) continue;
            $child = $chanLinkedMap[$chan];
            if ($child !== $parent && !isset($map[$child])) {
                $map[$child] = $parent;
            }
        }
    }

    return $map;
}

/**
 * Возвращает "нормализованный" linkedid (корень цепочки)
 *
 * @param array $linkedIdMap
 * @param string $linkedid
 * @return string
 */
function normalize_linkedId(array $linkedIdMap, string $linkedid): string
{
    $visited = [];
    while (isset($linkedIdMap[$linkedid])) {
        if (in_array($linkedid, $visited)) {
            // Предотвращаем бесконечный цикл
            break;
        }
        $visited[] = $linkedid;
        $linkedid = $linkedIdMap[$linkedid];
    }

    return $linkedid;
}

