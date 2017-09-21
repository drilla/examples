<?php

/**
 * Все делаем просто, без всяких неймспейсов
 */
include "Progression.php";
/**
 */

try {
    if (isset($argv[1])) {
        $testingString = $argv[1];
    } else {
        throw new Exception('String argument required.');
    }

    if (!Progression::isStringFormatValid($testingString)) {
        throw new  Exception('Invalid string format.');
    }

    if (Progression::isProgression($testingString)) {
        echo "String '$testingString' is a progression!";
    } else {
        echo "String '$testingString' is NOT a progression!";
    }
} catch (Throwable $error) {
    echo $error->getMessage();
} finally {
    echo "\n";
    return 1;
}


