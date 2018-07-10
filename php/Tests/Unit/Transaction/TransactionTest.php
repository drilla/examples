<?php

use common\models\ar;
use common\models\domain;

class TransactionTest extends \Codeception\Test\Unit
{
    /** @var \UnitTester */
    protected $tester;

    protected function _before() {}

    protected function _after() {}

    /**
     * @dataProvider transactionDataProvider
     */
    public function testCreateObject($amount, $type, string $conversionId = null, string $expectedException = null)
    {
        /** @var domain\Balance $balance */
        $balance = $this->getMockBuilder(domain\Balance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $operatorMock       = $this->getMockBuilder(ar\User::class)->disableOriginalConstructor()->getMock();
        $operatorMock->id   = 1;
        $operatorMock->role = ar\User::ROLE_ADMIN;

        if ($expectedException) $this->expectException($expectedException);

        $transaction = new domain\Transaction($balance, $type, $amount, $operatorMock, null, $conversionId);

        $this->assertTrue($transaction instanceof domain\Transaction);

        $this->assertSame($operatorMock, $transaction->getOperator());
        $this->assertSame($balance, $transaction->getBalance());
        $this->assertSame($type, $transaction->getType());
        $this->assertEquals($amount, $transaction->getAmount());
    }

    public function transactionDataProvider() : array {
        return [
            //могут быть отрицательными
            [-100, domain\Transaction::TYPE_WITHDRAW],
            [-100, domain\Transaction::TYPE_MANUAL_CORRECTION],
            [-100, domain\Transaction::TYPE_CONVERSION_REVENUE, 1],

            //не могут быть отрицательными
            [-100, domain\Transaction::TYPE_CONVERSION_PAYOUT, 1, DomainException::class],
            [-100, domain\Transaction::TYPE_BONUS, null, DomainException::class],
            [-100, domain\Transaction::TYPE_DEPOSIT, null, DomainException::class],

            //не может быть положительным
            [-100, domain\Transaction::TYPE_CONVERSION_REVENUE, 1],

            [100, 'invalidtype', null, InvalidArgumentException::class],

            //должен быть conversion_id
            [100, domain\Transaction::TYPE_CONVERSION_PAYOUT, null, DomainException::class],
            [100, domain\Transaction::TYPE_CONVERSION_PAYOUT, 1],
            [-100, domain\Transaction::TYPE_CONVERSION_REVENUE, null, DomainException::class],
            [-100, domain\Transaction::TYPE_CONVERSION_REVENUE, 1],

            //не должно быть conversion_id
            [100, domain\Transaction::TYPE_WITHDRAW, 1, DomainException::class],
            [100, domain\Transaction::TYPE_MANUAL_CORRECTION, 1, DomainException::class],
            [100, domain\Transaction::TYPE_BONUS, 1, DomainException::class],
            [100, domain\Transaction::TYPE_DEPOSIT, 1, DomainException::class],

            //нулевую нельзя создать
            [0, domain\Transaction::TYPE_DEPOSIT, 1, DomainException::class],

        ];
    }


    public function testSetId() {
        $balance     = $this->getMockBuilder(domain\Balance::class)->disableOriginalConstructor()->getMock();
        $operator    = $this->getMockBuilder(ar\User::class)->disableOriginalConstructor()->getMock();
        $type        = domain\Transaction::TYPE_DEPOSIT;
        $amount      = 100;
        $transaction = new domain\Transaction($balance, $type, $amount, $operator);

        //можно установить любой если его не было
        $transaction->setId(1); //ok

        //можно установить такой же
        $transaction->setId(1); //ok

        $this->assertEquals(1, $transaction->getId());

        //другой нельзя установить
        $this->expectException(\DomainException::class);
        $transaction->setId(2);
    }
}
