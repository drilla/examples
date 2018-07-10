<?php

namespace common\models\domain;

use common\models\ar\Currency;
use common\models\ar\Team;
use common\models\ar\User;

abstract class Balance
{
    /** @var Currency  */
    private $_currency;

    /** @var float  */
    private $_amount;

    /** @var int | null */
    private $_id = null;

    public function __construct(float $amount, Currency $currency, int $id = null) {
        $this->_amount   = $amount;
        $this->_currency = $currency;
        $this->_id       = $id;
    }

    final public static function create(
        User $user = null,
        Team $team = null,
        float $amount,
        Currency $currency,
        int $id = null
    ) : self {
        if (! ($user XOR $team)) throw new \Exception('Нужен либо юзер либо команда для создания баланса');

        if ($user) return new BalanceAffiliate($user, $amount, $currency, $id);
        if ($team) return new BalanceTeam($team, $amount, $currency, $id);

        throw new \DomainException('Unexpected branch');
    }

    public function getCurrency(): Currency {
        return $this->_currency;
    }

    public function getAmount(): float {
        return $this->_amount;
    }

    /** @return int | null */
    public function getId() {
        return $this->_id;
    }

    /**
     * изменяет баланс на $amount - можно отрицательное
     */
    public function add(float $amount) {
        $this->_amount += $amount;
    }

    /**
     * уменьшает баланс на $amount - можно отрицательное
     */
    public function take(float $amount) {
        $this->add(-1 * $amount);
    }
}
