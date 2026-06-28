import { readonly, ref } from 'vue';

const DEFAULT_DURATION = 4200;
const toasts = ref([]);
let nextToastId = 1;

const pushToast = ({ message, title = '', type = 'info', duration = DEFAULT_DURATION }) => {
    const id = nextToastId++;

    toasts.value.push({
        id,
        message,
        title,
        type,
    });

    if (duration > 0) {
        window.setTimeout(() => dismissToast(id), duration);
    }

    return id;
};

const dismissToast = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
};

export const toastService = {
    toasts: readonly(toasts),
    show: pushToast,
    dismiss: dismissToast,
    success: (message, options = {}) => pushToast({ ...options, message, type: 'success' }),
    error: (message, options = {}) => pushToast({ ...options, message, type: 'error' }),
    info: (message, options = {}) => pushToast({ ...options, message, type: 'info' }),
};
