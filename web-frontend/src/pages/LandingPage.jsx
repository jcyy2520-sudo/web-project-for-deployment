import { useState, useEffect, useRef } from 'react';
import { useTheme } from '../context/ThemeContext';
import logger from '../utils/logger';
import useLandingContent from '../hooks/useLandingContent';
import AuthTabsModal from '../components/auth/AuthTabsModal';
import StarRating from '../components/ui/StarRating';
import FeedbackThankYouModal from '../components/modals/FeedbackThankYouModal';
import FeedbackErrorModal from '../components/modals/FeedbackErrorModal';
import AllTestimonialsModal from '../components/modals/AllTestimonialsModal';
import InlineChatbot from '../components/chatbot/InlineChatbot';
import { CheckCircleIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

const LandingPage = () => {
  const { isDarkMode, setIsDarkMode } = useTheme();
  const { getSection, getSetting, getSettingsGroup, loading: cmsLoading } = useLandingContent();

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
  const [feedbackSuccessMessage, setFeedbackSuccessMessage] = useState('');
  const [feedbackLimitMessage, setFeedbackLimitMessage] = useState('');
  const [feedbackCategory, setFeedbackCategory] = useState('other');
  const hasAnimatedRef = useRef(false);

  // Handle Auth Modal trigger from redirects/callbacks
  useEffect(() => {
    const searchParams = new URLSearchParams(window.location.search);
    
    // Check if we should open the auth modal (triggered by redirects/callbacks)
    if (searchParams.get('auth_modal') === 'open') {
      setIsAuthModalOpen(true);
      
      // Clean up the URL
      const newUrl = window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);
    }
    
    // Legacy support for direct registration confirmation links
    if (searchParams.get('registration') === 'confirmed') {
      sessionStorage.setItem('oauth_success_message', 'Registration confirmed. You can now sign in.');
      setIsAuthModalOpen(true);
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }, []);

  // CMS content extraction
  const hero = getSection('hero', {
    title: 'Professional Notary',
    subtitle: 'Services Made Easy',
    description: 'Get your documents notarized online in minutes. Secure, convenient, and professional. No hidden fees, no complicated process.',
    badge_text: '✨ Trusted Legal Notary Service',
    button_primary_text: 'Book Appointment',
    button_secondary_text: 'Learn More',
    image_url: '/hero.webp',
    image_alt: 'Legal Notary Services',
  });

  const servicesSection = getSection('services', {
    title: 'Complete Notary Solutions',
    badge_text: 'Our Services',
    description: 'Professional notarization services tailored to your legal needs',
  });

  const howItWorks = getSection('how_it_works', {
    title: 'Simple 4-Step Process',
    badge_text: 'How It Works',
    description: 'Get your documents notarized in minutes with our streamlined process',
  });

  const testimonialsSection = getSection('testimonials', {
    title: 'Trusted by Hundreds',
    badge_text: 'Testimonials',
    description: 'Real feedback from clients who have experienced our professional notary service',
    button_primary_text: 'See All Testimonials',
  });

  const ctaSection = getSection('cta', {
    title: 'Ready to Get Started?',
    description: 'Join hundreds of satisfied clients who trust us with their important legal documents',
    button_primary_text: 'Start Now',
    button_secondary_text: 'Learn More',
  });

  const chatbotSection = getSection('chatbot', {
    title: 'Have Questions? Ask Our AI Assistant',
    description: 'Get instant answers about our services, pricing, requirements, and more. Our AI assistant is available 24/7.',
    badge_text: 'AI Assistant',
  });

  const statsSection = getSection('stats');

  // Settings
  const siteName = getSetting('site_name', 'LEGALEASE');
  const logoUrl = getSetting('logo_url', '/logo.jpg');
  const logoAlt = getSetting('logo_alt', 'LegalEase Logo');
  const copyrightText = getSetting('copyright_text', '© 2024 LegalEase System. All rights reserved. | Privacy | Terms');
  const footerAddress = getSetting('footer_address', '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.');
  const footerContactText = getSetting('footer_contact_text', 'Have questions? Our team is here to help.');
  const footerServicesLinks = getSetting('footer_services_links', ['Services', 'Notarization', 'Verification', 'Certification', 'Signing']);
  const footerSupportLinks = getSetting('footer_support_links', ['Help Center', 'Contact Us', 'FAQ']);
  const navItems = getSetting('nav_items', ['Home', 'Services', 'How It Works', 'Reviews']);
  const navCtaText = getSetting('nav_cta_text', 'Get Started');
  const navSigninText = getSetting('nav_signin_text', 'Sign In');
  const feedbackFormTitle = getSetting('feedback_form_title', 'Share Your Feedback');
  const feedbackCategories = getSetting('feedback_categories', [
    { value: 'service_quality', label: 'Service Quality' },
    { value: 'speed', label: 'Speed' },
    { value: 'support', label: 'Support' },
    { value: 'system_experience', label: 'System Experience' },
    { value: 'bug_report', label: 'Bug Report' },
    { value: 'suggestion', label: 'Suggestion' },
    { value: 'other', label: 'Other' },
  ]);
  const chatbotEnabled = getSetting('chatbot_section_enabled', true);

  // Gradients
  const lightGradient = () => `linear-gradient(90deg, var(--primary), var(--secondary))`;
  const darkGradient = () => `linear-gradient(90deg,var(--accent),#D97706)`;
  const lightBgGradient = `linear-gradient(to bottom, var(--background), var(--surface))`;

  // Glass card utility
  const glassCard = isDarkMode
    ? 'bg-gray-900/30 backdrop-blur-xl border border-white/5 shadow-xl shadow-black/10'
    : 'bg-white/30 backdrop-blur-xl border border-white/40 shadow-xl shadow-blue-500/5';
  const glassCardHover = isDarkMode
    ? 'hover:bg-gray-900/50 hover:border-amber-500/20 hover:shadow-amber-500/10'
    : 'hover:bg-white/50 hover:border-blue-300/50 hover:shadow-blue-500/10';

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

  // Animate stats counter
  useEffect(() => {
    if (stats.totalAppointments > 0 || stats.totalUsers > 0 || stats.completedAppointments > 0 || stats.totalServices > 0) {
      if (!hasAnimatedRef.current) {
        hasAnimatedRef.current = true;
        const duration = 2000;
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
        setAnimatedStats({
          totalAppointments: stats.totalAppointments,
          totalUsers: stats.totalUsers,
          completedAppointments: stats.completedAppointments,
          totalServices: stats.totalServices
        });
      }
    }
  }, [stats]);

  // Fetch stats polling
  useEffect(() => {
    let isMounted = true;
    const fetchAllData = async () => {
      if (!isMounted) return;
      try {
        const statsResponse = await axios.get('/api/stats/summary', { timeout: 3000 });
        if (isMounted && statsResponse.data?.data) {
          const apiStats = statsResponse.data.data;
          setStats({
            totalAppointments: apiStats.totalAppointments || 0,
            totalUsers: apiStats.totalUsers || 0,
            completedAppointments: apiStats.completedAppointments || 0,
            totalServices: apiStats.totalServices || 0
          });
        }
      } catch (err) {
        logger.debug('Stats fetch failed, keeping existing values');
      }
    };
    fetchAllData();
    const interval = setInterval(fetchAllData, 30000);
    return () => { isMounted = false; clearInterval(interval); };
  }, []);

  // Active section observer
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => { entries.forEach((entry) => { if (entry.isIntersecting) setActiveSection(entry.target.id); }); },
      { threshold: 0.3 }
    );
    return () => observer.disconnect();
  }, []);

  // Handle hash scroll (e.g., /#assistant from another page)
  useEffect(() => {
    if (window.location.hash) {
      const id = window.location.hash.slice(1);
      const el = document.getElementById(id);
      if (el) {
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
      }
    }
  }, []);

  // Fetch services and testimonials
  useEffect(() => {
    const fetchServicesAndTestimonials = async () => {
      // Prefer the combined cached endpoint to minimize DB work and roundtrips
      try {
        const resp = await axios.get('/api/public/init', { timeout: 3000 });
        if (resp.data?.data) {
          const data = resp.data.data;
          if (Array.isArray(data.services)) setServices(data.services.slice(0, 4));
          if (data.stats) setStats(data.stats);
          if (Array.isArray(data.testimonials)) setTestimonials(data.testimonials);
          return;
        }
      } catch (err) {
        logger.debug('Public init endpoint unavailable, falling back to individual calls');
      }

      // Fallback to separate endpoints if combined init fails
      try {
        const servicesResponse = await axios.get('/api/services', { timeout: 3000 });
        if (servicesResponse.data?.data && Array.isArray(servicesResponse.data.data)) {
          setServices(servicesResponse.data.data.slice(0, 4));
        }
      } catch (err) {
        logger.debug('Services API unavailable');
        setServices([]);
      }

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
      alert('Please complete all feedback fields before submitting.');
      return;
    }
    if (feedbackMessage.trim().length < 10) {
      alert('Feedback must be at least 10 characters long.');
      return;
    }
    setIsFeedbackLoading(true);
    setFeedbackSuccessMessage('');
    setFeedbackLimitMessage('');
    try {
      await axios.post('/api/feedback', {
        email: feedbackEmail,
        message: feedbackMessage,
        rating: feedbackRating,
        feedback_type: feedbackCategory
      });
      setFeedbackSuccessMessage('✅ Thank you! Your feedback has been received. A confirmation email has been sent to your inbox.');
      setFeedbackEmail('');
      setFeedbackMessage('');
      setFeedbackRating(0);
      setFeedbackCategory('other');
      setTimeout(() => setFeedbackSuccessMessage(''), 8000);
    } catch (error) {
      const resp = error.response?.data || {};
      if (resp.error === 'email_not_registered') {
        setFeedbackLimitMessage('⚠️ The email you provided is not registered. Please create an account or log in to submit feedback.');
      } else if (resp.error === 'rate_limit_reached') {
        const nextAvailable = resp.data?.next_available_at;
        if (nextAvailable) {
          const date = new Date(nextAvailable);
          const formattedDate = `${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} at ${date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}`;
          setFeedbackLimitMessage(`⚠️ You've reached your feedback limit. You can submit feedback again on ${formattedDate}.`);
        } else {
          setFeedbackLimitMessage('⚠️ You have reached your feedback submission limit. Please try again later.');
        }
      } else if (resp.error === 'profanity_detected') {
        setFeedbackLimitMessage('❌ Your feedback contains disallowed language. Please edit and try again.');
      } else if (resp.error === 'duplicate_feedback') {
        setFeedbackLimitMessage('⚠️ It looks like you submitted similar feedback recently. Please try again later.');
      } else {
        alert('Failed to send feedback. Please try again.');
      }
      setTimeout(() => setFeedbackLimitMessage(''), 8000);
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

  // Transform real services into features with CMS fallback
  const features = services.length > 0
    ? services.map((service, idx) => ({
        title: service.name || 'Legal Service',
        description: service.description || 'Professional legal service tailored to your needs',
        icon: ['⚖️', '📋', '🔐', '✅'][idx % 4]
      }))
    : servicesSection.items.length > 0
      ? servicesSection.items.map(item => ({
          title: item.title,
          description: item.description,
          icon: item.icon || '📋'
        }))
      : [
          { title: "Instant Booking", description: "Schedule appointments in seconds with our intuitive booking system", icon: "⏱️" },
          { title: "Document Security", description: "Military-grade encryption for all your sensitive legal documents", icon: "🛡️" },
          { title: "Real-time Tracking", description: "Track your appointment status and receive live updates", icon: "📱" },
          { title: "Available Always", description: "Book and manage appointments anytime, anywhere", icon: "🌙" },
        ];

  // Process steps from CMS or defaults
  const processSteps = howItWorks.items.length > 0
    ? howItWorks.items.map(item => ({
        step: item.step_number || '01',
        title: item.title,
        description: item.description,
      }))
    : [
        { step: "01", title: "Register Account", description: "Create your secure account in under 2 minutes" },
        { step: "02", title: "Verify Identity", description: "Complete quick identity verification process" },
        { step: "03", title: "Book Appointment", description: "Choose your preferred date and time slot" },
        { step: "04", title: "Get Notarized", description: "Complete your notarization seamlessly" },
      ];

  // Trust indicators from CMS or defaults
  const trustIndicators = hero.items.length > 0
    ? hero.items.map(item => ({
        value: item.metadata?.stat_key ? (stats[item.metadata.stat_key] || item.description) : (item.metadata?.static_value || item.description),
        label: item.title,
      }))
    : [
        { value: stats.totalAppointments || '500+', label: 'Documents Notarized' },
        { value: stats.totalUsers || '1000+', label: 'Satisfied Clients' },
        { value: '8/5', label: 'Available Anytime' },
      ];

  // Dynamic stats display
  const displayStats = statsSection.items.length > 0
    ? statsSection.items.map(item => ({
        number: item.metadata?.stat_key
          ? (animatedStats[item.metadata.stat_key] > 0
            ? `${animatedStats[item.metadata.stat_key]}${item.metadata?.suffix || ''}`
            : '—')
          : '—',
        label: item.title,
      }))
    : [
        { number: animatedStats.totalAppointments > 0 ? `${animatedStats.totalAppointments}+` : "—", label: "Total Appointments" },
        { number: animatedStats.totalUsers > 0 ? `${animatedStats.totalUsers}+` : "—", label: "Active Users" },
        { number: animatedStats.completedAppointments > 0 ? `${animatedStats.completedAppointments}+` : "—", label: "Completed Services" },
        { number: animatedStats.totalServices > 0 ? `${animatedStats.totalServices}` : "—", label: "Available Services" },
      ];

  // How It Works visual from CMS
  const howItWorksVisualIcon = howItWorks.metadata?.visual_icon || '📋';
  const howItWorksVisualText = howItWorks.metadata?.visual_text || 'Secure Document Processing';

  return (
    <div className={`min-h-screen overflow-hidden transition-colors duration-500 ${
      isDarkMode
        ? 'bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950'
        : ''
    }`} style={!isDarkMode ? { background: lightBgGradient } : {}}>
      {/* Animated Background Blobs */}
      <div className="fixed inset-0 pointer-events-none overflow-hidden">
        <div
          className={`absolute top-1/4 left-1/4 w-80 h-80 ${isDarkMode ? 'bg-amber-500/5' : 'bg-blue-500/5'} rounded-full blur-3xl animate-pulse`}
          style={{ transform: `translate(${mousePosition.x}px, ${mousePosition.y}px)`, animationDelay: '0.5s' }}
        />
        <div
          className={`absolute bottom-1/3 right-1/4 w-96 h-96 ${isDarkMode ? 'bg-amber-600/3' : 'bg-blue-600/3'} rounded-full blur-3xl animate-pulse`}
          style={{ transform: `translate(${-mousePosition.x * 2}px, ${-mousePosition.y * 2}px)`, animationDelay: '1s' }}
        />
        <div
          className={`absolute top-2/3 left-1/2 w-64 h-64 ${isDarkMode ? 'bg-purple-500/3' : 'bg-indigo-400/3'} rounded-full blur-3xl animate-pulse`}
          style={{ transform: `translate(${mousePosition.x * 1.5}px, ${-mousePosition.y}px)`, animationDelay: '2s' }}
        />
      </div>

      {/* Scroll Progress Bar */}
      <div className="fixed top-0 left-0 right-0 h-0.5 z-50 origin-left scale-x-0"
        style={{ animation: 'progress linear forwards', animationTimeline: 'scroll(root)', background: isDarkMode ? darkGradient() : lightGradient() }} />

      {/* ==================== NAVIGATION ==================== */}
      <nav className={`fixed w-full z-50 transition-all duration-300 ${
        isDarkMode
          ? 'bg-gray-900/30 backdrop-blur-xl border-b border-white/5'
          : 'bg-white/30 backdrop-blur-xl border-b border-white/40'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="flex justify-between items-center h-16 md:h-20">
            {/* Logo */}
            <div className="flex items-center space-x-2.5 group cursor-pointer" onClick={() => scrollToSection('home')}>
              <img src={logoUrl} alt={logoAlt} className="h-8 md:h-10 w-auto object-contain rounded shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3" />
              <span className="text-lg md:text-2xl font-bold tracking-tight transition-colors duration-300"
                style={{ color: isDarkMode ? 'rgba(255,255,255,0.95)' : 'var(--secondary)', textTransform: !isDarkMode ? 'uppercase' : undefined, letterSpacing: '1px' }}>
                {siteName}
              </span>
            </div>

            {/* Desktop Menu */}
            <div className="hidden md:flex items-center space-x-6">
              {(Array.isArray(navItems) ? navItems : ['Home', 'Services', 'How It Works', 'Reviews']).map((item) => {
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
                    <span className={`absolute inset-0 scale-0 group-hover:scale-100 rounded transition-transform duration-300 ${isDarkMode ? 'bg-amber-500/10' : 'bg-blue-200'}`} />
                  </button>
                );
              })}
            </div>

            {/* Auth Buttons */}
            <div className="hidden md:flex items-center space-x-3">
              <button onClick={() => setIsAuthModalOpen(true)}
                className={`text-gray-400 font-medium text-sm transition-all duration-300 hover:scale-105 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-600'}`}>
                {navSigninText}
              </button>
              <button onClick={() => setIsAuthModalOpen(true)}
                className="px-5 py-2 rounded-lg font-semibold hover:scale-105 active:scale-95 relative overflow-hidden group transition-all duration-300"
                style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: '#ffffff' }}>
                <span className="relative z-10">{navCtaText}</span>
                <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300"
                  style={{ background: isDarkMode ? 'linear-gradient(90deg,#C2410C,#92400E)' : lightGradient() }} />
              </button>
            </div>

            {/* Mobile Menu Button */}
            <button onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 rounded hover:bg-gray-800 transition-all duration-300 hover:scale-110">
              <svg className={`w-5 h-5 text-gray-400 transition-transform duration-300 ${isMobileMenuOpen ? 'rotate-90' : ''}`}
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        <div className={`md:hidden backdrop-blur-xl overflow-hidden transition-all duration-300 ${
          isDarkMode ? 'bg-gray-900/60 border-t border-white/5' : 'bg-white/60 border-t border-white/30'
        } ${isMobileMenuOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'}`}>
          <div className="px-4 py-3 space-y-2">
            {(Array.isArray(navItems) ? navItems : ['Home', 'Services', 'How It Works', 'Reviews']).map((item, index) => {
              const sectionId = item.toLowerCase().replace(/\s+/g, '');
              return (
                <button key={item} onClick={() => scrollToSection(sectionId)}
                  className={`block w-full text-left ${isDarkMode ? 'text-gray-300' : 'text-slate-700'} font-medium text-sm py-2 transition-all duration-300 transform hover:translate-x-2 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-400'} ${
                    activeSection === sectionId ? (isDarkMode ? 'text-amber-300' : 'text-blue-400') : ''
                  }`} style={{ animationDelay: `${index * 50}ms` }}>
                  {item}
                </button>
              );
            })}
            <div className="pt-3 space-y-2 border-t border-gray-800">
              <button onClick={() => setIsAuthModalOpen(true)}
                className={`block w-full text-left ${isDarkMode ? 'text-gray-300' : 'text-slate-700'} font-medium text-sm py-2 transition-colors duration-300 ${isDarkMode ? 'hover:text-amber-300' : 'hover:text-blue-400'}`}>
                {navSigninText}
              </button>
              <button onClick={() => setIsAuthModalOpen(true)}
                className="w-full py-2.5 rounded-lg font-semibold text-sm transition-all duration-300 hover:scale-105"
                style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: '#fff' }}>
                {navCtaText}
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* ==================== HERO SECTION ==================== */}
      <section id="home" className="pt-20 pb-12 md:pt-24 md:pb-16 relative">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="grid lg:grid-cols-2 gap-10 items-center">
            <div className="text-center lg:text-left" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className={`inline-block mb-5 px-3 py-1.5 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : ''}`}
                style={!isDarkMode ? { backgroundColor: 'var(--secondary-10)', border: '1px solid var(--secondary-20)' } : {}}>
                <span className="text-xs font-semibold tracking-wide"
                  style={!isDarkMode ? { color: 'var(--secondary)', textTransform: 'uppercase', letterSpacing: '0.6px' } : {}}>
                  {hero.badge_text}
                </span>
              </div>
              <h1 className={`text-4xl md:text-5xl lg:text-6xl font-bold leading-snug mb-5 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                {hero.title}
                <span style={isDarkMode
                  ? { background: 'linear-gradient(90deg,#FCD34D,var(--accent))', WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent', display: 'inline-block', fontWeight: 700 }
                  : { color: 'var(--primary)' }}>
                  {' '}{hero.subtitle}
                </span>
              </h1>
              <p className={`text-base md:text-lg mb-7 leading-relaxed max-w-xl ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                {hero.description}
              </p>
              <div className="flex flex-row gap-2 sm:gap-3 justify-center lg:justify-start items-center flex-wrap">
                <button onClick={() => setIsAuthModalOpen(true)}
                  className="px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:scale-105 active:scale-95 group relative overflow-hidden transition-all duration-300 text-white text-sm sm:text-base"
                  style={{ background: isDarkMode ? darkGradient() : lightGradient() }}>
                  <span className="relative z-10">{hero.button_primary_text}</span>
                  <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300"
                    style={{ background: isDarkMode ? 'linear-gradient(90deg,#C2410C,#92400E)' : lightGradient() }} />
                </button>
                <button onClick={() => scrollToSection('howitworks')}
                  className="border px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold transition-all duration-300 hover:scale-105 active:scale-95 text-sm sm:text-base"
                  style={!isDarkMode ? { borderColor: 'var(--borders)', color: 'var(--secondary)', backgroundColor: 'transparent' } : {}}>
                  {hero.button_secondary_text}
                </button>
                {/* Theme Toggle */}
                <button onClick={() => setIsDarkMode(!isDarkMode)}
                  className="p-2.5 rounded-lg transition-all duration-300 border"
                  style={!isDarkMode ? { backgroundColor: 'var(--surface)', borderColor: 'var(--borders)', color: 'var(--secondary)' } : {}}
                  title={isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'} aria-label="Toggle theme">
                  {isDarkMode ? (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" /></svg>
                  ) : (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clipRule="evenodd" /></svg>
                  )}
                </button>
              </div>

              {/* Trust Indicators */}
              <div className={`mt-10 flex flex-wrap gap-6 pt-6 border-t justify-center lg:justify-start ${isDarkMode ? 'border-gray-800' : 'border-blue-300'}`}
                style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
                {trustIndicators.map((indicator, idx) => (
                  <div key={idx} className="group">
                    <p className={`text-xl font-bold transition-all duration-300 group-hover:scale-110 ${isDarkMode ? 'text-amber-400' : ''}`}
                      style={!isDarkMode ? { color: 'var(--primary)' } : {}}>
                      {indicator.value}
                    </p>
                    <p className={`text-xs group-hover transition-colors ${isDarkMode ? 'text-gray-500 group-hover:text-gray-400' : 'text-slate-500 group-hover:text-slate-600'}`}>
                      {indicator.label}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Hero Visual */}
            <div className="relative hidden lg:block" style={{ animation: 'float 6s ease-in-out infinite' }}>
              <div className={`rounded-2xl p-6 overflow-hidden group transition-all duration-500 ${glassCard} ${glassCardHover}`}>
                <div className={`aspect-square rounded-xl flex items-center justify-center overflow-hidden transition-transform duration-700 group-hover:scale-105 ${
                  isDarkMode ? 'bg-gradient-to-br from-amber-500/10 to-amber-600/10 border border-amber-500/10' : 'bg-white/50 border border-blue-50'}`}>
                  <img src={hero.image_url} alt={hero.image_alt} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                </div>
                <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 ${
                  isDarkMode ? 'bg-gradient-to-t from-amber-500/5 to-transparent' : 'bg-gradient-to-t from-blue-500/5 to-transparent'}`} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ==================== STATS SECTION ==================== */}
      <section className={`py-12 transition-colors duration-500 ${
        isDarkMode ? 'bg-gray-900/30 border-y border-white/5' : 'bg-white/20 border-y border-white/30'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {displayStats.map((stat, index) => (
              <div key={index} className={`text-center group p-4 rounded-xl transition-all duration-300 ${glassCard} ${glassCardHover}`}
                style={{ animation: `fadeInUp 0.6s ease-out ${index * 100}ms forwards` }}>
                <div className="text-2xl md:text-3xl font-bold mb-1 transition-all duration-300 group-hover:scale-110"
                  style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
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

      {/* ==================== SERVICES SECTION ==================== */}
      <section id="services" className="py-16">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className={`inline-block mb-3 px-3 py-1 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : 'bg-blue-500/10 border border-blue-500/20'}`}>
              <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400' : 'text-blue-400'}`}>{servicesSection.badge_text}</span>
            </div>
            <h2 className="text-2xl md:text-4xl font-bold mb-4" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
              {servicesSection.title}
            </h2>
            <p className={`text-sm md:text-base max-w-2xl mx-auto ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
              {servicesSection.description}
            </p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {features.map((feature, index) => (
              <div key={index}
                className={`rounded-xl p-5 relative overflow-hidden transition-all duration-300 group ${glassCard} ${glassCardHover}`}
                style={{ animation: `fadeInUp 0.6s ease-out ${index * 100}ms forwards` }}>
                <div className="relative z-10">
                  <div className="text-3xl md:text-4xl mb-2 md:mb-4 transition-transform duration-300 group-hover:scale-110">
                    {feature.icon}
                  </div>
                  <h3 className={`text-sm md:text-lg font-bold mb-1 md:mb-2 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    {feature.title}
                  </h3>
                  <p className={`text-xs md:text-sm leading-relaxed ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>
                    {feature.description}
                  </p>
                </div>
                {/* Gradient glow on hover */}
                <div className={`absolute -inset-1 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 -z-10 blur-xl ${
                  isDarkMode ? 'bg-gradient-to-r from-amber-500/10 to-amber-600/10' : 'bg-gradient-to-r from-blue-400/10 to-blue-500/10'
                }`} />
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ==================== HOW IT WORKS SECTION ==================== */}
      <section id="howitworks" className={`py-16 transition-colors duration-500 ${
        isDarkMode ? 'bg-gray-900/30 border-y border-white/5' : 'bg-white/20 border-y border-white/30'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className={`inline-block mb-3 px-3 py-1 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : 'bg-blue-500/10 border border-blue-500/20'}`}>
              <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400' : 'text-blue-400'}`}>{howItWorks.badge_text}</span>
            </div>
            <h2 className="text-3xl md:text-4xl font-bold mb-4" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
              {howItWorks.title}
            </h2>
            <p className={`max-w-2xl mx-auto ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
              {howItWorks.description}
            </p>
          </div>

          <div className="grid lg:grid-cols-2 gap-12 items-center">
            <div className="space-y-6">
              {processSteps.map((step, index) => (
                <div key={index} className="flex gap-4 items-start group"
                  style={{ animation: `fadeInRight 0.6s ease-out ${index * 200}ms forwards` }}>
                  <div className="flex-shrink-0 relative">
                    <div className={`flex items-center justify-center h-14 w-14 rounded-full text-white shadow transition-all duration-300 group-hover:scale-110 ${
                      isDarkMode
                        ? 'bg-gradient-to-br from-amber-500 to-amber-600 border border-amber-400/30 group-hover:shadow-lg group-hover:shadow-amber-500/30'
                        : 'bg-gradient-to-br from-blue-500 to-blue-600 border border-blue-400/30 group-hover:shadow-lg group-hover:shadow-blue-500/30'
                    }`}>
                      <span className="text-lg font-bold">{step.step}</span>
                    </div>
                    {index < processSteps.length - 1 && (
                      <div className={`absolute top-full left-1/2 w-0.5 h-6 animate-pulse ${
                        isDarkMode ? 'bg-gradient-to-b from-amber-500 to-amber-600/50' : 'bg-gradient-to-b from-blue-500 to-blue-600/50'
                      }`} />
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
              <div className={`rounded-2xl p-6 group transition-all duration-500 ${glassCard} ${glassCardHover}`}>
                <div className={`aspect-square rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform duration-700 ${
                  isDarkMode ? 'bg-gradient-to-br from-gray-800 to-gray-700 border border-amber-500/10' : 'bg-gradient-to-br from-blue-50 to-white border border-blue-100'
                }`}>
                  <div className="text-center p-4">
                    <div className="text-5xl mb-3 animate-bounce">{howItWorksVisualIcon}</div>
                    <p className={`font-semibold text-sm ${isDarkMode ? 'text-gray-300' : 'text-slate-700'}`}>{howItWorksVisualText}</p>
                  </div>
                </div>
                <div className={`absolute -inset-2 rounded-2xl blur-xl transition-all duration-500 opacity-0 group-hover:opacity-100 ${
                  isDarkMode ? 'bg-gradient-to-r from-amber-500/10 to-amber-600/10' : 'bg-gradient-to-r from-blue-500/10 to-blue-600/10'
                }`} />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ==================== CHATBOT SECTION (Inline) ==================== */}
      {chatbotEnabled && (
        <section id="assistant" className="py-16">
          <div className="max-w-5xl mx-auto px-4 sm:px-6">
            <div className="text-center mb-10" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className={`inline-block mb-3 px-3 py-1 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : 'bg-blue-500/10 border border-blue-500/20'}`}>
                <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400' : 'text-blue-400'}`}>{chatbotSection.badge_text}</span>
              </div>
              <h2 className="text-3xl md:text-4xl font-bold mb-4" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>
                {chatbotSection.title}
              </h2>
              <p className={`max-w-2xl mx-auto ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                {chatbotSection.description}
              </p>
            </div>

            <div className="max-w-4xl mx-auto relative overflow-hidden">
              <InlineChatbot isDarkMode={isDarkMode} sectionData={chatbotSection} />
            </div>
          </div>
        </section>
      )}

      {/* ==================== REVIEWS SECTION ==================== */}
      <section id="reviews" className={`py-16 transition-colors duration-500 ${
        isDarkMode ? 'bg-gray-900/30 border-y border-white/5' : 'bg-white/20 border-y border-white/30'
      }`}>
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className={`inline-block mb-3 px-3 py-1 rounded-full animate-pulse ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20' : ''}`}
              style={!isDarkMode ? { backgroundColor: 'var(--secondary-10)', border: '1px solid var(--secondary-20)' } : {}}>
              <span className="text-xs font-semibold" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>{testimonialsSection.badge_text}</span>
            </div>
            <h2 className={`text-3xl md:text-4xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
              {testimonialsSection.title}
            </h2>
            <p className={`max-w-2xl mx-auto ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
              {testimonialsSection.description}
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {testimonials.length > 0 ? (
              testimonials.map((item, index) => (
                <div key={item.id}
                  className={`rounded-xl p-6 transition-all duration-500 hover:scale-105 group ${glassCard} ${glassCardHover}`}
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}>
                  <div className="flex items-center mb-4">
                    <div className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3"
                      style={!isDarkMode ? { background: lightGradient(), border: '1px solid var(--secondary-20)', color: 'var(--text-primary)' } : { background: darkGradient() }}>
                      {item.clientName.charAt(0).toUpperCase()}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-sm transition-colors" style={isDarkMode ? { color: '#fff' } : { color: 'var(--primary)' }}>
                        {item.clientName}
                      </div>
                      <div className="text-xs animate-pulse" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>
                        {'★'.repeat(item.rating)}
                      </div>
                    </div>
                  </div>
                  <p className={`text-sm mb-3 leading-relaxed transition-colors ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                    "{item.message || `Successfully completed ${item.serviceType}`}"
                  </p>
                  <span className="inline-block text-xs font-semibold px-2 py-1 rounded border transition-colors"
                    style={!isDarkMode ? { color: 'var(--secondary)', backgroundColor: 'var(--secondary-10)', borderColor: 'var(--secondary-20)' } : {}}>
                    {item.serviceType}
                  </span>
                </div>
              ))
            ) : (
              [1, 2, 3].map((item, index) => (
                <div key={item}
                  className={`rounded-xl p-6 transition-all duration-500 hover:scale-105 group ${glassCard} ${glassCardHover}`}
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}>
                  <div className="flex items-center mb-4">
                    <div className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm border transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3"
                      style={!isDarkMode ? { background: lightGradient(), borderColor: 'var(--secondary-20)' } : { background: darkGradient() }}>
                      C{item}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-sm transition-colors" style={isDarkMode ? { color: '#fff' } : { color: 'var(--primary)' }}>
                        Client {item}
                      </div>
                      <div className="text-xs" style={!isDarkMode ? { color: 'var(--secondary)' } : {}}>★★★★★</div>
                    </div>
                  </div>
                  <p className={`text-sm leading-relaxed transition-colors ${isDarkMode ? 'text-gray-400' : 'text-slate-600'}`}>
                    "{testimonialsSection.metadata?.fallback_message || 'Professional and reliable notary service. Made my document process so much easier!'}"
                  </p>
                </div>
              ))
            )}
          </div>

          {testimonials.length > 0 && (
            <div className="mt-8 flex justify-center">
              <button onClick={() => setIsAllTestimonialsModalOpen(true)}
                className={`px-6 py-3 rounded-lg font-semibold transition-all duration-300 hover:scale-105 active:scale-95 ${
                  isDarkMode
                    ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:shadow-lg hover:shadow-amber-500/30'
                    : 'bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:shadow-lg hover:shadow-blue-500/30'
                }`}>
                {testimonialsSection.button_primary_text}
              </button>
            </div>
          )}
        </div>
      </section>

      {/* ==================== CTA SECTION ==================== */}
      <section className="py-16 relative overflow-hidden"
        style={{ background: isDarkMode ? darkGradient() : lightGradient(), color: '#fff' }}>
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0" style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E")`,
            backgroundSize: '100px 100px'
          }} />
        </div>

        <div className="max-w-4xl mx-auto px-4 sm:px-6 text-center relative z-10">
          <h2 className="text-3xl md:text-4xl font-bold mb-4" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            {ctaSection.title}
          </h2>
          <p className="text-gray-100 mb-8 max-w-xl mx-auto text-sm" style={{ animation: 'fadeInUp 0.6s ease-out 100ms forwards' }}>
            {ctaSection.description}
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center" style={{ animation: 'fadeInUp 0.6s ease-out 200ms forwards' }}>
            <button onClick={() => setIsAuthModalOpen(true)}
              className="px-6 py-3 rounded-xl font-bold transition-all duration-300 hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl relative overflow-hidden group"
              style={{ backgroundColor: 'rgba(255,255,255,0.95)', color: isDarkMode ? '#92400E' : 'var(--primary)' }}>
              <span className="relative z-10">{ctaSection.button_primary_text}</span>
              <span className="absolute inset-0 bg-gray-100 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
            </button>
            <button onClick={() => scrollToSection('howitworks')}
              className="px-6 py-3 rounded-xl font-bold border border-white/50 text-white hover:bg-white/10 transition-all duration-300 hover:scale-105 active:scale-95">
              {ctaSection.button_secondary_text}
            </button>
          </div>
        </div>
      </section>

      {/* ==================== FOOTER ==================== */}
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
                <img src={logoUrl} alt={logoAlt} className="h-10 w-auto object-contain rounded transition-transform duration-300 group-hover:scale-110" />
                <span className="text-xl font-bold group-hover:text-amber-400 transition-colors"
                  style={isDarkMode ? { color: '#ffffff' } : { color: '#7DD3FC', textTransform: 'uppercase', letterSpacing: '1px' }}>
                  {siteName.replace('LEGALEASE', 'LegalEase')}
                </span>
              </div>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                {footerAddress}
              </p>
            </div>

            {/* Quick Links */}
            <div className="text-center" style={{ animation: 'fadeInUp 0.6s ease-out 100ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>Services</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {(Array.isArray(footerServicesLinks) ? footerServicesLinks : []).map((item) => (
                  <li key={item}>
                    <button className="hover:text-blue-400 transition-colors duration-300">{item}</button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support */}
            <div className="text-center" style={{ animation: 'fadeInUp 0.6s ease-out 200ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>Support</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {(Array.isArray(footerSupportLinks) ? footerSupportLinks : []).map((item) => (
                  <li key={item}>
                    <button className="hover:text-blue-400 transition-colors duration-300">{item}</button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Get Started */}
            <div className="col-span-2 md:col-span-1 text-center" style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
              <h4 className="font-semibold mb-3 text-sm" style={isDarkMode ? { color: '#ffffff' } : { color: 'var(--primary)' }}>{navCtaText}</h4>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                {footerContactText}
              </p>
              <button onClick={() => setIsAuthModalOpen(true)}
                className="w-full py-2 rounded-lg font-semibold text-sm hover:shadow-md transition-all duration-300 hover:scale-105 active:scale-95 relative overflow-hidden group"
                style={isDarkMode ? { background: darkGradient(), color: '#fff' } : { backgroundColor: 'var(--surface)', color: 'var(--secondary)' }}>
                <span className="relative z-10">{navCtaText}</span>
                <span className="absolute inset-0 translate-y-full group-hover:translate-y-0 transition-transform duration-300"
                  style={isDarkMode ? { background: 'linear-gradient(90deg,#C2410C,#92400E)' } : { backgroundColor: 'var(--surface)' }} />
              </button>
            </div>
          </div>

          {/* Feedback Form */}
          <div className={`rounded-xl p-6 mb-8 transition-colors duration-300 backdrop-blur-xl ${
            isDarkMode
              ? 'bg-gray-900/30 border border-white/5'
              : 'bg-white/30 border border-white/40'
          }`} style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <h3 className={`font-semibold mb-4 text-sm transition-colors duration-300 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
              {feedbackFormTitle}
            </h3>
            <form className="space-y-4" onSubmit={handleSendFeedback}>
              <div>
                <label className={`block text-xs font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Feedback Category
                </label>
                <select value={feedbackCategory} onChange={(e) => setFeedbackCategory(e.target.value)}
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 ${
                    isDarkMode
                      ? 'bg-gray-800/50 text-white border-gray-700 focus:border-amber-500'
                      : 'bg-white/60 text-gray-800 border-blue-200/50 focus:border-blue-500'
                  }`} disabled={isFeedbackLoading}>
                  {(Array.isArray(feedbackCategories) ? feedbackCategories : []).map(cat => (
                    <option key={cat.value} value={cat.value}>{cat.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className={`block text-xs font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Rate your experience
                </label>
                <div className="flex justify-start">
                  <StarRating value={feedbackRating} onChange={setFeedbackRating} size="md" />
                </div>
              </div>
              <div>
                <input type="email" placeholder="Your email" value={feedbackEmail} onChange={(e) => setFeedbackEmail(e.target.value)}
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 ${
                    isDarkMode
                      ? 'bg-gray-800/50 text-white border-gray-700 focus:border-amber-500 placeholder-gray-500'
                      : 'bg-white/60 text-gray-800 border-blue-200/50 focus:border-blue-500 placeholder-gray-500'
                  }`} required disabled={isFeedbackLoading} />
              </div>
              <div>
                <textarea placeholder="Your feedback or suggestions..." value={feedbackMessage} onChange={(e) => setFeedbackMessage(e.target.value)}
                  rows="3"
                  className={`w-full text-sm rounded-lg px-3 py-2 border focus:outline-none transition-colors duration-300 resize-none ${
                    isDarkMode
                      ? 'bg-gray-800/50 text-white border-gray-700 focus:border-amber-500 placeholder-gray-500'
                      : 'bg-white/60 text-gray-800 border-blue-200/50 focus:border-blue-500 placeholder-gray-500'
                  }`} required disabled={isFeedbackLoading} />
              </div>
              <button type="submit" disabled={isFeedbackLoading || feedbackRating === 0}
                className={`w-full py-2 rounded-lg font-semibold text-sm transition-all duration-300 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed ${
                  isDarkMode
                    ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:shadow-md hover:shadow-amber-500/30'
                    : 'bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:shadow-md hover:shadow-blue-600/40'
                }`}>
                {isFeedbackLoading ? (
                  <div className="flex items-center justify-center">
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    Sending...
                  </div>
                ) : 'Send Feedback'}
              </button>
            </form>

            {feedbackSuccessMessage && (
              <div className={`mt-4 p-4 rounded-lg flex items-start gap-3 border ${
                isDarkMode ? 'bg-green-500/10 border-green-500/30' : 'bg-green-50 border-green-300'
              }`}>
                <CheckCircleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                <div className="flex-1">
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-green-400' : 'text-green-700'}`}>{feedbackSuccessMessage}</p>
                </div>
                <button onClick={() => setFeedbackSuccessMessage('')}
                  className={`flex-shrink-0 ${isDarkMode ? 'text-green-400 hover:text-green-300' : 'text-green-600 hover:text-green-700'}`}>
                  <XMarkIcon className="h-4 w-4" />
                </button>
              </div>
            )}

            {feedbackLimitMessage && (
              <div className={`mt-4 p-4 rounded-lg flex items-start gap-3 border ${
                isDarkMode ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-300'
              }`}>
                <ExclamationTriangleIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <div className="flex-1">
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-700'}`}>{feedbackLimitMessage}</p>
                </div>
                <button onClick={() => setFeedbackLimitMessage('')}
                  className={`flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-amber-600 hover:text-amber-700'}`}>
                  <XMarkIcon className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>

          <div className={`border-t pt-6 text-center transition-colors duration-300 ${isDarkMode ? 'border-gray-800' : 'border-gray-300'}`}
            style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <p className="text-xs text-gray-600">{copyrightText}</p>
          </div>
        </div>
      </footer>

      {/* Modals */}
      <AuthTabsModal isOpen={isAuthModalOpen} onClose={() => setIsAuthModalOpen(false)} isDarkMode={isDarkMode} />
      <FeedbackThankYouModal isOpen={isThankYouModalOpen} onClose={() => setIsThankYouModalOpen(false)} rating={feedbackRating} message={feedbackMessage} category={feedbackCategory} />
      <FeedbackErrorModal isOpen={isErrorModalOpen} onClose={() => setIsErrorModalOpen(false)} title={errorModalContent.title} message={errorModalContent.message} primaryAction={errorModalContent.primaryAction} />
      <AllTestimonialsModal isOpen={isAllTestimonialsModalOpen} onClose={() => setIsAllTestimonialsModalOpen(false)} isDarkMode={isDarkMode} />
    </div>
  );
};

export default LandingPage;
