<?php
/**
 * Roll Validator - Centralized validation for roll/bowl data
 */

class RollValidator {

    // Valid end length codes (from constants.php)
    const VALID_END_LENGTHS = [9, 10, 11]; // 9=Long, 10=Middle, 11=Short

    // Valid delivery codes
    const VALID_DELIVERIES = [13, 14]; // 13=Backhand, 14=Forehand

    // Valid result position codes
    const VALID_RESULTS = [
        1, 2, 3, 4, 5, 6, 7, 8, 12,  // Grid positions (1-8, 12=Centre)
        20, 21, 22, 23,              // Miss positions
        30, 31, 32, 33,              // Trail & Rest drill: trail, resting touch, within mat width, none
    ];

    // Valid scoring teams for matches
    const VALID_TEAMS = [1, 2];

    // Valid shots per end
    const MIN_SHOTS = 1;
    const MAX_SHOTS = 8;

    public static function isValidEndLength(int $endLength): bool {
        return in_array($endLength, self::VALID_END_LENGTHS);
    }

    public static function isValidDelivery(int $delivery): bool {
        return in_array($delivery, self::VALID_DELIVERIES);
    }

    public static function isValidResult(int $result): bool {
        return in_array($result, self::VALID_RESULTS);
    }

    public static function isValidTeam(int $team): bool {
        return in_array($team, self::VALID_TEAMS);
    }

    public static function isValidShots(int $shots): bool {
        return $shots >= self::MIN_SHOTS && $shots <= self::MAX_SHOTS;
    }

    /**
     * Validate end length or throw exception
     */
    public static function validateEndLength(int $endLength): void {
        if (!self::isValidEndLength($endLength)) {
            throw new InvalidArgumentException('Invalid end length');
        }
    }

    /**
     * Validate delivery or throw exception
     */
    public static function validateDelivery(int $delivery): void {
        if (!self::isValidDelivery($delivery)) {
            throw new InvalidArgumentException('Invalid delivery');
        }
    }

    /**
     * Validate result or throw exception
     */
    public static function validateResult(int $result): void {
        if (!self::isValidResult($result)) {
            throw new InvalidArgumentException('Invalid result position');
        }
    }

    /**
     * Validate scoring team or throw exception
     */
    public static function validateTeam(int $team): void {
        if (!self::isValidTeam($team)) {
            throw new InvalidArgumentException('Invalid scoring team');
        }
    }

    /**
     * Validate shots count or throw exception
     */
    public static function validateShots(int $shots): void {
        if (!self::isValidShots($shots)) {
            throw new InvalidArgumentException('Invalid shots count');
        }
    }
}
