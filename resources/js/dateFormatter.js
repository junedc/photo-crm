export const formatDateLabel = (value, timezone, options = {}) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        timeZone: timezone || 'UTC',
        ...options,
    }).format(date);
};
