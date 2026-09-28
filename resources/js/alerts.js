import Swal from 'sweetalert2';

function isDark() {
  return document.documentElement.classList.contains('dark');
}

function getSwalConfig() {
  const dark = isDark();
  return Swal.mixin({
    background: dark ? '#161C26' : '#FFFFFF',
    color: dark ? '#F1F5F9' : '#0F172A',
    customClass: {
      popup: dark 
        ? 'border border-[#1A222D] rounded-xl shadow-2xl backdrop-blur-md' 
        : 'border border-[#E2E8F0] rounded-xl shadow-xl',
      title: `text-base font-semibold tracking-tight ${dark ? 'text-slate-100' : 'text-slate-900'}`,
      htmlContainer: `text-sm font-normal ${dark ? 'text-slate-400' : 'text-slate-600'}`,
      confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs px-4 py-2 rounded-lg transition shadow-sm cursor-pointer',
      cancelButton: `${dark ? 'bg-[#1C2430] hover:bg-[#263342] text-slate-300 border-[#1A222D]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'} font-medium text-xs px-4 py-2 rounded-lg border transition ml-2 cursor-pointer`,
      denyButton: 'bg-rose-600 hover:bg-rose-700 text-white font-medium text-xs px-4 py-2 rounded-lg transition ml-2 cursor-pointer'
    },
    buttonsStyling: false,
  });
}

export const Alert = {
  toast: (title, icon = 'success') => {
    const dark = isDark();
    return getSwalConfig().fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: title,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      background: dark ? '#0F141C' : '#FFFFFF',
      customClass: {
        popup: `border ${dark ? 'border-[#1A222D]' : 'border-[#E2E8F0]'} rounded-lg shadow-xl !px-4 !py-3`
      }
    });
  },

  success: (title, text = '') => {
    return getSwalConfig().fire({
      icon: 'success',
      iconColor: isDark() ? '#10B981' : '#059669',
      title: title,
      text: text,
      confirmButtonText: 'Selesai'
    });
  },

  error: (title, text = '') => {
    return getSwalConfig().fire({
      icon: 'error',
      iconColor: isDark() ? '#F43F5E' : '#E11D48',
      title: title,
      text: text,
      confirmButtonText: 'Tutup'
    });
  },

  confirmDelete: (title = 'Hapus Jurnal Trade?', text = 'Data transaksi dan screenshot terkait akan dihapus secara permanen.') => {
    return getSwalConfig().fire({
      title: title,
      text: text,
      icon: 'warning',
      iconColor: isDark() ? '#F43F5E' : '#E11D48',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
      customClass: {
        popup: isDark() ? 'border border-[#1A222D] rounded-xl shadow-2xl' : 'border border-[#E2E8F0] rounded-xl shadow-xl',
        title: `text-base font-semibold ${isDark() ? 'text-slate-100' : 'text-slate-900'}`,
        htmlContainer: `text-sm ${isDark() ? 'text-slate-400' : 'text-slate-600'}`,
        confirmButton: 'bg-rose-600 hover:bg-rose-700 text-white font-medium text-xs px-4 py-2 rounded-lg transition cursor-pointer',
        cancelButton: `${isDark() ? 'bg-[#1C2430] hover:bg-[#263342] text-slate-300 border-[#1A222D]' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'} font-medium text-xs px-4 py-2 rounded-lg border transition ml-2 cursor-pointer`
      }
    });
  },

  liveUpdateToast: (message, type = 'info') => {
    const dark = isDark();
    return getSwalConfig().fire({
      toast: true,
      position: 'bottom-end',
      icon: type === 'profit' ? 'success' : (type === 'loss' ? 'error' : 'info'),
      iconColor: type === 'profit' ? (dark ? '#10B981' : '#059669') : (type === 'loss' ? (dark ? '#F43F5E' : '#E11D48') : '#0284C7'),
      title: `<span class="font-mono text-xs">${message}</span>`,
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
      background: dark ? '#0F141C' : '#FFFFFF'
    });
  }
};

window.Alert = Alert;
export default Alert;
