import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import LoadingSpinner from '../components/ui/LoadingSpinner';

/**
 * AuthCallback Component
 * Dedicated page to handle OAuth redirects from the backend.
 * Parses tokens/messages from URL hash/query and redirects user to the final destination.
 */
const AuthCallback = () => {
  const navigate = useNavigate();

  useEffect(() => {
    const handleCallback = async () => {
      // Parse parameters from hash or search
      const hashRaw = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
      const hashParams = new URLSearchParams(hashRaw);
      const searchParams = new URLSearchParams(window.location.search);
      
      // Values can be in hash (preferred) or query string
      const oauthStatus = hashParams.get('oauth') || searchParams.get('oauth');
      const token = hashParams.get('token') || searchParams.get('token');
      const message = hashParams.get('message') || searchParams.get('message');
      const tab = hashParams.get('tab') || searchParams.get('tab');
      const registrationStatus = hashParams.get('registration') || searchParams.get('registration');

      // 1. Handle registration confirmation (email link)
      if (registrationStatus === 'confirmed') {
        sessionStorage.setItem('oauth_success_message', 'Registration confirmed. You can now sign in.');
        navigate('/?auth_modal=open', { replace: true });
        return;
      }

      // 2. Handle pending email verification (Google registration)
      if (oauthStatus === 'pending_verification') {
        if (message) sessionStorage.setItem('oauth_pending_message', message);
        navigate('/?auth_modal=open&tab=login', { replace: true });
        return;
      }

      // 3. Handle OAuth Errors
      if (oauthStatus === 'error') {
        if (message) sessionStorage.setItem('oauth_error_message', message);
        if (tab) sessionStorage.setItem('oauth_error_tab', tab);
        navigate('/?auth_modal=open', { replace: true });
        return;
      }

      // 4. Handle OAuth Success
      if (oauthStatus === 'success' && token) {
        try {
          // Initialize auth headers and storage
          localStorage.setItem('token', token);
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          // Fetch fresh user data to ensure role and profile status are synced
          const userResp = await axios.get('/api/user');
          const userData = userResp.data?.data || userResp.data;
          
          localStorage.setItem('user', JSON.stringify(userData));
          
          // Successful login/register -> Dashboard
          // We use window.location.replace to trigger a full refresh and re-init AuthContext
          window.location.replace('/dashboard');
        } catch (error) {
          console.error('OAuth finalization failed:', error);
          
          if (error.response?.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            sessionStorage.setItem('oauth_error_message', 'Session invalid. Please sign in again.');
            navigate('/?auth_modal=open', { replace: true });
          } else {
             // Non-auth failures (network/timeout) - still proceed with token
             // AuthContext will handle background refreshing if needed
             window.location.replace('/dashboard');
          }
        }
      } else {
        // No valid oauth state found, go back home
        navigate('/', { replace: true });
      }
    };

    handleCallback();
  }, [navigate]);

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
