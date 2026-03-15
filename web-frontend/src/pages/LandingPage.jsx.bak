import { useState, useEffect, useRef } from 'react';
import { useTheme } from '../context/ThemeContext';
import logger from '../utils/logger';
import AuthTabsModal from '../components/auth/AuthTabsModal';
import StarRating from '../components/ui/StarRating';
import FeedbackThankYouModal from '../components/modals/FeedbackThankYouModal';
import FeedbackErrorModal from '../components/modals/FeedbackErrorModal';
import AllTestimonialsModal from '../components/modals/AllTestimonialsModal';
import { CheckCircleIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

const LandingPage = () => {
  const { isDarkMode, setIsDarkMode } = useTheme();
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
  const [feedbackEmail, setFeedbackEmail] = useState('');
  const [feedbackMessage, setFeedbackMessage] = useState('');
  const [feedbackRating, setFeedbackRating] = useState(0);
  const [isThankYouModalOpen, setIsThankYouModalOpen] = useState(false);
  const [isErrorModalOpen, setIsErrorModalOpen] = useState(false);
  const [errorModalContent, setErrorModalContent] = useState({ title: '', message: '', primaryAction: null });
  const [isFeedbackLoading, setIsFeedbackLoading] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [activeSection, setActiveSection] = useState('home');
  const [animatedStats, setAnimatedStats] = useState({
    totalAppointments: 0,
    totalUsers: 0,
    completedAppointments: 0,
    totalServices: 0
  });

  // Real data states
  const [services, setServices] = useState([]);
  const [stats, setStats] = useState({
    totalAppointments: 0,
    totalUsers: 0,
    completedAppointments: 0,
    totalServices: 0
  });
  const [testimonials, setTestimonials] = useState([]);
  const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });
  const [isAllTestimonialsModalOpen, setIsAllTestimonialsModalOpen] = useState(false);
  
  // Inline feedback success/error messages (non-modal)
  const [feedbackSuccessMessage, setFeedbackSuccessMessage] = useState('');
  const [feedbackLimitMessage, setFeedbackLimitMessage] = useState('');
  const [feedbackCategory, setFeedbackCategory] = useState('other');
  
  // Track if we've already animated to prevent re-animation on polling updates
  const hasAnimatedRef = useRef(false);

  // Theme colors are provided via CSS variables in `src/index.css`.
  // Note: Theme is now managed by ThemeContext and initialized in main.jsx

  const lightGradient = () => `linear-gradient(90deg, var(--primary), var(--secondary))`;
  const darkGradient = () => `linear-gradient(90deg,var(--accent),#D97706)`;
  const lightBgGradient = `linear-gradient(to bottom, var(--background), var(--surface))`;

  // Track mouse for parallax
  useEffect(() => {
    const handleMouseMove = (e) => {
      setMousePosition({
        x: (e.clientX - window.innerWidth / 2) * 0.01,
        y: (e.clientY - window.innerHeight / 2) * 0.01
      });
    };

    window.addEventListener('mousemove', handleMouseMove);
    return () => window.removeEventListener('mousemove', handleMouseMove);
  }, []);

  // Animate stats counter - Always update animated stats when real stats change
  useEffect(() => {
    if (stats.totalAppointments > 0 || stats.totalUsers > 0 || stats.completedAppointments > 0 || stats.totalServices > 0) {
      // If this is the first time with data, animate from 0
      if (!hasAnimatedRef.current) {
        hasAnimatedRef.current = true;
        
        const duration = 2000; // 2 seconds
        const steps = 60;
        const interval = duration / steps;

        const animateValue = (start, end, setter, key) => {
          let current = start;
          const increment = (end - start) / steps;
          const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
              current = end;
              clearInterval(timer);
            }
            setter(prev => ({ ...prev, [key]: Math.floor(current) }));
          }, interval);
          
          return () => clearInterval(timer);
        };

        const cleanups = [
          animateValue(0, stats.totalAppointments, setAnimatedStats, 'totalAppointments'),
          animateValue(0, stats.totalUsers, setAnimatedStats, 'totalUsers'),
          animateValue(0, stats.completedAppointments, setAnimatedStats, 'completedAppointments'),
          animateValue(0, stats.totalServices, setAnimatedStats, 'totalServices')
        ];
        
        return () => cleanups.forEach(cleanup => cleanup && cleanup());
      } else {
        // On subsequent updates (polling), just update without animation
        setAnimatedStats({
          totalAppointments: stats.totalAppointments,
          totalUsers: stats.totalUsers,
          completedAppointments: stats.completedAppointments,
          totalServices: stats.totalServices
        });
      }
    }
  }, [stats]); // Depend on stats to trigger animation when they load

  // Single consolidated polling for all stats - avoid multiple conflicting intervals
  useEffect(() => {
    let isMounted = true;
    
    const fetchAllData = async () => {
      if (!isMounted) return;
      
      try {
        // Fetch stats from the single source of truth
        const statsResponse = await axios.get('/api/stats/summary', { timeout: 3000 });
        
        if (isMounted && statsResponse.data?.data) {
          const apiStats = statsResponse.data.data;
          // Use stats from API directly - this is the authoritative source
          setStats({
            totalAppointments: apiStats.totalAppointments || 0,
            totalUsers: apiStats.totalUsers || 0,
            completedAppointments: apiStats.completedAppointments || 0,
            totalServices: apiStats.totalServices || 0
          });
        }
      } catch (err) {
        // On error, keep existing stats - don't reset to defaults on network blips
        logger.debug('Stats fetch failed, keeping existing values');
      }
    };

    // Fetch immediately on mount
    fetchAllData();

    // Poll every 30 seconds (not 5s - reduces load and flicker)
    const interval = setInterval(fetchAllData, 30000);

    return () => {
      isMounted = false;
      clearInterval(interval);
    };
  }, []);

  // Active section observer
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setActiveSection(entry.target.id);
          }
        });
      },
      { threshold: 0.3 }
    );

    return () => observer.disconnect();
  }, []);

  // Fetch services and testimonials on component mount (stats handled by separate polling)
  useEffect(() => {
    const fetchServicesAndTestimonials = async () => {
      // Fetch services
      try {
        const servicesResponse = await axios.get('/api/services', { timeout: 3000 });
        if (servicesResponse.data?.data && Array.isArray(servicesResponse.data.data)) {
          setServices(servicesResponse.data.data.slice(0, 4));
        }
      } catch (err) {
        logger.debug('Services API unavailable');
        setServices([]);
      }

      // Fetch testimonials
      try {
        const feedbackResponse = await axios.get('/api/testimonials/feedbacks?limit=3', { timeout: 3000 });
        if (feedbackResponse.data?.data && Array.isArray(feedbackResponse.data.data) && feedbackResponse.data.data.length > 0) {
          const testimonialData = feedbackResponse.data.data.map((feedback, idx) => ({
            id: feedback.id,
            clientName: feedback.privacy_safe_username || feedback.email?.split('@')[0] || `Client ${idx + 1}`,
            maskedInitial: feedback.masked_initial || feedback.privacy_safe_username?.charAt(0).toUpperCase() || 'U',
            serviceType: feedback.feedback_type?.replace(/_/g, ' ') || 'Feedback',
            rating: feedback.rating || 5,
            message: feedback.message
          }));
          setTestimonials(testimonialData);
        } else {
          // Fallback to completed appointments
          const appointmentsResponse = await axios.get('/api/testimonials/completed-appointments?limit=3', { timeout: 3000 });
          if (appointmentsResponse.data?.data && Array.isArray(appointmentsResponse.data.data)) {
            const testimonialData = appointmentsResponse.data.data.map((apt, idx) => ({
              id: apt.id,
              clientName: apt.user?.name || `Client ${idx + 1}`,
              serviceType: apt.type || 'Legal Service',
              rating: 5,
              message: apt.notes || `Successfully completed ${apt.type || 'appointment'}`
            }));
            setTestimonials(testimonialData);
          }
        }
      } catch (err) {
        logger.debug('Testimonials API unavailable');
        setTestimonials([]);
      }
    };

    fetchServicesAndTestimonials();
  }, []);

  const handleSendFeedback = async (e) => {
    e.preventDefault();
    
    if (!feedbackEmail || !feedbackMessage || feedbackRating === 0) {
      logger.warn('Feedback form incomplete');
      alert('Please complete all feedback fields before submitting.');
      return;
    }

    if (feedbackMessage.trim().length < 10) {
      logger.warn('Feedback message too short');
      alert('Feedback must be at least 10 characters long.');
      return;
    }

    setIsFeedbackLoading(true);
    setFeedbackSuccessMessage('');
    setFeedbackLimitMessage('');
    
    try {
      const response = await axios.post('/api/feedback', {
        email: feedbackEmail,
        message: feedbackMessage,
        rating: feedbackRating,
        feedback_type: feedbackCategory
      });

      logger.info('Feedback sent successfully', { response });
      
      // Show inline success message instead of modal
      setFeedbackSuccessMessage('✅ Thank you! Your feedback has been received. A confirmation email has been sent to your inbox.');
      
      // Reset form
      setFeedbackEmail('');
      setFeedbackMessage('');
      setFeedbackRating(0);
      setFeedbackCategory('other');
      
      // Auto-dismiss success message after 8 seconds
      setTimeout(() => {
        setFeedbackSuccessMessage('');
      }, 8000);

    } catch (error) {
      logger.error('Failed to send feedback', { error });
      const resp = error.response?.data || {};

      // Handle known errors with inline messages instead of modals
      if (resp.error === 'email_not_registered') {
        setFeedbackLimitMessage(
          '⚠️ The email you provided is not registered. Please create an account or log in to submit feedback.'
        );
        setTimeout(() => setFeedbackLimitMessage(''), 8000);
      } else if (resp.error === 'rate_limit_reached') {
        const nextAvailable = resp.data?.next_available_at;
        if (nextAvailable) {
          const date = new Date(nextAvailable);
          const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
          const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
          const formattedDate = `${date.toLocaleDateString('en-US', dateOptions)} at ${date.toLocaleTimeString('en-US', timeOptions)}`;
          setFeedbackLimitMessage(`⚠️ You've reached your feedback limit. You can submit feedback again on ${formattedDate}.`);
        } else {
          setFeedbackLimitMessage('⚠️ You have reached your feedback submission limit. Please try again later.');
        }
        setTimeout(() => setFeedbackLimitMessage(''), 8000);
      } else if (resp.error === 'profanity_detected') {
        setFeedbackLimitMessage('❌ Your feedback contains disallowed language. Please edit and try again.');
        setTimeout(() => setFeedbackLimitMessage(''), 8000);
      } else if (resp.error === 'duplicate_feedback') {
        setFeedbackLimitMessage('⚠️ It looks like you submitted similar feedback recently. Please try again later.');
        setTimeout(() => setFeedbackLimitMessage(''), 8000);
      } else {
        alert('Failed to send feedback. Please try again.');
      }
    } finally {
      setIsFeedbackLoading(false);
    }
  };

  const scrollToSection = (sectionId) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
      setIsMobileMenuOpen(false);
      setActiveSection(sectionId);
    }
  };

  // Transform real services into features
  const features = services.length > 0 
    ? services.map((service, idx) => ({
        title: service.name || 'Legal Service',
        description: service.description || 'Professional legal service tailored to your needs',
        icon: ['⚖️', '📋', '🔐', '✅'][idx % 4]
      }))
    : [
        {
          title: "Instant Booking",
          description: "Schedule appointments in seconds with our intuitive booking system",
          icon: "⏱️"
        },
        {
          title: "Document Security",
          description: "Military-grade encryption for all your sensitive legal documents",
          icon: "🛡️"
        },
        {
          title: "Real-time Tracking",
          description: "Track your appointment status and receive live updates",
          icon: "📱"
        },
        {
          title: "Available Always",
          description: "Book and manage appointments anytime, anywhere",
          icon: "🌙"
        }
      ];

  const processSteps = [
    {
      step: "01",
      title: "Register Account",
      description: "Create your secure account in under 2 minutes"
    },
    {
      step: "02",
      title: "Verify Identity",
      description: "Complete quick identity verification process"
    },
    {
      step: "03",
      title: "Book Appointment",
      description: "Choose your preferred date and time slot"
    },
    {
      step: "04",
      title: "Get Notarized",
      description: "Complete your notarization seamlessly"
    }
  ];

  // Dynamic stats display
  const displayStats = [
    { 
      number: animatedStats.totalAppointments > 0 ? `${animatedStats.totalAppointments}+` : "—", 
      label: "Total Appointments" 
    },
    { 
      number: animatedStats.totalUsers > 0 ? `${animatedStats.totalUsers}+` : "—", 
      label: "Active Users" 
    },
    { 
      number: animatedStats.completedAppointments > 0 ? `${animatedStats.completedAppointments}+` : "—", 
      label: "Completed Services" 
    },
    { 
      number: animatedStats.totalServices > 0 ? `${animatedStats.totalServices}` : "—", 
      label: "Available Services" 
    }
  ];

  return (
    <div className={`min-h-screen overflow-hidden transition-colors duration-500 ${
      isDarkMode 
        ? 'bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950' 
        : ''
    }`} style={!isDarkMode ? { background: lightBgGradient } : {}}>
      {/* Animated Background Elements */}
      <div className="fixed inset-0 pointer-events-none">
        <div 
          className={`absolute top-1/4 left-1/4 w-64 h-64 ${isDarkMode ? 'bg-amber-500/5' : 'bg-blue-500/5'} rounded-full blur-3xl animate-pulse`}
          style={{ 
            transform: `translate(${mousePosition.x}px, ${mousePosition.y}px)`,
            animationDelay: '0.5s'
          }}
        />
        <div 
          className={`absolute bottom-1/4 right-1/4 w-96 h-96 ${isDarkMode ? 'bg-amber-600/3' : 'bg-blue-600/3'} rounded-full blur-3xl animate-pulse`}
          style={{ 
            transform: `translate(${-mousePosition.x * 2}px, ${-mousePosition.y * 2}px)`,
            animationDelay: '1s'
          }}
        />
      </div>

      {/* Scroll Progress Bar */}
       <div className="fixed top-0 left-0 right-0 h-0.5 z-50 origin-left scale-x-0" 
         style={{ animation: 'progress linear forwards', animationTimeline: 'scroll(root)', background: isDarkMode ? darkGradient() : lightGradient() }} />

      {/* Navigation */}
      <nav className={`fixed w-full backdrop-blur-md z-50 shadow-sm transition-all duration-300 ${
        isDarkMode
          ? 'bg-gray-900/40 border-b border-gray-800/20'
          : 'bg-white/60 border-b border-blue-100'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="flex justify-between items-center h-16 md:h-20">
            {/* Logo with hover animation */}
            <div 
              className="flex items-center space-x-2.5 group cursor-pointer"
              onClick={() => scrollToSection('home')}
            >
              <img 
                src="/logo.jpg" 
                alt="Logo" 
                className="h-8 md:h-10 w-auto object-contain rounded shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3" 
              />
              <span className="text-lg font-bold tracking-tight transition-colors duration-300" style={{ color: isDarkMode ? undefined : 'var(--secondary)' }}>
                <span className="text-lg md:text-2xl font-bold tracking-tight transition-colors duration-300" style={{ color: isDarkMode ? 'rgba(255,255,255,0.95)' : 'var(--secondary)', textTransform: !isDarkMode ? 'uppercase' : undefined, opacity: 0.95, letterSpacing: '1px' }}>
                  LEGALEASE
                </span>
              </span>
            </div>

            {/* Desktop Menu */}
            <div className="hidden md:flex items-center space-x-6">
              {['Home', 'Services', 'How It Works', 'Reviews'].map((item) => {
                const sectionId = item.toLowerCase().replace(/\s+/g, '');
                return (
                  <button
                    key={item}
                    onClick={() => scrollToSection(sectionId)}
                    className={`relative text-sm transition-all duration-300 group ${
                      activeSection === sectionId 
                        ? (isDarkMode ? 'text-amber-300 font-semibold' : 'font-semibold') 
                        : isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-slate-600 hover:text-blue-600'
                    }`} style={!isDarkMode && activeSection === sectionId ? { color: 'var(--primary)' } : {}}
                  >
                    <span className="relative z-10">{item}</span>
                    {activeSection === sectionId && (
                      <span className="absolute -bottom-1 left-0 w-full h-0.5 animate-pulse" style={{ background: isDarkMode ? undefined : lightGradient() }} />
                    )}
                    <span className={`absolute inset-0 scale-0 group-hover:scale-100 rounded transition-transform duration-300 ${
                      isDarkMode ? 'bg-amber-500/10' : 'bg-blue-200'
                    }`} />
                  </button>
                );
              })}
            </div>

            {/* Auth Buttons */}
            <div className="hidden md:flex items-center space-x-3">
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className={`text-gray-400 font-medium text-sm transition-all duration-300 hover:scale-105 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-600'}`}
              >
                Sign In
              </button>
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className={`px-5 py-2 rounded-lg font-semibold hover:scale-105 active:scale-95 relative overflow-hidden group transition-all duration-300`}
                style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: '#ffffff' }}
              >
                <span className="relative z-10">Get Started</span>
                <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300" style={{ background: isDarkMode ? 'linear-gradient(90deg,#C2410C,#92400E)' : lightGradient() }} />
              </button>
            </div>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 rounded hover:bg-gray-800 transition-all duration-300 hover:scale-110"
            >
              <svg 
                className={`w-5 h-5 text-gray-400 transition-transform duration-300 ${isMobileMenuOpen ? 'rotate-90' : ''}`} 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Menu with animation */}
        <div 
          className={`md:hidden ${isDarkMode ? 'bg-gray-800/85 border-t border-gray-800/20' : 'bg-white/80 border-t border-blue-100'} backdrop-blur-sm overflow-hidden transition-all duration-300 ${
            isMobileMenuOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
          }`}
        >
          <div className="px-4 py-3 space-y-2">
            {['Home', 'Services', 'How It Works', 'Reviews'].map((item, index) => {
              const sectionId = item.toLowerCase().replace(/\s+/g, '');
              return (
                <button
                  key={item}
                  onClick={() => scrollToSection(sectionId)}
                  className={`block w-full text-left ${isDarkMode ? 'text-gray-300' : 'text-slate-700'} font-medium text-sm py-2 transition-all duration-300 transform hover:translate-x-2 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-400'} ${
                    activeSection === sectionId ? (isDarkMode ? 'text-amber-300' : 'text-blue-400') : ''
                  }`}
                  style={{ animationDelay: `${index * 50}ms` }}
                >
                  {item}
                </button>
              );
            })}
            <div className="pt-3 space-y-2 border-t border-gray-800">
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className={`block w-full text-left ${isDarkMode ? 'text-gray-300' : 'text-slate-700'} font-medium text-sm py-2 transition-colors duration-300 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-400'}`}
              >
                Sign In
              </button>
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className={`w-full py-2.5 rounded-lg font-semibold text-sm transition-all duration-300 hover:scale-105`}
                style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: isDarkMode ? '#fff' : 'var(--text-primary)' }}
              >
                Get Started
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section id="home" className="pt-20 pb-12 md:pt-24 md:pb-16 relative">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="grid lg:grid-cols-2 gap-10 items-center">
            {/* Hero Content */}
            <div className="text-center lg:text-left" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className={`inline-block mb-5 px-3 py-1.5 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : ''}`} style={!isDarkMode ? { backgroundColor: 'var(--secondary-10)', border: '1px solid var(--secondary-20)' } : {}}>
                <span className="text-xs font-semibold tracking-wide" style={!isDarkMode ? { color: 'var(--secondary)', textTransform: 'uppercase', letterSpacing: '0.6px' } : {}}>{'✨ Trusted Legal Notary Service'}</span>
              </div>
              <h1 className={`text-4xl md:text-5xl lg:text-6xl font-bold leading-snug mb-5 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                Professional Notary
                <span style={isDarkMode ? { background: 'linear-gradient(90deg,#FCD34D,var(--accent))', WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent', display: 'inline-block', fontWeight: 700 } : { color: 'var(--primary)' }}>
                  Services Made Easy
                </span>
              </h1>
              <p className={`text-base md:text-lg mb-7 leading-relaxed max-w-xl ${
                isDarkMode ? 'text-gray-400' : 'text-slate-600'
              }`}>
                Get your documents notarized online in minutes. Secure, convenient, and professional. No hidden fees, no complicated process.
              </p>
              <div className="flex flex-row gap-2 sm:gap-3 justify-center lg:justify-start items-center flex-wrap">
                <button
                  onClick={() => setIsAuthModalOpen(true)}
                  className={`px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:scale-105 active:scale-95 group relative overflow-hidden transition-all duration-300 text-white text-sm sm:text-base`}
                  style={{ background: isDarkMode ? darkGradient() : lightGradient() }}
                >
                  <span className="relative z-10">Book Appointment</span>
                  <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300" style={{ background: isDarkMode ? 'linear-gradient(90deg,#C2410C,#92400E)' : lightGradient() }} />
                </button>
                <button
                  onClick={() => scrollToSection('howitworks')}
                  className="border px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold transition-all duration-300 hover:scale-105 active:scale-95 text-sm sm:text-base"
                  style={!isDarkMode ? { borderColor: 'var(--borders)', color: 'var(--secondary)', backgroundColor: 'transparent' } : {}}
                >
                  Learn More
                </button>
                
                {/* Theme Toggle */}
                <button
                  onClick={() => setIsDarkMode(!isDarkMode)}
                  className={`p-2.5 rounded-lg transition-all duration-300 border`}
                  style={!isDarkMode ? { backgroundColor: 'var(--surface)', borderColor: 'var(--borders)', color: 'var(--secondary)' } : {}}
                  title={isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
                  aria-label="Toggle theme"
                >
                  {isDarkMode ? (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                  ) : (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clipRule="evenodd" />
                    </svg>
                  )}
                </button>
              </div>

              {/* Trust Indicators */}
              <div className={`mt-10 flex flex-wrap gap-6 pt-6 border-t justify-center lg:justify-start ${isDarkMode ? 'border-gray-800' : 'border-blue-300'}`} style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
                <div className="group">
                  <p className={`text-xl font-bold transition-all duration-300 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : ''}`} style={!isDarkMode ? { color: 'var(--primary)' } : {}}>
                    {stats.totalAppointments || '500+'}
                  </p>
                  <p className={`text-xs group-hover transition-colors ${
                    isDarkMode ? 'text-gray-500 group-hover:text-gray-400' : 'text-slate-500 group-hover:text-slate-600'
                  }`}>Documents Notarized</p>
                </div>
                <div className="group">
                  <p className={`text-xl font-bold transition-all duration-300 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : ''}`} style={!isDarkMode ? { color: 'var(--primary)' } : {}}>
                    {stats.totalUsers || '1000+'}
                  </p>
                  <p className={`text-xs group-hover transition-colors ${
                    isDarkMode ? 'text-gray-500 group-hover:text-gray-400' : 'text-slate-500 group-hover:text-slate-600'
                  }`}>Satisfied Clients</p>
                </div>
                <div className="group">
                  <p className={`text-xl font-bold transition-all duration-300 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : ''}`} style={!isDarkMode ? { color: 'var(--primary)' } : {}}>8/5</p>
                  <p className={`text-xs group-hover transition-colors ${
                    isDarkMode ? 'text-gray-500 group-hover:text-gray-400' : 'text-slate-500 group-hover:text-slate-600'
                  }`}>Available Anytime</p>
                </div>
              </div>
            </div>

            {/* Hero Visual with hover effect */}
            <div className="relative hidden lg:block" style={{ animation: 'float 6s ease-in-out infinite' }}>
              <div className={`rounded-2xl p-6 overflow-hidden group transition-all duration-500 ${isDarkMode ? 'bg-gradient-to-br from-gray-800 to-gray-700/90 border border-gray-700 shadow-lg hover:border-amber-500/30' : 'bg-white border border-blue-100 shadow-sm hover:border-blue-500/30'}`}>
                <div className={`aspect-square rounded-xl flex items-center justify-center overflow-hidden transition-transform duration-700 group-hover:scale-105 ${isDarkMode ? 'bg-gradient-to-br from-amber-500/10 to-amber-600/10 border border-amber-500/10' : 'bg-white/50 border border-blue-50'}`}>
                  <img 
                    src="/hero.webp" 
                    alt="Legal Notary Services" 
                    className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" 
                  />
                </div>
                <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 ${isDarkMode ? 'bg-gradient-to-t from-amber-500/5 to-transparent' : 'bg-gradient-to-t from-blue-500/5 to-transparent'}`} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Stats Section with animated counters */}
      <section className={`py-12 transition-colors duration-500 ${
        isDarkMode
          ? 'bg-gray-900/50 border-y border-gray-800/50'
          : 'bg-gray-50 border-y border-gray-200'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {displayStats.map((stat, index) => (
              <div 
                key={index} 
                className="text-center group"
                style={{ animation: `fadeInUp 0.6s ease-out ${index * 100}ms forwards` }}
              >
                <div className={`text-2xl md:text-3xl font-bold mb-1 transition-all duration-300 group-hover:scale-110`} style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
                  {stat.number}
                </div>
                <div className="text-sm transition-colors" style={isDarkMode ? { color: '#9CA3AF' } : { color: 'var(--text-secondary)' }}>
                  {stat.label}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Services Section */}
      <section id="services" className="py-16">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className="inline-block mb-3 px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full animate-pulse">
              <span className="text-blue-400 text-xs font-semibold">Our Services</span>
            </div>
            <h2 className="text-2xl md:text-4xl font-bold mb-4" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
              Complete Notary Solutions
            </h2>
            <p className="text-sm md:text-base text-gray-400 max-w-2xl mx-auto">
              Professional notarization services tailored to your legal needs
            </p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {features.map((feature, index) => (
              <div 
                key={index} 
                className={`rounded-lg p-4 relative overflow-hidden transition-all duration-300 ${isDarkMode ? 'bg-gray-900/50 border border-gray-800 hover:border-blue-500/30' : 'bg-white border border-blue-100 hover:border-blue-200'}`}
              >
                <div className="relative z-10">
                  <div className="text-3xl md:text-4xl mb-2 md:mb-4">
                    {feature.icon}
                  </div>
                      <h3 className={`text-sm md:text-lg font-bold mb-1 md:mb-2 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                        {feature.title}
                      </h3>
                      <p className={`text-xs md:text-sm leading-relaxed ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>
                        {feature.description}
                      </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works Section */}
      <section id="howitworks" className={`py-16 transition-colors duration-500 ${
        isDarkMode
          ? 'bg-gray-900/50 border-y border-gray-800/50'
          : 'bg-gray-50 border-y border-gray-200'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className="inline-block mb-3 px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full animate-pulse">
              <span className="text-blue-400 text-xs font-semibold">How It Works</span>
            </div>
            <h2 className="text-3xl md:text-4xl font-bold mb-4" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
              Simple 4-Step Process
            </h2>
            <p className="text-gray-400 max-w-2xl mx-auto">
              Get your documents notarized in minutes with our streamlined process
            </p>
          </div>

          <div className="grid lg:grid-cols-2 gap-12 items-center">
            {/* Process Steps */}
            <div className="space-y-6">
              {processSteps.map((step, index) => (
                <div 
                  key={index} 
                  className="flex gap-4 items-start group"
                  style={{ animation: `fadeInRight 0.6s ease-out ${index * 200}ms forwards` }}
                >
                  <div className="flex-shrink-0 relative">
                    <div className={`flex items-center justify-center h-14 w-14 rounded-full text-white shadow transition-all duration-300 group-hover:scale-110 ${isDarkMode ? 'bg-gradient-to-br from-amber-500 to-amber-600 border border-amber-400/30 group-hover:shadow-lg group-hover:shadow-amber-500/30' : 'bg-gradient-to-br from-blue-500 to-blue-600 border border-blue-400/30 group-hover:shadow-lg group-hover:shadow-blue-500/30'}`}>
                      <span className="text-lg font-bold">{step.step}</span>
                    </div>
                    {index < processSteps.length - 1 && (
                      <div className={`${isDarkMode ? 'absolute top-full left-1/2 w-0.5 h-6 bg-gradient-to-b from-amber-500 to-amber-600/50 animate-pulse' : 'absolute top-full left-1/2 w-0.5 h-6 bg-gradient-to-b from-blue-500 to-blue-600/50 animate-pulse'}`} />
                    )}
                  </div>
                  <div className="group-hover:translate-x-2 transition-transform duration-300">
                    <h3 className={`text-xl font-bold mb-1 transition-colors ${isDarkMode ? 'text-white group-hover:text-amber-300' : 'text-slate-900 group-hover:text-blue-300'}`}>
                      {step.title}
                    </h3>
                    <p className={`text-sm transition-colors ${isDarkMode ? 'text-gray-400 group-hover:text-gray-300' : 'text-gray-700 group-hover:text-gray-900'}`}>
                      {step.description}
                    </p>
                  </div>
                </div>
              ))}
            </div>

            {/* Process Visual */}
            <div className="relative hidden lg:block" style={{ animation: 'fadeInLeft 0.6s ease-out forwards' }}>
              <div className={`bg-gradient-to-br from-gray-800 to-gray-700/90 border border-gray-700 rounded-2xl shadow p-6 group transition-all duration-500 ${isDarkMode ? 'hover:border-amber-500/30' : 'hover:border-blue-500/30'}`}>
                <div className="aspect-square bg-gradient-to-br from-gray-800 to-gray-700 border border-blue-500/10 rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform duration-700">
                  <div className="text-center p-4">
                    <div className="text-5xl mb-3 animate-bounce">📋</div>
                    <p className="text-gray-300 font-semibold text-sm">Secure Document Processing</p>
                  </div>
                </div>
                <div className={`${isDarkMode ? 'absolute -inset-2 bg-gradient-to-r from-amber-500/0 to-amber-600/0 group-hover:from-amber-500/10 group-hover:to-amber-600/10' : 'absolute -inset-2 bg-gradient-to-r from-blue-500/0 to-blue-600/0 group-hover:from-blue-500/10 group-hover:to-blue-600/10'} rounded-2xl blur-xl transition-all duration-500 opacity-0 group-hover:opacity-100`} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Reviews/Testimonials Section */}
      <section id="reviews" className="py-16">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className={`inline-block mb-3 px-3 py-1 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : ''}`} style={!isDarkMode ? { backgroundColor: 'var(--secondary-10)', border: '1px solid var(--secondary-20)' } : {}}>
                <span className="text-xs font-semibold" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>Testimonials</span>
              </div>
            <h2 className={`text-3xl md:text-4xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
              Trusted by Hundreds
            </h2>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-900'} max-w-2xl mx-auto` }>
              Real feedback from clients who have experienced our professional notary service
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {testimonials.length > 0 ? (
              // Real testimonials
              testimonials.map((item, index) => (
                <div 
                  key={item.id} 
                  className={`rounded-xl p-6 transition-all duration-500 hover:scale-105 group ${isDarkMode ? 'bg-gray-900/50 border border-gray-800 hover:border-amber-500/30' : 'bg-white border border-blue-100 hover:border-blue-200'}`}
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}
                >
                  <div className="flex items-center mb-4">
                    <div className={`w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3`} style={!isDarkMode ? { background: lightGradient(), border: '1px solid var(--secondary-20)', color: 'var(--text-primary)' } : {}}>
                      {item.clientName.charAt(0).toUpperCase()}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-white text-sm transition-colors" style={!isDarkMode ? { color: 'var(--primary)' } : {}}>
                        {item.clientName}
                      </div>
                      <div className="text-xs animate-pulse" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>
                        {'★'.repeat(item.rating)}
                      </div>
                    </div>
                  </div>
                  <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-900'} text-sm mb-3 leading-relaxed transition-colors` }>
                    "{item.message || `Successfully completed ${item.serviceType}`}"
                  </p>
                  <span className="inline-block text-xs font-semibold px-2 py-1 rounded border transition-colors" style={!isDarkMode ? { color: 'var(--secondary)', backgroundColor: 'var(--secondary-10)', borderColor: 'var(--secondary-20)' } : {}}>
                    {item.serviceType}
                  </span>
                </div>
              ))
            ) : (
              // Fallback testimonials
              [1, 2, 3].map((item, index) => (
                <div 
                  key={item} 
                  className={`rounded-xl p-6 transition-all duration-500 hover:scale-105 group ${isDarkMode ? 'bg-gray-900/50 border border-gray-800 hover:border-blue-500/30' : 'bg-white border border-blue-100 hover:border-blue-200'}`}
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}
                >
                  <div className="flex items-center mb-4">
                    <div className={`w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm border transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3`} style={!isDarkMode ? { background: lightGradient(), borderColor: 'var(--secondary-20)' } : {}}>
                      C{item}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-white text-sm transition-colors" style={!isDarkMode ? { color: 'var(--primary)' } : {}}>
                        Client {item}
                      </div>
                      <div className="text-xs" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>★★★★★</div>
                    </div>
                  </div>
                  <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-900'} text-sm leading-relaxed transition-colors` }>
                    "Professional and reliable notary service. Made my document process so much easier!"
                  </p>
                </div>
              ))
            )}
          </div>

          {/* See All Button */}
          {testimonials.length > 0 && (
            <div className="mt-8 flex justify-center">
              <button
                onClick={() => setIsAllTestimonialsModalOpen(true)}
                className={`px-6 py-3 rounded-lg font-semibold transition-all duration-300 hover:scale-105 active:scale-95 ${
                  isDarkMode
                    ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:shadow-lg hover:shadow-amber-500/30'
                    : 'bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:shadow-lg hover:shadow-blue-500/30'
                }`}
              >
                See All Testimonials
              </button>
            </div>
          )}
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-16 relative overflow-hidden" style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: isDarkMode ? '#fff' : 'var(--text-primary)' }}>
        {/* Fixed background pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0" style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E")`,
            backgroundSize: '100px 100px'
          }} />
        </div>

        <div className="max-w-4xl mx-auto px-4 sm:px-6 text-center relative z-10">
          <h2 className="text-3xl md:text-4xl font-bold mb-4" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            Ready to Get Started?
          </h2>
          <p className="text-gray-100 mb-8 max-w-xl mx-auto text-sm" style={{ animation: 'fadeInUp 0.6s ease-out 100ms forwards' }}>
            Join hundreds of satisfied clients who trust us with their important legal documents
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center" style={{ animation: 'fadeInUp 0.6s ease-out 200ms forwards' }}>
            <button
              onClick={() => setIsAuthModalOpen(true)}
              className="px-6 py-3 rounded-xl font-bold hover:bg-gray-50 transition-all duration-300 hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl relative overflow-hidden group"
              style={isDarkMode ? { background: darkGradient(), color: '#fff' } : { backgroundColor: 'var(--surface)', color: 'var(--primary)' }}
            >
              <span className="relative z-10">Start Now</span>
              <span className="absolute inset-0 bg-gray-100 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
            </button>
            <button
              onClick={() => scrollToSection('howitworks')}
              className={`px-6 py-3 rounded-xl font-bold hover:bg-white/10 transition-all duration-300 hover:scale-105 active:scale-95 ${isDarkMode ? 'border-white text-white' : 'border border-slate-300 text-slate-700'}`}
            >
              Learn More
            </button>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className={`pt-12 pb-6 transition-colors duration-500 ${
        isDarkMode
          ? 'bg-gray-950 text-gray-400 border-t border-gray-800'
          : 'bg-gray-100 text-slate-600 border-t border-gray-300'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8 mb-10 place-items-center">
            {/* Company Info */}
            <div className="col-span-2 md:col-span-1 text-center" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className="flex flex-col items-center space-y-2.5 mb-3 group">
                <img 
                  src="/logo.jpg" 
                  alt="Logo" 
                  className="h-10 w-auto object-contain rounded transition-transform duration-300 group-hover:scale-110" 
                />
                <span className="text-xl font-bold group-hover:text-amber-400 transition-colors" style={isDarkMode ? { color: '#ffffff' } : { color: '#7DD3FC', textTransform: 'uppercase', letterSpacing: '1px' }}>
                  LegalEase
                </span>
              </div>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.
              </p>
            </div>

            {/* Quick Links */}
            <div className="text-center" style={{ animation: 'fadeInUp 0.6s ease-out 100ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>Services</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {['Services', 'Notarization', 'Verification', 'Certification', 'Signing'].map((item, index) => (
                  <li key={item}>
                    <button 
                      className="hover:text-blue-400 transition-colors duration-300"
                      style={{ animationDelay: `${index * 50}ms` }}
                    >
                      {item}
                    </button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support */}
            <div className="text-center" style={{ animation: 'fadeInUp 0.6s ease-out 200ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>Support</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {['Help Center', 'Contact Us', 'FAQ'].map((item, index) => (
                  <li key={item}>
                    <button 
                      className="hover:text-blue-400 transition-colors duration-300"
                      style={{ animationDelay: `${index * 50}ms` }}
                    >
                      {item}
                    </button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Contact */}
            <div className="col-span-2 md:col-span-1 text-center" style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>Get Started</h4>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                Have questions? Our team is here to help.
              </p>
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className="w-full py-2 rounded-lg font-semibold text-sm hover:shadow-md transition-all duration-300 hover:scale-105 active:scale-95 relative overflow-hidden group"
                style={isDarkMode ? { background: darkGradient(), color: '#fff' } : { backgroundColor: 'var(--surface)', color: 'var(--secondary)' }}
              >
                <span className="relative z-10">Get Started</span>
                <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300" style={isDarkMode ? { background: 'linear-gradient(90deg,#C2410C,#92400E)' } : { backgroundColor: 'var(--surface)' }} />
              </button>
            </div>
          </div>

          {/* Feedback Form */}
          <div className={`backdrop-blur-sm border rounded-lg p-6 mb-8 transition-colors duration-300 ${
            isDarkMode 
              ? 'bg-gray-900/50 border-gray-800' 
              : 'bg-blue-50/80 border-blue-300'
          }`} style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <h3 className={`font-semibold mb-4 text-sm transition-colors duration-300 ${
              isDarkMode ? 'text-white' : 'text-gray-800'
            }`}>Share Your Feedback</h3>
            <form className="space-y-4" onSubmit={handleSendFeedback}>
              <div>
                <label className={`block text-xs font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Feedback Category
                </label>
                <select
                  value={feedbackCategory}
                  onChange={(e) => setFeedbackCategory(e.target.value)}
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 ${
                    isDarkMode
                      ? 'bg-gray-800 text-white border-gray-700 focus:border-amber-500'
                      : 'bg-white text-gray-800 border-blue-300 focus:border-blue-500'
                  }`}
                  disabled={isFeedbackLoading}
                >
                  <option value="service_quality">Service Quality</option>
                  <option value="speed">Speed</option>
                  <option value="support">Support</option>
                  <option value="system_experience">System Experience</option>
                  <option value="bug_report">Bug Report</option>
                  <option value="suggestion">Suggestion</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label className={`block text-xs font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Rate your experience
                </label>
                <div className="flex justify-start">
                  <StarRating 
                    value={feedbackRating} 
                    onChange={setFeedbackRating}
                    size="md"
                  />
                </div>
              </div>
              <div>
                <input
                  type="email"
                  placeholder="Your email"
                  value={feedbackEmail}
                  onChange={(e) => setFeedbackEmail(e.target.value)}
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 ${
                    isDarkMode
                      ? 'bg-gray-800 text-white border-gray-700 focus:border-amber-500 placeholder-gray-500'
                      : 'bg-white text-gray-800 border-blue-300 focus:border-blue-500 placeholder-gray-500'
                  }`}
                  required
                  disabled={isFeedbackLoading}
                />
              </div>
              <div>
                <textarea
                  placeholder="Your feedback or suggestions..."
                  value={feedbackMessage}
                  onChange={(e) => setFeedbackMessage(e.target.value)}
                  rows="3"
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 resize-none ${
                    isDarkMode
                      ? 'bg-gray-800 text-white border-gray-700 focus:border-amber-500 placeholder-gray-500'
                      : 'bg-white text-gray-800 border-blue-300 focus:border-blue-500 placeholder-gray-500'
                  }`}
                  required
                  disabled={isFeedbackLoading}
                />
              </div>
              <button
                type="submit"
                disabled={isFeedbackLoading || feedbackRating === 0}
                className={`w-full py-2 rounded-lg font-semibold text-sm transition-all duration-300 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed ${
                  isDarkMode
                    ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:shadow-md hover:shadow-amber-500/30'
                    : 'bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:shadow-md hover:shadow-blue-600/40'
                }`}
              >
                {isFeedbackLoading ? (
                  <div className="flex items-center justify-center">
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    Sending...
                  </div>
                ) : (
                  'Send Feedback'
                )}
              </button>
            </form>

            {/* Inline Success Message */}
            {feedbackSuccessMessage && (
              <div className={`mt-4 p-4 rounded-lg flex items-start gap-3 border ${
                isDarkMode
                  ? 'bg-green-500/10 border-green-500/30'
                  : 'bg-green-50 border-green-300'
              }`}>
                <CheckCircleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                <div className="flex-1">
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-green-400' : 'text-green-700'}`}>
                    {feedbackSuccessMessage}
                  </p>
                </div>
                <button
                  onClick={() => setFeedbackSuccessMessage('')}
                  className={`flex-shrink-0 ${isDarkMode ? 'text-green-400 hover:text-green-300' : 'text-green-600 hover:text-green-700'}`}
                >
                  <XMarkIcon className="h-4 w-4" />
                </button>
              </div>
            )}

            {/* Inline Limit/Error Message */}
            {feedbackLimitMessage && (
              <div className={`mt-4 p-4 rounded-lg flex items-start gap-3 border ${
                isDarkMode
                  ? 'bg-amber-500/10 border-amber-500/30'
                  : 'bg-amber-50 border-amber-300'
              }`}>
                <ExclamationTriangleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <div className="flex-1">
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-700'}`}>
                    {feedbackLimitMessage}
                  </p>
                </div>
                <button
                  onClick={() => setFeedbackLimitMessage('')}
                  className={`flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-amber-600 hover:text-amber-700'}`}
                >
                  <XMarkIcon className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>

          <div className={`border-t pt-6 text-center transition-colors duration-300 ${
            isDarkMode ? 'border-gray-800' : 'border-gray-300'
          }`} style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <p className={`text-xs transition-colors duration-300 ${
              isDarkMode ? 'text-gray-600' : 'text-gray-600'
            }`}>
              &copy; 2024 LegalEase System. All rights reserved. | Privacy | Terms
            </p>
          </div>
        </div>
      </footer>

      {/* Auth Modal with Tabs */}
      <AuthTabsModal
        isOpen={isAuthModalOpen}
        onClose={() => setIsAuthModalOpen(false)}
        isDarkMode={isDarkMode}
      />

      {/* Feedback Thank You Modal */}
      <FeedbackThankYouModal
        isOpen={isThankYouModalOpen}
        onClose={() => setIsThankYouModalOpen(false)}
        rating={feedbackRating}
        message={feedbackMessage}
        category={feedbackCategory}
      />
      <FeedbackErrorModal
        isOpen={isErrorModalOpen}
        onClose={() => setIsErrorModalOpen(false)}
        title={errorModalContent.title}
        message={errorModalContent.message}
        primaryAction={errorModalContent.primaryAction}
      />

      {/* All Testimonials Modal */}
      <AllTestimonialsModal
        isOpen={isAllTestimonialsModalOpen}
        onClose={() => setIsAllTestimonialsModalOpen(false)}
        isDarkMode={isDarkMode}
      />
    </div>
  );
};

export default LandingPage;