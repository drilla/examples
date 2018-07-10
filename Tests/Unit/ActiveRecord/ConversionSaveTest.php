<?php
namespace tests\unitCommon;

use common\models\ar;
use common\models\domain;
use tests\stub\common\behaviors\ConversionBehaviorStub;
use yii\db\ActiveQuery;

class ConversionSaveTest extends \Codeception\Test\Unit
{
    /** @var \UnitTester */
    protected $tester;

    protected function _before(){}

    protected function _after(){}

    /**
     * сохраняем тест и смотрим, что создаются все транзакции
     * и изменяются связанные данные.
     *
     * кейсы
     * 1. апрув
     * 2. отмена апрува
     * 3. реапрув
     *
     * @dataProvider saveProvider
     */
    public function testSave(
        array $oldConversionAttrs,
        array $newConversionAttrs,
        bool  $hasTransactionsFlagAfterSave,
        bool  $expectOriginPayoutTransaction,
        bool  $expectOriginRevenueTransaction,
        bool  $expectReversePayoutTransaction,
        bool  $expectReverseRevenueTransaction,
        bool  $wasLastNotRevertedPayoutTransaction,
        bool  $wasLastNotRevertedRevenueTransaction
    ) {

        $offerMock = $this
            ->getMockBuilder(ar\Offer::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock()
        ;

        /** @var ar\Offer $offerMock */
        $offerMock->advertiser_id = 2;

        $offerActiveQuery = $this->getMockBuilder(ActiveQuery::class)->disableOriginalConstructor()->getMock();
        $offerActiveQuery->method('findFor')->willReturn($offerMock);

        $conversion = $this->getMockBuilder(ar\Conversion::class)->setMethods(['getOffer'])->getMock();
        $conversion->method('getOffer')->willReturn($offerActiveQuery);

        /** @var ar\Conversion $conversion */
        //заменяем поведение на более удобное, чтобы не выполнять лишней работы
        $conversion->detachBehavior('conversion');
        $conversion->attachBehavior('conversion', ConversionBehaviorStub::class);

        $conversion->setOldAttributes($oldConversionAttrs);
        $conversion->setAttributes($newConversionAttrs, false);

        $conversion->offer_id = 1;

        $originPayoutTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_CONVERSION_PAYOUT,
            'amount'        => $conversion->getAttribute('payout'),
            'operator_id'   => ar\User::SYSTEM_ID,
        ];

        $originRevenueTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_CONVERSION_REVENUE,
            'amount'        => -1 * $conversion->getAttribute('revenue'),
            'operator_id'   => ar\User::SYSTEM_ID,
        ];

