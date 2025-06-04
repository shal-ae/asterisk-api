<?php

function keepOneAnswered(array &$groupedArray)
{
    // Строим массив отвеченных uniqueid
    $answered = [];
    foreach ($groupedArray as &$group) {
        foreach ($group['items'] as $item) {
            $uid = $item['uniqueid'];
            if ((($item['disposition'] ?? '') === 'ANSWERED') && !isset($answered[$uid])) {
                $answered[$uid] = true;
            }
        }
    }
    unset($group); // good practice with references

    foreach ($groupedArray as &$group) {
        $filteredItems = [];
        foreach ($group['items'] as $item) {
            $uid = $item['uniqueid'];

            if (isset($answered[$uid]) && (($item['disposition'] ?? '') !== 'ANSWERED')) {
                continue;
            } else {
                $filteredItems[] = $item;
            }
        }
        $group['items'] = $filteredItems;
    }
    unset($group); // good practice with references

}
