export const getDashboardRouteByRole = (rawRole) => {
  const role = typeof rawRole === 'string' ? rawRole.trim().toLowerCase() : '';

  if (role === 'admin') {
    return '/admin/dashboard';
  }

  if (role === 'staff' || role === 'cashier') {
    return '/cashier';
  }

  return '/dashboard';
};

const POST_AUTH_REDIRECT_KEY = 'post_auth_redirecting';
const POST_AUTH_REDIRECT_TTL_MS = 10000;

const getSessionStorage = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.sessionStorage;
};

export const markPostAuthRedirecting = () => {
  const storage = getSessionStorage();

  if (!storage) {
    return;
  }

  storage.setItem(POST_AUTH_REDIRECT_KEY, String(Date.now()));
};

export const clearPostAuthRedirecting = () => {
  const storage = getSessionStorage();

  if (!storage) {
    return;
  }

  storage.removeItem(POST_AUTH_REDIRECT_KEY);
};

export const isPostAuthRedirecting = () => {
  const storage = getSessionStorage();

  if (!storage) {
    return false;
  }

  const rawValue = storage.getItem(POST_AUTH_REDIRECT_KEY);

  if (!rawValue) {
    return false;
  }

  const startedAt = Number(rawValue);

  if (!Number.isFinite(startedAt)) {
    storage.removeItem(POST_AUTH_REDIRECT_KEY);
    return false;
  }

  if (Date.now() - startedAt > POST_AUTH_REDIRECT_TTL_MS) {
    storage.removeItem(POST_AUTH_REDIRECT_KEY);
    return false;
  }

  return true;
};
