/**
 * Shared schedule utilities used in Employee schedule views.
 * Extracted to avoid duplication between Employees/Schedule/Index.tsx
 * and the inline schedule section in Employees/Show.tsx.
 */

export const DAYS_KEYS = [
    'schedule.sunday',
    'schedule.monday',
    'schedule.tuesday',
    'schedule.wednesday',
    'schedule.thursday',
    'schedule.friday',
    'schedule.saturday',
] as const;

/**
 * Converts a "HH:MM:SS" or "HH:MM" time string into a human-readable
 * 12-hour format, e.g. "9:00 AM".
 */
export const formatTime = (time: string): string => {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
};
