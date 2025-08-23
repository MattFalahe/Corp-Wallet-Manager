<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyBalance extends Model
{
    protected $table = 'corpwalletmanager_monthly_balances';
    protected $fillable = ['month','balance'];
}
