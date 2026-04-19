export const isBlank = (value) => String(value ?? '').trim() === '';

export const firstError = (errors, field) => {
    const value = errors?.[field];

    return Array.isArray(value) ? value[0] : value;
};

export const mergeFieldErrors = (clientErrors, serverErrors) => ({
    ...(serverErrors ?? {}),
    ...(clientErrors ?? {}),
});

export const hasFieldErrors = (errors) => Object.keys(errors ?? {}).length > 0;

export const requiredMessage = (label) => `${label} is required.`;

export const validEmail = (value) => isBlank(value) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
