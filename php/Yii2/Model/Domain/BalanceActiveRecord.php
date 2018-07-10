<?php

namespace common\models\ar;

use common\exception\DbObjectNotSaved;
use yii\db\ActiveQuery;
use common\models\domain;

/**
 * @property integer $id
 * @property integer $user_id
 * @property integer $team_id
 * @property integer $currency_id
 * @property string  $value
 *
 * @property Currency    $currency
 * @property User | null $user
 * @property Team | null $team
 *
 * класс используем только как репозиторий
 */
class Balance extends \yii\db\ActiveRecord
{
    public static function tableName() {
        return 'balance';
    }

    public function createModel() : domain\Balance {
        if (!$this->id) {
            throw new \Exception('Попытка создать domain из базы без ID');
        }

        $affiliate = $this->user;
        $team      = $this->team;
        $amount    = (float) $this->value;
        $currency  = $this->currency;
        $id        = $this->id;

        return domain\Balance::create($affiliate, $team, $amount, $currency, $id);
    }

    public static function saveModel(domain\Balance $balance) {
        $ar = new static();

        if ($balance->getId()) {
            $ar->id =$balance->getId();
            $ar->refresh();
        }

        $ar->currency_id = $balance->getCurrency()->id;
        $ar->value       = $balance->getAmount();
        if ($balance instanceof domain\BalanceAffiliate) {
            $ar->user_id     = $balance->getUser()->getId();
        }
        if ($balance instanceof domain\BalanceTeam) {
            $ar->team_id = $balance->getTeam()->id;
        }

        if (!$ar->save()) throw new DbObjectNotSaved($ar);
    }

    /** @return static | null */
    public static function findByModel(domain\Balance $balance) {
        return static::findOne($balance->getId());
    }

    /**
     * если баланса не было он будет создан и сохранен сразу
     */
    public static function findByPayment(Payment $payment) : domain\Balance {
        return static::findByUser($payment->user_id, $payment->currency_id);
    }

    /**
     * если баланса не было он будет создан и сохранен сразу
     */
    public static function findByBonus(Bonus $bonus) : domain\Balance {
        return static::findByUser($bonus->user_id, $bonus->currency_id);
    }

    /**
     * если баланса не было он будет создан и сохранен сразу
     */
    public static function findByUser(int $userId, int $currencyId) : domain\Balance {
        $balanceAr = static::findOne([
            'currency_id' => $currencyId,
            'user_id'     => $userId
        ]);

        if (!$balanceAr) {
            //создаем новый баланс если его нет
            $balanceAr = new static([
                'user_id'     => $userId,
                'currency_id' => $currencyId,
                'value'       => 0,
            ]);

            if (!$balanceAr->insert()) throw new \Exception('Баланс не удалось создать.');
        }

        return $balanceAr->createModel();
    }

    /**
     * если баланса не было он будет создан и сохранен сразу
     */
    public static function findByTeam(int $teamId, int $currencyId) : domain\Balance {
        $balanceAr = static::findOne([
            'currency_id' => $currencyId,
            'team_id'     => $teamId
        ]);

        if (!$balanceAr) {
            //создаем новый баланс если его нет
            $balanceAr = new static([
                'team_id'     => $teamId,
                'currency_id' => $currencyId,
                'value'       => 0,
            ]);

            if (!$balanceAr->save()) throw new DbObjectNotSaved($balanceAr);
        }

        return $balanceAr->createModel();
    }

    public function rules() {
        return [
            [['user_id', 'currency_id', 'team_id'], 'integer'],
            [['value'], 'number'],
            [['currency_id'], 'exist', 'skipOnError' => true, 'targetClass' => Currency::class, 'targetAttribute' => ['currency_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function attributeLabels() {
        return [
            'id'          => 'ID',
            'user_id'     => 'User ID',
            'team_id'     => 'Team ID',
            'currency_id' => 'Currency ID',
            'value'       => 'Value',
        ];
    }

    public function getCurrency() : ActiveQuery {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getUser() : ActiveQuery {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTeam() : ActiveQuery {
        return $this->hasOne(Team::class, ['id' => 'team_id']);
    }
}
