// Lightweight logger that gates output to development only.
const isDev = typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.DEV;

const noop = () => {};

export const logger = {
  log: isDev ? (...args) => console.log(...args) : noop,
  info: isDev ? (...args) => console.info(...args) : noop,
  warn: isDev ? (...args) => console.warn(...args) : noop,
  error: isDev ? (...args) => console.error(...args) : noop,
  group: isDev ? (...args) => console.group(...args) : noop,
  groupEnd: isDev ? () => console.groupEnd() : noop,
};

export default logger;
