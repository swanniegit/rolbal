<?php
/**
 * Challenge Progress - Progress calculation and score breakdown
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Challenge.php';

class ChallengeProgress {

    public static function getProgress(int $attemptId, array $attempt, array $sequences, array $rolls): array {
        $rollCount = count($rolls);

        // Calculate which sequence and bowl we're on
        $currentSequenceIndex = 0;
        $currentBowlInSequence = 0;
        $bowlsProcessed = 0;

        foreach ($sequences as $index => $seq) {
            $seqBowls = (int) $seq['bowl_count'];
            if ($bowlsProcessed + $seqBowls > $rollCount) {
                $currentSequenceIndex = $index;
                $currentBowlInSequence = $rollCount - $bowlsProcessed;
                break;
            }
            $bowlsProcessed += $seqBowls;
            if ($bowlsProcessed >= $rollCount && $index === count($sequences) - 1) {
                $currentSequenceIndex = $index;
                $currentBowlInSequence = $seqBowls;
            }
        }

        // Calculate total bowls
        $totalBowls = 0;
        foreach ($sequences as $seq) {
            $totalBowls += (int) $seq['bowl_count'];
        }

        $currentSequence = $sequences[$currentSequenceIndex] ?? null;
        $isComplete = $rollCount >= $totalBowls;

        return [
            'attempt_id' => $attemptId,
            'total_score' => (int) $attempt['total_score'],
            'max_possible_score' => (int) $attempt['max_possible_score'],
            'roll_count' => $rollCount,
            'total_bowls' => $totalBowls,
            'current_sequence_index' => $currentSequenceIndex,
            'current_sequence_number' => $currentSequenceIndex + 1,
            'total_sequences' => count($sequences),
            'current_bowl_in_sequence' => $currentBowlInSequence + 1,
            'current_sequence' => $currentSequence,
            'is_complete' => $isComplete,
            'completed_at' => $attempt['completed_at'],
            'percent_complete' => $totalBowls > 0 ? round(($rollCount / $totalBowls) * 100, 1) : 0
        ];
    }

    public static function getScoreBreakdown(array $sequences, array $rolls): array {
        $breakdown = [];
        $rollIndex = 0;

        foreach ($sequences as $seq) {
            $seqBowls = (int) $seq['bowl_count'];
            $seqScore = 0;
            $seqMaxScore = $seqBowls * Challenge::getMaxScorePerBowl();
            $seqRolls = [];

            for ($i = 0; $i < $seqBowls && $rollIndex < count($rolls); $i++, $rollIndex++) {
                $roll = $rolls[$rollIndex];
                $rollScore = Challenge::calculateScore($roll['result'], $roll['toucher']);
                $seqScore += $rollScore;
                $seqRolls[] = [
                    'result' => $roll['result'],
                    'toucher' => $roll['toucher'],
                    'score' => $rollScore
                ];
            }

            $breakdown[] = [
                'sequence_order' => $seq['sequence_order'],
                'description' => $seq['description'],
                'end_length' => $seq['end_length'],
                'delivery' => $seq['delivery'],
                'bowl_count' => $seqBowls,
                'rolls' => $seqRolls,
                'score' => $seqScore,
                'max_score' => $seqMaxScore,
                'percentage' => $seqMaxScore > 0 ? round(($seqScore / $seqMaxScore) * 100, 1) : 0
            ];
        }

        return $breakdown;
    }
}
