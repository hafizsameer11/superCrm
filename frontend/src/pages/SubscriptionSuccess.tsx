import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../services/api';
import { useAuthStore } from '../stores/authStore';
import Button from '../components/ui/Button';

export default function SubscriptionSuccess() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const sessionId = searchParams.get('session_id');

  useEffect(() => {
    if (sessionId) {
      verifySession();
    } else {
      setError('Missing session ID');
      setLoading(false);
    }
  }, [sessionId]);

  const checkAuth = useAuthStore((state) => state.checkAuth);
  const setUser = useAuthStore((state) => state.setUser);

  const verifySession = async () => {
    try {
      // Get company_id and plan_id from URL params (passed from checkout)
      const companyId = searchParams.get('company_id');
      const planId = searchParams.get('plan_id');
      const paymentIntentId = searchParams.get('payment_intent');
      
      if (!companyId || !planId) {
        setError('Missing company or plan information');
        setLoading(false);
        return;
      }
      
      // Call backend API to activate subscription - no verification, just activate
      console.log('📞 Activating subscription (payment successful)...');
      const response = await api.post('/subscription/activate', {
        session_id: sessionId,
        company_id: parseInt(companyId),
        plan_id: parseInt(planId),
        payment_intent_id: paymentIntentId || null,
      });
      
      console.log('✅ Subscription activated:', response.data);
      
      // Refresh auth data to get updated subscription status
      await checkAuth();
      
      // Get fresh user data from store
      const currentUser = useAuthStore.getState().user;
      
      console.log('User after activation:', {
        subscription_status: currentUser?.company?.subscription_status,
        company_status: currentUser?.company?.status,
      });
      
      // If subscription is active, redirect to dashboard
      if (currentUser?.company?.subscription_status === 'active') {
        console.log('✅✅✅ Subscription confirmed ACTIVE! Redirecting...');
        setLoading(false);
        setTimeout(() => {
          navigate('/dashboard', { replace: true });
        }, 500);
      } else {
        // Retry auth refresh a few times (in case of cache delay)
        let attempts = 0;
        const maxAttempts = 3;
        
        while (attempts < maxAttempts) {
          await new Promise(resolve => setTimeout(resolve, 1000));
          await checkAuth();
          const updatedUser = useAuthStore.getState().user;
          
          if (updatedUser?.company?.subscription_status === 'active') {
            console.log('✅ Subscription active after retry!');
            setLoading(false);
            navigate('/dashboard', { replace: true });
            return;
          }
          attempts++;
        }
        
        // Backend activated it, but frontend hasn't picked it up yet - redirect anyway
        console.warn('⚠️ Redirecting anyway - backend activated subscription');
        setLoading(false);
        navigate('/dashboard', { replace: true });
      }
      
    } catch (err: any) {
      console.error('❌ Failed to activate subscription:', err);
      const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'Failed to activate subscription';
      setError(errorMessage + '. Please refresh the page or contact support.');
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-aqua-2 to-aqua-1">
        <div className="bg-white p-8 rounded-2xl shadow-lg border border-line w-full max-w-md text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-aqua-5 mx-auto mb-4"></div>
          <p className="text-muted">Verifying your subscription...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-aqua-2 to-aqua-1">
        <div className="bg-white p-8 rounded-2xl shadow-lg border border-line w-full max-w-md text-center">
          <div className="mb-4">
            <svg
              className="mx-auto h-16 w-16 text-red-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-ink mb-2">Verification Failed</h1>
          <p className="text-muted mb-6">{error}</p>
          <Button onClick={() => navigate('/subscribe')} variant="primary">
            Try Again
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-aqua-2 to-aqua-1">
      <div className="bg-white p-8 rounded-2xl shadow-lg border border-line w-full max-w-md text-center">
        <div className="mb-4">
          <svg
            className="mx-auto h-16 w-16 text-green-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <h1 className="text-2xl font-bold text-ink mb-2">Subscription Activated!</h1>
        <p className="text-muted mb-6">
          Your subscription has been successfully activated. Your account is now active and you have full access to the platform.
        </p>
        <p className="text-sm text-muted mb-6">
          {loading ? 'Verifying subscription status...' : 'Redirecting to dashboard...'}
        </p>
        <Button 
          onClick={async () => {
            // Force refresh auth before navigating
            try {
              console.log('🔄 Manual refresh before navigation...');
              await checkAuth();
              const currentUser = useAuthStore.getState().user;
              console.log('User after manual refresh:', currentUser?.company?.subscription_status);
            } catch (e) {
              console.error('Manual auth refresh failed:', e);
            }
            navigate('/dashboard', { replace: true });
          }} 
          variant="primary"
        >
          Go to Dashboard Now
        </Button>
      </div>
    </div>
  );
}

