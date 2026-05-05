import { useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { syncPushSubscription } from '../utils/pushNotifications';

export default function PushNotificationsBootstrap() {
  const { isAuthenticated, loading } = useAuth();

  useEffect(() => {
    if (loading || !isAuthenticated) {
      return;
    }

    syncPushSubscription();
  }, [isAuthenticated, loading]);

  return null;
}