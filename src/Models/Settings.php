<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'corpwalletmanager_settings';
    protected $fillable = ['key','value'];
}
