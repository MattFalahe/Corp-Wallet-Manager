<?php
// Updated Settings.php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'corpwalletmanager_settings';
    
    protected $fillable = [
        'key',
        'value'
    ];
    
    /**
     * Get a setting value by key
     */
    public static function getSetting($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
    
    /**
     * Set a setting value
     */
    public static function setSetting($key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
    
    /**
     * Get boolean setting value
     */
    public static function getBooleanSetting($key, $default = false)
    {
        $value = static::getSetting($key, $default ? '1' : '0');
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
