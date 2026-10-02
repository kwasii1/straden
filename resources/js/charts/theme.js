/**
 * Shared chart tokens so every Chart.js chart in Straden reads as one system.
 *
 * Categorical order is fixed (validated for CVD separation on a white surface):
 * assign series in this order and never cycle. Status colours are reserved for
 * meaning (passed / failed) and never reused as "series N".
 */
export const series = ['#3d5bdb', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

export const status = {
    good: '#0ca30c',
    warning: '#fab219',
    critical: '#d03b3b',
    neutral: '#a1a1aa',
};

export const ink = {
    primary: '#18181b',
    secondary: '#52525b',
    muted: '#71717a',
    grid: '#f4f4f5',
    axis: '#e4e4e7',
    surface: '#ffffff',
};

/** Translucent fill for an area under a line. */
export const fill = (hex, alpha = 0.08) => {
    const value = parseInt(hex.slice(1), 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
};

/** Apply app-wide defaults once, before any chart is created. */
export const applyChartDefaults = (Chart) => {
    Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim() || 'Inter, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = ink.muted;
    Chart.defaults.borderColor = ink.grid;
    Chart.defaults.animation.duration = 300;
    Chart.defaults.animation.easing = 'easeOutQuart';
    Chart.defaults.elements.line.borderWidth = 2;
    Chart.defaults.elements.line.tension = 0.25;
    Chart.defaults.elements.point.radius = 0;
    Chart.defaults.elements.point.hoverRadius = 4;
    Chart.defaults.elements.point.hitRadius = 12;
    Chart.defaults.elements.bar.borderRadius = 4;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.boxHeight = 8;
    Chart.defaults.plugins.legend.labels.color = ink.secondary;
};
