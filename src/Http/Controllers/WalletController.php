<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Http\Request;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;

class WalletController extends Controller
{
    public function director() {
        return view('corpwalletmanager::director');
    }

    public function member() {
        return view('corpwalletmanager::member');
    }

    public function latest() {
        $balance = 1000000000; // example, replace with DB logic
        $predicted = 1200000000;
        return response()->json(['balance'=>$balance,'predicted'=>$predicted]);
    }

    public function monthlyComparison() {
        $labels = ['Mar','Apr','May','Jun','Jul','Aug'];
        $data = [900000000, 1000000000, 1100000000, 1050000000, 1150000000, 1200000000];
        return response()->json(['labels'=>$labels,'data'=>$data]);
    }
}
