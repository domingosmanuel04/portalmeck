/**
 * notifications.js - Sistema global de notificações para Meck
 */

const MeckNotifications = {
    // Toast notifications (bottom-right)
    toast: function(message, type = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#1e293b',
            color: '#f8fafc',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: type,
            title: message
        });
    },

    // Success message
    success: function(title, text = '') {
        return Swal.fire({
            icon: 'success',
            title: title,
            text: text,
            background: '#101622',
            color: '#f8fafc',
            confirmButtonColor: '#2563eb',
            customClass: {
                popup: 'rounded-2xl border border-slate-800 shadow-2xl',
                confirmButton: 'rounded-xl px-6 py-2'
            }
        });
    },

    // Error message
    error: function(title, text = '') {
        return Swal.fire({
            icon: 'error',
            title: title,
            text: text,
            background: '#101622',
            color: '#f8fafc',
            confirmButtonColor: '#ef4444',
            customClass: {
                popup: 'rounded-2xl border border-slate-800 shadow-2xl',
                confirmButton: 'rounded-xl px-6 py-2'
            }
        });
    },

    // Warning/Alert message
    warning: function(title, text = '') {
        return Swal.fire({
            icon: 'warning',
            title: title,
            text: text,
            background: '#101622',
            color: '#f8fafc',
            confirmButtonColor: '#f59e0b',
            customClass: {
                popup: 'rounded-2xl border border-slate-800 shadow-2xl',
                confirmButton: 'rounded-xl px-6 py-2'
            }
        });
    },

    // Confirmation dialog
    confirm: function(title, text, confirmButtonText = 'Sim, continuar') {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#1e293b',
            confirmButtonText: confirmButtonText,
            cancelButtonText: 'Cancelar',
            background: '#101622',
            color: '#f8fafc',
            customClass: {
                popup: 'rounded-2xl border border-slate-800 shadow-2xl',
                confirmButton: 'rounded-xl px-6 py-2',
                cancelButton: 'rounded-xl px-6 py-2'
            }
        });
    }
};

// Auto-replace window.alert if needed (optional, but good for uniformity)
// window.alert = (msg) => MeckNotifications.toast(msg, 'info');
