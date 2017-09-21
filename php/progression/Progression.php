<?php


abstract class Progression {
    public static function isStringFormatValid(string $string) : bool {
        return  preg_match('/^(|[a-zA-Z]{1},)*[a-zA-Z]{1}$/', $string);
    }

    public static function isProgression(string $string) : bool {

        //parse string to array of chars
        $chars = explode(',', $string);

        //walk through array, determine progression or not!

        $prevChar = null;
        foreach ($chars as $char) {

            //first iteration - only remember previous character
            if (!$prevChar) {
                $prevChar = $char;
                continue;
            }

            if (!self::_isCharsOrderAsc($prevChar, $char)) {
                //later iteration is not necessary
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $one
     * @param string $two
     * @return bool character $two place after $one in alphabet
     * @throws Exception
     */
    private function _isCharsOrderAsc(string $one, string $two) : bool {
        if (strlen($one) !== 1 || strlen($two) !== 1) throw new Exception('Internal script exception');

        //in ASCII the letters are sorted by alphabetic order
        $orderOfOne = ord(strtoupper($one));
        $orderOfTwo = ord(strtoupper($two));

        return $orderOfOne < $orderOfTwo;
    }
}
