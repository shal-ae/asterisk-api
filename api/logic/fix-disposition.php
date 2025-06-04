<?php

// === Коррекция disposition и billsec на основе CEL ===
function fixDispositionFromCEL(array &$groupedArray, array $celRows)
{
    $celByUniqueid = [];
    foreach ($celRows as $row) {
        $uid = $row['uniqueid'];
        if (!isset($celByUniqueid[$uid])) {
            $celByUniqueid[$uid] = [];
        }
        $celByUniqueid[$uid][] = $row;
    }

    $minBillDuration = 1;

    foreach ($groupedArray as &$group) {
        $marked = false;
        foreach ($group['items'] as &$item) {
            $uid = $item['uniqueid'];
            $events = $celByUniqueid[$uid] ?? [];

            $hasAnswer = false;
            $bridgeEnter = null;
            $bridgeExit = null;
            $bridgePeer = null;

            foreach ($events as $ev) {
                if ($ev['eventtype'] === 'ANSWER') {
                    $hasAnswer = true;
                }
                if ($ev['eventtype'] === 'BRIDGE_ENTER') {
                    $bridgeEnter = strtotime($ev['eventtime']);
                    $bridgePeer = $ev['peer']; // ← это ключ
                }
                if ($ev['eventtype'] === 'BRIDGE_EXIT') {
                    $bridgeExit = strtotime($ev['eventtime']);
                }
            }

            $billsec = ($bridgeEnter && $bridgeExit && $bridgeExit > $bridgeEnter) ? ($bridgeExit - $bridgeEnter) : 0;

            // Проверяем: этот item соответствует участнику моста?
            $isMatchedChannel = $bridgePeer &&
                (
                    strpos($item['channel'], $bridgePeer) !== false ||
                    strpos($item['dstchannel'], $bridgePeer) !== false
                );

            if (!$marked && $hasAnswer && $isMatchedChannel && $billsec >= $minBillDuration) {
                $item['disposition'] = 'ANSWERED';
                $item['billsec'] = $billsec;
                $item['real_answered'] = true;
                $marked = true; // чтобы другие записи не получили ANSWERED
            } else {
                $item['disposition'] = 'NO ANSWER';
                $item['billsec'] = 0;
                $item['real_answered'] = false;
            }
        }
        unset($item);
    }
}

function fixDispositionFromCEL11(array &$groupedArray, array $celRows)
{
    $celByUniqueid = [];
    foreach ($celRows as $row) {
        $uid = $row['uniqueid'];
        if (!isset($celByUniqueid[$uid])) {
            $celByUniqueid[$uid] = [];
        }
        $celByUniqueid[$uid][] = $row;
    }

    $minBillDuration = 1;
    $processedUids = []; // чтобы не повторять обработку UID

    foreach ($groupedArray as &$group) {
        foreach ($group['items'] as &$item) {
            $uid = $item['uniqueid'];

            // Если уже обработан этот UID — не даём второй раз ANSWERED
            if (isset($processedUids[$uid])) {
                $item['real_answered'] = false;
                $item['disposition'] = 'NO ANSWER';
                $item['billsec'] = 0;
                continue;
            }

            $events = $celByUniqueid[$uid] ?? [];

            $hasAnswer = false;
            $bridgeEnter = null;
            $bridgeExit = null;

            foreach ($events as $ev) {
                switch ($ev['eventtype']) {
                    case 'ANSWER':
                        $hasAnswer = true;
                        break;
                    case 'BRIDGE_ENTER':
                        $bridgeEnter = strtotime($ev['eventtime']);
                        break;
                    case 'BRIDGE_EXIT':
                        $bridgeExit = strtotime($ev['eventtime']);
                        break;
                }
            }

            if ($hasAnswer && $bridgeEnter && $bridgeExit && ($bridgeExit - $bridgeEnter) >= $minBillDuration) {
                $realBillsec = $bridgeExit - $bridgeEnter;
                $item['disposition'] = 'ANSWERED';
                $item['billsec'] = $realBillsec;
                $item['real_answered'] = true;

                // помечаем UID как обработанный
                $processedUids[$uid] = true;
            } else {
                $item['real_answered'] = false;
                $item['disposition'] = 'NO ANSWER';
                $item['billsec'] = 0;
            }
        }
        unset($item);
    }
}

