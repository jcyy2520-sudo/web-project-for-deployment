import axios from 'axios';

const SERVICE_WORKER_READY_TIMEOUT_MS = 2000;

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let index = 0; index < rawData.length; index += 1) {
    outputArray[index] = rawData.charCodeAt(index);
  }

  return outputArray;
}

export function isPushSupported() {
  return typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;
}

async function getRegistration() {
  if (!isPushSupported()) {
    return null;
  }

  const existingRegistration = await navigator.serviceWorker.getRegistration();

  if (existingRegistration) {
    return existingRegistration;
  }

  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise((resolve) => {
      window.setTimeout(() => resolve(null), SERVICE_WORKER_READY_TIMEOUT_MS);
    }),
  ]);
}

export async function getDevicePushStatus() {
  if (!isPushSupported()) {
    return {
      supported: false,
      permission: 'unsupported',
      subscribed: false,
    };
  }

  const registration = await getRegistration();
  const subscription = registration ? await registration.pushManager.getSubscription() : null;

  return {
    supported: true,
    permission: Notification.permission,
    subscribed: Boolean(subscription),
  };
}

export async function subscribeDeviceToPush({ requestPermission = false } = {}) {
  if (!isPushSupported()) {
    return { success: false, message: 'Push notifications are not supported on this device.' };
  }

  let permission = Notification.permission;
  if (requestPermission && permission !== 'granted') {
    permission = await Notification.requestPermission();
  }

  if (permission !== 'granted') {
    return {
      success: false,
      message: permission === 'denied'
        ? 'Notification permission is blocked in this browser.'
        : 'Notification permission was not granted.',
      permission,
    };
  }

  const registration = await getRegistration();
  if (!registration) {
    return { success: false, message: 'Service worker is not ready yet.' };
  }

  let subscription = await registration.pushManager.getSubscription();

  if (!subscription) {
    const response = await axios.get('/api/push/public-key');
    const publicKey = response.data?.data?.public_key;

    if (!publicKey) {
      return { success: false, message: 'Push public key is not available.' };
    }

    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey),
    });
  }

  const payload = subscription.toJSON();
  await axios.post('/api/push/subscriptions', payload);

  return {
    success: true,
    message: 'Push notifications enabled on this device.',
    permission,
    subscribed: true,
  };
}

export async function syncPushSubscription() {
  if (!isPushSupported() || Notification.permission !== 'granted') {
    return { success: false, silent: true };
  }

  try {
    return await subscribeDeviceToPush({ requestPermission: false });
  } catch (error) {
    return {
      success: false,
      silent: true,
      message: error.response?.data?.message || error.message || 'Failed to sync push subscription.',
    };
  }
}

export async function unsubscribeDeviceFromPush({ unsubscribeBrowser = true } = {}) {
  if (!isPushSupported()) {
    return { success: true, message: 'Push is not supported.' };
  }

  const registration = await getRegistration();
  if (!registration) {
    return { success: true, message: 'No service worker registration found.' };
  }

  const subscription = await registration.pushManager.getSubscription();
  if (!subscription) {
    return { success: true, message: 'No active push subscription found.' };
  }

  try {
    await axios.delete('/api/push/subscriptions', {
      data: {
        endpoint: subscription.endpoint,
      },
    });
  } catch (error) {
    if (error.response?.status !== 422) {
      throw error;
    }
  }

  if (unsubscribeBrowser) {
    await subscription.unsubscribe();
  }

  return { success: true, message: 'Push notifications disabled on this device.' };
}