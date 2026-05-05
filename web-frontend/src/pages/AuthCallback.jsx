import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { useAuth } from '../context/AuthContext';
import {
  clearPostAuthRedirecting,
  getDashboardRouteByRole,
  markPostAuthRedirecting,
} from '../utils/authRedirect';

/**
 * AuthCallback Component
 * Dedicated page to handle OAuth redirects from the backend.
 * Parses tokens/messages from URL hash/query and redirects user to the final destination.
 */
const AuthCallback = () => {
  const navigate = useNavigate();

  const { setAuthData } = useAuth();

  useEffect(() => {
    const handleCallback = async () => {
      // Parse parameters from hash or search
      const hashRaw = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
      const hashParams = new URLSearchParams(hashRaw);
      const searchParams = new URLSearchParams(window.location.search);
      
      // Values can be in hash (preferred) or query string
      const oauthStatus = hashParams.get('oauth') || searchParams.get('oauth');
      const message = hashParams.get('message') || searchParams.get('message');
      const tab = hashParams.get('tab') || searchParams.get('tab');
      const registrationStatus = hashParams.get('registration') || searchParams.get('registration');

      // 1. Handle registration confirmation (email link)
      if (registrationStatus === 'confirmed') {
        clearPostAuthRedirecting();
        sessionStorage.setItem('oauth_success_message', 'Registration confirmed. You can now sign in.');
        navigate('/?auth_modal=open', { replace: true });
        return;
      }

      // 2. Handle OAuth Errors
      if (oauthStatus === 'error') {
        clearPostAuthRedirecting();
        if (message) sessionStorage.setItem('oauth_error_message', message);
        if (tab) sessionStorage.setItem('oauth_error_tab', tab);
        navigate('/?auth_modal=open', { replace: true });
        return;
      }

      // 3. Handle OAuth Success
      if (oauthStatus === 'success') {
        try {
          // Clean the callback URL before doing follow-up auth requests.
          window.history.replaceState(null, '', window.location.pathname);

          await axios.get('/sanctum/csrf-cookie');
          const userResp = await axios.get('/api/user');
          const userData = userResp.data?.data || userResp.data;
          
          setAuthData(userData);

          markPostAuthRedirecting();
          navigate(getDashboardRouteByRole(userData?.role), { replace: true });
        } catch (error) {
          console.error('OAuth finalization failed:', error);
          clearPostAuthRedirecting();
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          sessionStorage.setItem('oauth_error_message', 'Failed to establish a secure session. Please sign in again.');
          navigate('/?auth_modal=open', { replace: true });
        }
      } else {
        // No valid oauth state found, go back home
        clearPostAuthRedirecting();
        navigate('/', { replace: true });
      }
    };

    handleCallback();
  }, [navigate, setAuthData]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-900">
      <div className="text-center animate-in fade-in duration-500">
        <LoadingSpinner size="lg" />
        <p className="mt-4 text-amber-100 font-medium tracking-wide">
          Completing authentication...
        </p>
        <p className="mt-2 text-amber-100/60 text-sm">
          Securing your session and preparing your dashboard.
        </p>
      </div>
    </div>
  );
};

export default AuthCallback;
