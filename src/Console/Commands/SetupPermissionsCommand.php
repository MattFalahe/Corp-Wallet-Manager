<?php
namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\Web\Models\Acl\Permission;

class SetupPermissionsCommand extends Command
{
    protected $signature = 'corpwalletmanager:setup';
    
    protected $description = 'Setup CorpWallet Manager permissions in database';

    public function handle()
    {
        $this->info('Setting up CorpWallet Manager permissions...');
        
        $permissions = [
            'corpwalletmanager.view' => 'View Corp Wallet Manager',
            'corpwalletmanager.director_view' => 'Director View',
            'corpwalletmanager.member_view' => 'Member View',
            'corpwalletmanager.settings' => 'Manage Settings',
        ];
        
        foreach ($permissions as $name => $description) {
            $permission = Permission::firstOrCreate([
                'title' => $name
            ]);
            
            if ($permission->wasRecentlyCreated) {
                $this->info("Created permission: {$name}");
            } else {
                $this->info("Permission already exists: {$name}");
            }
        }
        
        $this->info('Setup complete! You can now assign these permissions in SeAT\'s Access Management.');
        $this->info('Don\'t forget to assign "corpwalletmanager.view" permission to see the sidebar menu.');
    }
}
