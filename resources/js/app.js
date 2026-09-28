import './bootstrap';
import './echo';
import Alert from './alerts';
import ApexCharts from 'apexcharts';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.Alert = Alert;

Alpine.start();
