export const bytes = (value: number, decimals = 1): string => {
    if (!value) return '0 B';
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    const i = Math.floor(Math.log(value) / Math.log(1024));
    return `${(value / 1024 ** i).toFixed(decimals)} ${units[i]}`;
};

export const megabytes = (mb: number): string => bytes(mb * 1024 * 1024, mb >= 1024 ? 1 : 0);

export const uptime = (seconds: number): string => {
    if (!seconds) return 'offline';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return d > 0 ? `${d}d ${h}h` : h > 0 ? `${h}h ${m}m` : `${m}m`;
};

export const percent = (used: number, total: number): number =>
    total <= 0 ? 0 : Math.min(100, Math.round((used / total) * 100));
