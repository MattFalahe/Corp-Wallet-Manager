<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $table = 'corpwalletmanager_predictions';
    protected $fillable = ['date','predicted_balance'];
}
