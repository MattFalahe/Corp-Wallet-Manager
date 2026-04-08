<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        try {
            if (!is_string($key) || empty($key)) {
                return $default;
            }
            
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        } catch (\Exception $e) {
            Log::warning('Settings: Failed to get setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
    
    /**
     * Set a setting value
     */
    public static function setSetting($key, $value)
    {
        try {
            if (!is_string($key) || empty($key)) {
                throw new \InvalidArgumentException('Setting key must be a non-empty string');
            }
            
            return static::updateOrCreate(
                ['key' => $key],
                ['value' => (string)$value]
            );
        } catch (\Exception $e) {
            Log::error('Settings: Failed to set setting', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get boolean setting value
     */
    public static function getBooleanSetting($key, $default = false)
    {
        try {
            $value = static::getSetting($key, $default ? '1' : '0');
            
            // Handle various true values
            if (is_bool($value)) {
                return $value;
            }
            
            // Check for string true values
            return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
            
        } catch (\Exception $e) {
            Log::warning('Settings: Failed to get boolean setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
    
    /**
     * Get integer setting value
     */
    public static function getIntegerSetting($key, $default = 0)
    {
        try {
            $value = static::getSetting($key, $default);
            return is_numeric($value) ? (int)$value : $default;
        } catch (\Exception $e) {
            Log::warning('Settings: Failed to get integer setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
}
