<?php
/**
 * Rolbal Constants - Field Definitions
 */

// 15 - Hand
const HANDS = [
    'L' => 'Left',
    'R' => 'Right'
];

// 13/14 - Delivery
const DELIVERIES = [
    13 => 'Backhand',
    14 => 'Forehand'
];

// 9/10/11 - End Length
const END_LENGTHS = [
    9  => 'Long End',
    10 => 'Middle End',
    11 => 'Short End'
];

// 1-8, 12 - Result Position (within 2 mat lengths)
const RESULTS = [
    1  => 'Short Left',
    2  => 'Short Right',
    3  => 'Level Left',
    4  => 'Level Right',
    5  => 'Long Left',
    6  => 'Long Right',
    7  => 'Long Centre',
    8  => 'Centre',
    12 => 'Short Centre'
];

// 20-23 - Miss Positions (more than 2 mat lengths from jack)
const MISSES = [
    20 => 'Too Far Left',
    21 => 'Too Far Right',
    22 => 'Too Long/Ditch',
    23 => 'Too Short'
];
