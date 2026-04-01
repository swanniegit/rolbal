<?php
/**
 * Simple Math Captcha Helper
 */

require_once __DIR__ . '/Auth.php';

class Captcha {

    public static function generate(): array {
        Auth::init();

        $num1 = rand(1, 10);
        $num2 = rand(1, 10);

        $operators = ['+', '-'];
        $operator = $operators[array_rand($operators)];

        if ($operator === '-' && $num2 > $num1) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }

        $answer = $operator === '+' ? $num1 + $num2 : $num1 - $num2;

        $_SESSION['captcha_answer'] = $answer;

        return [
            'question' => "$num1 $operator $num2 = ?",
            'answer' => $answer
        ];
    }

    public static function validate($answer): bool {
        Auth::init();

        if (!isset($_SESSION['captcha_answer'])) {
            return false;
        }

        $expected = $_SESSION['captcha_answer'];
        unset($_SESSION['captcha_answer']);

        return (int) $answer === (int) $expected;
    }

    public static function getQuestion(): string {
        $captcha = self::generate();
        return $captcha['question'];
    }
}
