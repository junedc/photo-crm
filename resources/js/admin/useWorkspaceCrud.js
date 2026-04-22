import axios from 'axios';
import { ref } from 'vue';

export function emitAdminToast(detail) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent('admin-toast', { detail }));
}

export function useWorkspaceCrud() {
    const saving = ref(false);
    const deleting = ref(false);
    const message = ref('');
    const errors = ref([]);
    const fieldErrors = ref({});
    const csrfToken = window.adminProps?.csrfToken ?? '';

    const clearFeedback = () => {
        message.value = '';
        errors.value = [];
        fieldErrors.value = {};
    };

    const errorMessagesFromResponse = (error, fallback) => {
        if (error.response?.status === 413) {
            return ['The uploaded file is too large. Please choose a smaller file.'];
        }

        const responseMessage = error.response?.data?.message;

        return [responseMessage || fallback];
    };

    const submitForm = async ({ url, data, method = 'post' }) => {
        saving.value = true;
        clearFeedback();

        try {
            const response = await axios({
                method,
                url,
                data,
                headers: {
                    Accept: 'application/json',
                },
            });

            message.value = response.data.message ?? 'Saved.';
            emitAdminToast({
                type: 'success',
                message: message.value,
            });

            return response.data.record;
        } catch (error) {
            if (error.response?.status === 422) {
                fieldErrors.value = error.response.data.errors ?? {};
                errors.value = Object.values(fieldErrors.value).flat();
            } else {
                errors.value = errorMessagesFromResponse(error, 'Something went wrong while saving.');
            }

            emitAdminToast({
                type: 'error',
                errors: errors.value,
            });

            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteRecord = async ({ url, redirectTo = null }) => {
        deleting.value = true;
        clearFeedback();

        try {
            const formData = new FormData();
            formData.append('_method', 'DELETE');

            if (csrfToken) {
                formData.append('_token', csrfToken);
            }

            const response = await axios({
                method: 'post',
                url,
                data: formData,
                headers: {
                    Accept: 'application/json',
                },
            });

            message.value = response.data.message ?? 'Deleted.';
            emitAdminToast({
                type: 'success',
                message: message.value,
            });

            if (redirectTo) {
                window.setTimeout(() => {
                    window.location.href = redirectTo;
                }, 250);
            }

            return response.data;
        } catch (error) {
            if (error.response?.status === 422) {
                errors.value = [error.response.data.message ?? 'This record cannot be deleted yet.'];
            } else {
                errors.value = ['Something went wrong while deleting.'];
            }

            emitAdminToast({
                type: 'error',
                errors: errors.value,
            });

            throw error;
        } finally {
            deleting.value = false;
        }
    };

    return {
        saving,
        deleting,
        message,
        errors,
        fieldErrors,
        clearFeedback,
        submitForm,
        deleteRecord,
    };
}
