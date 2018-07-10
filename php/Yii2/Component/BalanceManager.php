<?php

namespace common\components;

use common\models\ar\BalancePendingTest as BalancePending;
use common\models\ar\BalanceTest as Balance;
use common\models\ar\Balance as BalanceReal;
use common\models\ar\Conversion;
use common\models\ar\Payment;
use common\models\ar;
use common\models\ar\Team;
use common\models\ar\User;
use \common\models\domain;
use common\models\ar\relation;
use Yii;
use yii\base\Component;

/**
 * Отвечает за пополнение и списание с балансов
 */
class BalanceManager extends Component
{
    /** @var User */
    protected $_operator;

    public function init() {
        $this->_operator = Yii::$app->user->identity ?? User::getSystem();
    }

    /**
     * Начисляем выплату вебу по конверсии (баланс + история)
     */
    public function conversionAddAffiliatePayout(Conversion $conversion) {
        $delta           = $conversion->payout;
        $comment         = $conversion->payoutInfo ? $conversion->payoutInfo->payoutAffiliateText() : '';
        $balance         = Balance::findByUser($conversion->affiliate_id, $conversion->currency_id);
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    /**
     * коррекция выплаты вебу (баланс + история)
     * @throws \Exception
     */
    public function conversionUpdateAffiliatePayout(Conversion $conversion, float $oldPayout) {
        $newPayout       = (float) $conversion->payout;
        $delta           = $newPayout - $oldPayout;
        $comment         = "Изменена сумма выплаты за лид было $oldPayout, стало $newPayout.";
        $balance         = Balance::findByUser($conversion->affiliate_id, $conversion->currency_id);
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    /**
     * Списание выплаты вебу (баланс + история)
     * @throws \Exception
     */
    public function conversionRemoveAffiliatePayout(Conversion $conversion) {
        $delta           = -1 * (float) $conversion->payout;
        $balance         = Balance::findByUser($conversion->affiliate_id, $conversion->currency_id);
        $comment         = "Лид октлонен. Сумма списана $delta.";
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    //============================= team payout

    public function conversionAddTeamPayout(Conversion $conversion) {
        $delta           = (float) $conversion->payout_team;
        $balance         = Balance::findByTeam($conversion->affiliate->team_id, $conversion->currency_id);
        $comment         = $conversion->payoutInfo ? $conversion->payoutInfo->payoutTeamText() : '';
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    public function conversionUpdateTeamPayout(Conversion $conversion, float $oldPayout) {
        $newPayout       = (float) $conversion->payout_team;
        $delta           = $newPayout - $oldPayout;
        $balance         = Balance::findByTeam($conversion->affiliate->team_id, $conversion->currency_id);
        $comment         = "Изменена сумма выплаты за лид было $oldPayout, стало $newPayout.";
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    public function conversionRemoveTeamPayout(Conversion $conversion) {
        $delta           = -1 * (float) $conversion->payout_team;
        $balance         = Balance::findByTeam($conversion->affiliate->team_id, $conversion->currency_id);
        $comment         = "Лид октлонен. Сумма списана $delta.";
        $transactionType = domain\Transaction::TYPE_CONVERSION_PAYOUT_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    //============================= Revenue

    /**
     * Вычетает с рекла выплату по конверсии (баланс + история)
     *  @throws \Exception
     */
    public function conversionTakeRevenue(Conversion $conversion) {
        $delta           = -1 * (float) $conversion->revenue;
        $balance         = Balance::findByUser($conversion->offer->advertiser_id, $conversion->currency_id);
        $comment         = $conversion->payoutInfo ? $conversion->payoutInfo->revenueText() : '';
        $transactionType = domain\Transaction::TYPE_CONVERSION_REVENUE;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    /**
     * коррекция вычета с рекла (баланс + история)
     * @throws \Exception
     */
    public function conversionUpdateRevenue(Conversion $conversion, float $oldRevenue) {
        $newRevenue      = (float) $conversion->revenue;
        $delta           = $oldRevenue - $newRevenue;
        $balance         = Balance::findByUser($conversion->offer->advertiser_id, $conversion->currency_id);
        $comment         ="Изменена сумма вычета за лид было -$oldRevenue, стало -$newRevenue.";
        $transactionType = domain\Transaction::TYPE_CONVERSION_REVENUE_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    /**
     * Возврат вычета с рекла (баланс + история)
     * @throws \Exception
     */
    public function conversionRemoveRevenue(Conversion $conversion) {
        $delta           = (float) $conversion->revenue;
        $balance         = Balance::findByUser($conversion->offer->advertiser_id, $conversion->currency_id);
        $comment         = "Лид октлонен. Сумма возвращена $delta.";
        $transactionType = domain\Transaction::TYPE_CONVERSION_REVENUE_CORRECTION;

        $this->_conversionUpdateBalance($delta, $balance, $conversion, $transactionType, $comment);
    }

    //============================= конверсии в ожидании

    /**
     * Изменяем ожидаемый баланс веба
     */
    public function conversionUpdatePendingAffiliatePayout(Conversion $conversion, float $delta) {
        if ($delta && $conversion->affiliate_id) {
            BalancePending::changeAffiliateAmount($delta, $conversion->affiliate_id, $conversion->offer->currency_id);
        }
    }

    /**
     * Изменяем ожидаемый баланс комманды
     */
    public function conversionUpdatePendingTeamPayout(Conversion $conversion, float $delta) {
        if ($delta && $conversion->affiliate->isInTeam()) {
            BalancePending::changeTeamAmount($delta, $conversion->affiliate->team_id, $conversion->offer->currency_id);
        }
    }

    //============================= перевод средств команды на баланс лидера

    /**
     * списывание баланса команды в пользу лидера
     */
    public function withdrawToLeader(Team $team) {

        //todo здесь оперируем реальными балансами, т.к. нет триггеров на перевод
        //todo командного баланса
        foreach ($team->balances as $teamBalanceAr) {
            $teamBalance = $teamBalanceAr->createModel();
            if ($teamBalance->getAmount() <= 0 ) {
                //не делаем ничего, если баланс отрицательный
                continue;
            }

            $leaderBalance = BalanceReal::findByUser($team->leader_id, $teamBalance->getCurrency()->id);

            $leaderBalance->add($teamBalance->getAmount());
            $teamBalance->take($teamBalance->getAmount());

            $withdrawTransaction = new domain\Transaction(
                $teamBalance,
                domain\Transaction::TYPE_WITHDRAW,
                -1 * $teamBalance->getAmount(),
                $this->_operator,
                "Перевод командного баланса Лидеру(balance_id #{$leaderBalance->getId()}"
            );

            $depositTransaction = new domain\Transaction(
                $leaderBalance,
                domain\Transaction::TYPE_DEPOSIT,
                $teamBalance->getAmount(),
                $this->_operator,
                "Перевод с командного баланса(balance_id #{$teamBalance->getId()}) "
            );

            $dbTransaction = Yii::$app->db->beginTransaction();
            try {
                BalanceReal::saveModel($leaderBalance);
                BalanceReal::saveModel($teamBalance);
                ar\Transaction::saveModel($withdrawTransaction);
                ar\Transaction::saveModel($depositTransaction);

                $dbTransaction->commit();
            } catch (\Throwable $t) {
                $dbTransaction->rollBack();
                throw $t;
            }

        }
    }

    //============================= Выплаты

    /**
     * Возврат выплаты
     */
    public function refundPayment(Payment $payment) {
        $delta = (float) $payment->sum;

        if ($delta) {
            $comment = "Выплата октлонена";
            $this->_updatePayment($payment, $delta, domain\Transaction::TYPE_WITHDRAW_CORRECTION, $comment);
        }
    }

    /**
     * Списание выплаты
     */
    public function withdrawPayment(Payment $payment) {
        $delta = -1 * (float) $payment->sum;

        if ($delta) {
            $this->_updatePayment($payment, $delta, domain\Transaction::TYPE_WITHDRAW, null);
        }
    }

    public function updatePayment(Payment $payment, float $oldValue) {
        $newValue = (float) $payment->sum;
        $delta    = $oldValue - $newValue;

        if ($delta) {
            $comment = "Изменена сумма выплаты, стало : $newValue.";
            $this->_updatePayment($payment, $delta, domain\Transaction::TYPE_WITHDRAW_CORRECTION, $comment);
        }
    }

    protected function _updatePayment(ar\Payment $payment, float $delta, string $transactionType, string $comment = null) {

        if ($delta) {

            $balance = Balance::findByPayment($payment);

            $balance->add($delta);

            Balance::saveModel($balance);

            $balanceTransaction = new domain\Transaction(
                $balance,
                $transactionType,
                $delta,
                $this->_getPaymentOperator(),
                $comment
            );

            ar\Transaction::saveModel($balanceTransaction);

            relation\PaymentTransaction::saveBy($balanceTransaction->getId(), $payment->id);
        }
    }

    //============================= Бонусы

    public function addBonus(ar\Bonus $bonus) {
        $delta = (float) $bonus->amount;

        if ($delta) {
            $transactionType = domain\Transaction::TYPE_BONUS;
            $this->_updateBonus($bonus, $delta, $transactionType);
        }
    }

    public function removeBonus(ar\Bonus $bonus) {
        $delta = -1 * (float) $bonus->amount;

        if ($delta) {
            $transactionType = domain\Transaction::TYPE_BONUS_CORRECTION;
            $comment         = "Бонус октлонен";

            $this->_updateBonus($bonus, $delta, $transactionType, $comment);
        }
    }

    public function updateBonus(ar\Bonus $bonus, float $oldValue) {
        $newValue = (float) $bonus->amount;
        $delta    = $newValue - $oldValue;

        if ($delta) {
            $transactionType = domain\Transaction::TYPE_BONUS_CORRECTION;
            $comment         = "Изменена сумма выплаты. Стало : $newValue";

            $this->_updateBonus($bonus, $delta, $transactionType, $comment);
        }
    }

    protected function _updateBonus(ar\Bonus $bonus, float $delta, string $transactionType, string $comment = null) {
        if ($delta) {
            $balance = Balance::findByBonus($bonus);

            $balance->add($delta);

            Balance::saveModel($balance);

            $balanceTransaction = new domain\Transaction(
                $balance,
                $transactionType,
                $delta,
                $this->_getPaymentOperator(),
                $comment
            );

            ar\Transaction::saveModel($balanceTransaction);

            relation\BonusTransaction::saveBy($balanceTransaction->getId(), $bonus->id);
        }
    }

    protected function _getPaymentOperator() : User {

        if (!$this->_operator->isAdmin()) {

            //если оператор определяется не как админ - значит транзакция выполняется уже при
            //создании выплаты со статусом "В ожидании" (это требование логики)
            return User::getSystem();
        }

        return $this->_operator;
    }

    protected function _conversionUpdateBalance(
        float          $delta,
        domain\Balance $balance,
        Conversion     $conversion,
        string         $transactionType,
        string         $comment
    ) {
        if  ($delta) {
            $balance->add($delta);

            Balance::saveModel($balance);

            $balanceTransaction = new domain\Transaction(
                $balance,
                $transactionType,
                $delta,
                $this->_operator,
                $comment,
                $conversion->_id
            );

            ar\Transaction::saveModel($balanceTransaction);

            $conversion->has_balance_transactions = true;
        }
    }
}