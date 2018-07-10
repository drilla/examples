<?php


class ArrayHelperTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @dataProvider possibleCombinationsProvider
     */
    public function testPossibleCombinations($arrays, $expectedResult)
    {
        $actual = \common\helpers\ArrayHelper::getPossibleCombinations($arrays);

        $this->assertEquals($expectedResult, $actual);
    }

    public function possibleCombinationsProvider() {
        return [
            [
                [
                    ['a', 'b'],
                    ['c', 'd'],
                ],
                //expected
                [
                    ['a', 'c'],
                    ['a', 'd'],
                    ['b', 'c'],
                    ['b', 'd'],
                ]
            ],
            [
                [
                    ['a', 'b'],
                    [],
                    ['c', 'd'],
                ],
                //expected
                [
                    ['a', 'c'],
                    ['a', 'd'],
                    ['b', 'c'],
                    ['b', 'd'],
                ]
            ],
            [
                [
                    [],
                    ['a', 'b'],
                    ['c', 'd'],
                ],
                //expected
                [
                    ['a', 'c'],
                    ['a', 'd'],
                    ['b', 'c'],
                    ['b', 'd'],
                ]
            ],
            [
                [
                    [],
                    ['a', 'b'],
                    ['c', 'd'],
                    ['x'],
                ],
                //expected
                [
                    ['a', 'c', 'x'],
                    ['a', 'd', 'x'],
                    ['b', 'c', 'x'],
                    ['b', 'd', 'x'],
                ]
            ],
            [
                [
                    [],
                    ['a', 'b'],
                    ['c', 'd'],
                    ['e', 'f'],
                ],
                //expected
                [
                    ['a', 'c', 'e'],
                    ['a', 'c', 'f'],
                    ['a', 'd', 'e'],
                    ['a', 'd', 'f'],
                    ['b', 'c', 'e'],
                    ['b', 'c', 'f'],
                    ['b', 'd', 'e'],
                    ['b', 'd', 'f'],
                ]
            ],
        ];
    }
}