function fixDispositionFromCEL3(array &$groupedArray, array $celRows)
{
    $celByUniqueid = [];
    foreach ($celRows as $row) {
        $uid = $row['uniqueid'];
        if (!isset($celByUniqueid[$uid])) {
            $celByUniqueid[$uid] = [];
        }
        $celByUniqueid[$uid][] = $row;
    }

    foreach ($groupedArray as &$group) {
        foreach ($group['items'] as &$item) {
            $uid = $item['uniqueid'];
            $events = $celByUniqueid[$uid] ?? [];

            $hasAnswer = false;
            $bridgeEnter = null;
            $bridgeExit = null;

            foreach ($events as $ev) {
                switch ($ev['eventtype']) {
                    case 'ANSWER':
                        $hasAnswer = true;
                        break;
                    case 'BRIDGE_ENTER':
                        $bridgeEnter = strtotime($ev['eventtime']);
                        break;
                    case 'BRIDGE_EXIT':
                        $bridgeExit = strtotime($ev['eventtime']);
                        break;
                }
            }

            // Корректный звонок: ANSWER был, BRIDGE_ENTER и BRIDGE_EXIT заданы и последовательны
            if ($hasAnswer && $bridgeEnter && $bridgeExit && $bridgeExit > $bridgeEnter) {
                $realBillsec = $bridgeExit - $bridgeEnter;
                $item['disposition'] = 'ANSWERED';
                $item['billsec'] = $realBillsec;
                $item['real_answered'] = true;
            } else {
                // Даже если CDR говорит "ANSWERED", но нет подтверждения из CEL — считаем неотвеченным
                $item['real_answered'] = false;
                $item['disposition'] = 'NO ANSWER';
                $item['billsec'] = 0;
            }

            if ($uid === '1749032306.891') { // замените на UID проблемной записи
                echo "UID: $uid\n";
                echo "hasAnswer: " . ($hasAnswer ? 'yes' : 'no') . "\n";
                echo "bridgeEnter: $bridgeEnter\n";
                echo "bridgeExit: $bridgeExit\n";
            }
        }
        unset($item);
    }
}

function fixDispositionFromCELOld(array &$groupedArray, array $celRows)
{
    $celByUniqueid = [];
    foreach ($celRows as $row) {
        $uid = $row['uniqueid'];
        if (!isset($celByUniqueid[$uid])) {
            $celByUniqueid[$uid] = [];
        }
        $celByUniqueid[$uid][] = $row;
    }

    foreach ($groupedArray as &$group) {
        foreach ($group['items'] as &$item) {
            $uid = $item['uniqueid'];
            $events = $celByUniqueid[$uid] ?? [];

            $start = null;
            $end = null;
            $hasRealAnswer = false;

            foreach ($events as $ev) {
                if ($ev['eventtype'] === 'ANSWER') {
                    $hasRealAnswer = true;
                }
                if ($ev['eventtype'] === 'BRIDGE_ENTER') {
                    $start = strtotime($ev['eventtime']);
                } elseif ($ev['eventtype'] === 'BRIDGE_EXIT') {
                    $end = strtotime($ev['eventtime']);
                }
            }

            $realBillsec = ($start && $end && $end > $start && $hasRealAnswer) ? ($end - $start) : 0;

            if ($realBillsec > 0) {
                $item['disposition'] = 'ANSWERED';
                $item['billsec'] = $realBillsec;
                $item['real_answered'] = true;
            } else {
                $item['real_answered'] = false;
            }
        }
        unset($item);
    }

}