        $reversalPayoutTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_REVERSE,
            'amount'        => -1 * $conversion->getOldAttribute('payout'),
            'operator_id'   => ar\User::SYSTEM_ID,
            'reversal_id'    => null
        ];

        $reversalRevenueTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_REVERSE,
            'amount'        => $conversion->getOldAttribute('revenue'),
            'operator_id'   => ar\User::SYSTEM_ID,
            'reversal_id'    => null
        ];

        $lastNotRevertedPaymentTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_CONVERSION_PAYOUT,
            'amount'        => $conversion->getOldAttribute('payout'),
            'operator_id'   => ar\User::SYSTEM_ID,
            'balance_id'    => 1,
        ];

        $lastNotRevertedRevenueTransactionAttrs = [
            'conversion_id' => $conversion->_id,
            'type'          => domain\Transaction::TYPE_CONVERSION_REVENUE,
            'amount'        => -1 * $conversion->getOldAttribute('revenue'),
            'operator_id'   => ar\User::SYSTEM_ID,
            'balance_id'    => 2,
        ];

        //балансы в базе
        $this->tester->haveRecord(ar\BalanceTest::class, [
            'id'          => 1,
            'user_id'     => 1,
            'currency_id' => 1,
            'value'       => 100000,
        ]);

        $this->tester->haveRecord(ar\BalanceTest::class, [
            'id'          => 2,
            'user_id'     => 2,
            'currency_id' => 1,
            'value'       => 100000,
        ]);

        //транзакций еще нет в базе
        $this->tester->dontSeeRecord(ar\Transaction::class, $originPayoutTransactionAttrs);
        $this->tester->dontSeeRecord(ar\Transaction::class, $originRevenueTransactionAttrs);

        //откатов еще нет в базе
        $this->tester->dontSeeRecord(ar\Transaction::class, $reversalPayoutTransactionAttrs);
        $this->tester->dontSeeRecord(ar\Transaction::class, $reversalRevenueTransactionAttrs);

        //добавим "Последние неоткаченные транзакции" в базу
        if ($wasLastNotRevertedPayoutTransaction) {
            $this->tester->haveRecord(ar\Transaction::class, $lastNotRevertedPaymentTransactionAttrs);
        }

        if ($wasLastNotRevertedRevenueTransaction) {
            $this->tester->haveRecord(ar\Transaction::class, $lastNotRevertedRevenueTransactionAttrs);
        }

        //сохранение конверсии
        $conversion->save();

        //флаг конверсии
        $this->assertEquals($hasTransactionsFlagAfterSave, $conversion->has_balance_transactions);

        //транзакции уже есть в базе
        if ($expectOriginPayoutTransaction) {
            $this->tester->seeRecord(ar\Transaction::class, $originPayoutTransactionAttrs);
        } else {
            $this->tester->dontSeeRecord(ar\Transaction::class, $originPayoutTransactionAttrs);
        }

        if ($expectOriginRevenueTransaction) {
            $this->tester->seeRecord(ar\Transaction::class, $originRevenueTransactionAttrs);
        } else {
            $this->tester->dontSeeRecord(ar\Transaction::class, $originRevenueTransactionAttrs);
        }

        //откаты тоже в базе
        if ($expectReversePayoutTransaction) {
            $this->tester->seeRecord(ar\Transaction::class, $reversalPayoutTransactionAttrs);
        } else {
            $this->tester->dontSeeRecord(ar\Transaction::class, $reversalPayoutTransactionAttrs);
        }
        if ($expectReverseRevenueTransaction) {
            $this->tester->seeRecord(ar\Transaction::class, $reversalRevenueTransactionAttrs);
        } else {
            $this->tester->dontSeeRecord(ar\Transaction::class, $reversalRevenueTransactionAttrs);
        }
    }

    public function saveProvider() : array {
        $conversionId = '0001-88f393e5-5933e784-2ce3-2fd32b44';

        // назначается выплата в первый раз.
        $caseFirstTimeApprove = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_PENDING,
                'payout'       => null,
                'revenue'      => null,
                'has_balance_transactions' => null,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => null,
            ],
            'hasTransactionsFlagAfterSave'  => true,
            'expectOriginPayoutTransaction' => true,
            'expectOriginRevenueTransaction' => true,
            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,
            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,

        ];

        //первая выплата и она пустая
        $caseFirstTimeEmptyApprove = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_PENDING,
                'payout'       => null,
                'revenue'      => null,
                'has_balance_transactions' => null,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 0,
                'revenue'      => 0,
                'has_balance_transactions' => null,
            ],
            'hasTransactionsFlagAfterSave'  => false,
            'expectOriginPayoutTransaction' => false,
            'expectOriginRevenueTransaction' => false,
            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,
            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,

        ];

        // первое сохранение, апрува не было
        $caseFirstTimeNotApproved = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_PENDING,
                'payout'       => null,
                'revenue'      => null,
                'has_balance_transactions' => null,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_PENDING,
                'payout'       => 0,
                'revenue'      => 0,
                'has_balance_transactions' => null,
            ],
            'hasTransactionsFlagAfterSave'  => false,
            'expectOriginPayoutTransaction' => false,
            'expectOriginRevenueTransaction' => false,
            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,
            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,
        ];

        // Уже выплатили - потом отказ.
        $caseRejectAfterApprove = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_REJECTED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'hasTransactionsFlagAfterSave'  => true,
            'expectOriginPayoutTransaction' => true,
            'expectOriginRevenueTransaction' => true,
            'expectReversePayoutTransaction' => true,
            'expectReverseRevenueTransaction' => true,
            'wasLastNotRevertedPayoutTransaction' => true,
            'wasLastNotRevertedRevenueTransaction' => true,
        ];

        // Апрув, отказ, апрув. сумма не изменилась
        $caseReApproveAfterReject = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_REJECTED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'hasTransactionsFlagAfterSave'  => true,
            'expectOriginPayoutTransaction' => true,
            'expectOriginRevenueTransaction' => true,
            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,
            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,
        ];

        //не меняется статус и сумма изменилась
        $casePayoutAndRevenueChanged = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 90,
                'revenue'      => 110,
                'has_balance_transactions' => true,
            ],
            'hasTransactionsFlagAfterSave'  => true,
            'expectOriginPayoutTransaction' => true,
            'expectOriginRevenueTransaction' => true,

            'expectReversePayoutTransaction' => true,
            'expectReverseRevenueTransaction' => true,

            'wasLastNotRevertedPayoutTransaction' => true,
            'wasLastNotRevertedRevenueTransaction' => true,
        ];

        //не первая операция и сумма не изменилась
        $caseSumNotChanged = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_PENDING,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_APPROVED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => true,
            ],
            'hasTransactionsFlagAfterSave'  => true,
            'expectOriginPayoutTransaction' => true,
            'expectOriginRevenueTransaction' => true,

            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,

            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,
        ];

        //не-апрувнутый лид, меняем сумму и ничего не должно прозойти
        $caseStateNotApproveSumChanged = [
            'oldAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_REJECTED,
                'payout'       => 100,
                'revenue'      => 120,
                'has_balance_transactions' => false,
            ],
            'newAttrs' => [
                '_id'          => $conversionId,
                'affiliate_id' => 1,
                'currency_id'  => 1,
                'status'       => ar\Conversion::STATUS_REJECTED,
                'payout'       => 90,
                'revenue'      => 110,
                'has_balance_transactions' => false,
            ],
            'hasTransactionsFlagAfterSave'  => false,
            'expectOriginPayoutTransaction' => false,
            'expectOriginRevenueTransaction' => false,

            'expectReversePayoutTransaction' => false,
            'expectReverseRevenueTransaction' => false,

            'wasLastNotRevertedPayoutTransaction' => false,
            'wasLastNotRevertedRevenueTransaction' => false,
        ];

        return [
            $caseFirstTimeApprove,
            $caseFirstTimeEmptyApprove,
            $caseFirstTimeNotApproved,
            $caseRejectAfterApprove,
            $caseReApproveAfterReject,
            $casePayoutAndRevenueChanged,
            $caseSumNotChanged,
            $caseStateNotApproveSumChanged
        ];
    }
}
