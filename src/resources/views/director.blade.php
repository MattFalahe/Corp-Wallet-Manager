@extends('web::layouts.app')
@section('title','CorpWallet Manager - Director')
@section('content')
<div class="card mb-3">
  <div class="card-body">
    <h3>Live Wallet Balance</h3>
    <canvas id="walletChart" height="100"></canvas>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h3>Last 6 Months Comparison</h3>
    <canvas id="monthlyChart" height="100"></canvas>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-streaming@2.0.0"></script>
<script>
const refreshInterval = {{ config('corpwalletmanager.refresh_interval') }};
const decimals = {{ config('corpwalletmanager.decimals') }};
const colorActual = "{{ config('corpwalletmanager.color_actual') }}";
const colorPredicted = "{{ config('corpwalletmanager.color_predicted') }}";

// Progressive Live Chart
const ctxWallet = document.getElementById('walletChart').getContext('2d');
const walletChart = new Chart(ctxWallet, {
  type: 'line',
  data: { datasets:[
      { label:'Actual Balance', borderColor:colorActual, backgroundColor:'rgba(0,0,0,0)', data:[] },
      { label:'Predicted Balance', borderColor:colorPredicted, borderDash:[5,5], backgroundColor:'rgba(0,0,0,0)', data:[] }
    ]
  },
  options:{
    plugins:{ streaming:{
      duration:24*60*60*1000,
      refresh:refreshInterval,
      onRefresh:function(chart){
        fetch('{{ route("corpwalletmanager.latest") }}').then(res=>res.json()).then(data=>{
          const now = Date.now();
          chart.data.datasets[0].data.push({x:now,y:parseFloat(data.balance.toFixed(decimals))});
          chart.data.datasets[1].data.push({x:now,y:parseFloat(data.predicted.toFixed(decimals))});
        });
      }
    }}
  },
  scales:{
    x:{ type:'realtime', realtime:{duration:24*60*60*1000, refresh:refreshInterval, delay:2000}, title:{display:true,text:'Time'} },
    y:{ title:{display:true,text:'Balance (ISK)'} }
  }
});

// 6-Month Comparison Chart
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
fetch('{{ route("corpwalletmanager.monthly") }}').then(res=>res.json()).then(data=>{
  new Chart(ctxMonthly,{
    type:'bar',
    data:{labels:data.labels,datasets:[{label:'Net Balance',data:data.data,backgroundColor:data.data.map(v=>v>=0?colorActual:colorPredicted)}]},
    options:{scales:{y:{title:{display:true,text:'Balance (ISK)'}},x:{title:{display:true,text:'Month'}}}}
  });
});
</script>
@endsection
