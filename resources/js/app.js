import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js';
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);
const menu = document.querySelector('[data-menu-toggle]');
menu?.addEventListener('click', () => {
  const header = menu.closest('.site-header');
  const open = header.classList.toggle('menu-open');
  menu.setAttribute('aria-expanded', String(open));
});
const usageCanvas = document.getElementById('usage-chart');
const usageData = document.getElementById('usage-chart-data');
if (usageCanvas && usageData) {
  const {labels, values} = JSON.parse(usageData.textContent);
  new Chart(usageCanvas, {type:'line',data:{labels,datasets:[{label:'kWh per interval',data:values,borderColor:'#0B8F7A',backgroundColor:'rgba(11,143,122,.1)',fill:true,tension:.3,pointRadius:0,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},plugins:{legend:{display:false}},scales:{x:{ticks:{maxTicksLimit:7,color:'#53686D'},grid:{display:false}},y:{beginAtZero:true,ticks:{color:'#53686D'},grid:{color:'#e8efea'}}}}});
}

const billCanvas = document.getElementById('bill-chart');
const billData = document.getElementById('bill-chart-data');
if (billCanvas && billData) {
  const {labels, amounts} = JSON.parse(billData.textContent);
  new Chart(billCanvas, {type:'line',data:{labels,datasets:[{label:'Monthly bill',data:amounts,borderColor:'#0B8F7A',backgroundColor:'rgba(11,143,122,.1)',fill:true,tension:.25,pointRadius:4,pointBackgroundColor:'#0B8F7A',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#53686D'}},y:{beginAtZero:true,grid:{color:'#e8efea'},ticks:{color:'#53686D'}}}}});
}
