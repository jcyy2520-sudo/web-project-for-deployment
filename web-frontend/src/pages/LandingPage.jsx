import { useState, useEffect, useRef } from 'react';
import logger from '../utils/logger';
import LoginModal from '../components/auth/LoginModal';
import RegisterModal from '../components/auth/RegisterModal';
import LandingPageChatbot from '../components/chatbot/LandingPageChatbot';
import axios from 'axios';

const LandingPage = () => {
  const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
  const [isRegisterModalOpen, setIsRegisterModalOpen] = useState(false);
  const [feedbackEmail, setFeedbackEmail] = useState('');
  const [feedbackMessage, setFeedbackMessage] = useState('');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [activeSection, setActiveSection] = useState('home');
  const [animatedStats, setAnimatedStats] = useState({
    totalAppointments: 0,
    totalUsers: 0,
    completedAppointments: 0,
    pendingAppointments: 0
  });
  
  // Real data states
  const [services, setServices] = useState([]);
  const [stats, setStats] = useState({
    totalAppointments: 0,
    totalUsers: 0,
    completedAppointments: 0,
    pendingAppointments: 0
  });
  const [testimonials, setTestimonials] = useState([]);
  const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });

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
    };

    if (stats.totalAppointments > 0) {
      animateValue(0, stats.totalAppointments, setAnimatedStats, 'totalAppointments');
      animateValue(0, stats.totalUsers, setAnimatedStats, 'totalUsers');
      animateValue(0, stats.completedAppointments, setAnimatedStats, 'completedAppointments');
      animateValue(0, stats.pendingAppointments, setAnimatedStats, 'pendingAppointments');
    }
  }, [stats]);

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

    ['home', 'services', 'howitworks', 'reviews'].forEach((id) => {
      const element = document.getElementById(id);
      if (element) observer.observe(element);
    });

    return () => observer.disconnect();
  }, []);

  // Fetch real data on component mount
  useEffect(() => {
    const fetchLandingPageData = async () => {
      try {
        
        // Fetch stats - with fallback to defaults
        try {
          const statsResponse = await axios.get('/api/stats/summary', {
            timeout: 3000
          });
          
          if (statsResponse.data?.data) {
            setStats(statsResponse.data.data);
          }
        } catch (err) {
          logger.warn('Stats API unavailable, using defaults');
          setStats({
            totalAppointments: 500,
            totalUsers: 1000,
            completedAppointments: 450,
            pendingAppointments: 50
          });
        }
        
        // Fetch services - with fallback to defaults
        try {
          const servicesResponse = await axios.get('/api/services', {
            timeout: 3000
          });
          
          if (servicesResponse.data?.data && Array.isArray(servicesResponse.data.data)) {
            setServices(servicesResponse.data.data.slice(0, 4));
          }
        } catch (err) {
          logger.warn('Services API unavailable, using defaults');
          setServices([]);
        }
        
        // Fetch testimonials - with fallback to defaults
        try {
          const appointmentsResponse = await axios.get(
            '/api/appointments?status=completed&limit=3',
            { timeout: 3000 }
          );
          
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
        } catch (err) {
          logger.warn('Testimonials API unavailable, using defaults');
          setTestimonials([]);
        }
        
      } catch (error) {
        logger.error('Error in landing page data fetch:', error.message);
        // All errors are already handled in individual try-catch blocks
      }
    };
    
    fetchLandingPageData();
  }, []);

  const handleSendFeedback = (e) => {
    e.preventDefault();
    logger.info('Feedback:', { email: feedbackEmail, message: feedbackMessage });
    setFeedbackEmail('');
    setFeedbackMessage('');
    alert('Thank you for your feedback!');
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
      number: animatedStats.pendingAppointments > 0 ? `${animatedStats.pendingAppointments}` : "—", 
      label: "Pending Appointments" 
    }
  ];

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950 overflow-hidden">
      {/* Animated Background Elements */}
      <div className="fixed inset-0 pointer-events-none">
        <div 
          className="absolute top-1/4 left-1/4 w-64 h-64 bg-amber-500/5 rounded-full blur-3xl animate-pulse"
          style={{ 
            transform: `translate(${mousePosition.x}px, ${mousePosition.y}px)`,
            animationDelay: '0.5s'
          }}
        />
        <div 
          className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-amber-600/3 rounded-full blur-3xl animate-pulse"
          style={{ 
            transform: `translate(${-mousePosition.x * 2}px, ${-mousePosition.y * 2}px)`,
            animationDelay: '1s'
          }}
        />
      </div>

      {/* Scroll Progress Bar */}
      <div className="fixed top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-amber-500 to-amber-600 z-50 origin-left scale-x-0" 
           style={{ animation: 'progress linear forwards', animationTimeline: 'scroll(root)' }} />

      {/* Floating CTA Button (Mobile) */}
      <button
        onClick={() => setIsRegisterModalOpen(true)}
        className="md:hidden fixed bottom-6 right-6 bg-gradient-to-r from-amber-500 to-amber-600 text-white p-4 rounded-full shadow-lg hover:shadow-xl hover:shadow-amber-500/40 transition-all duration-300 animate-bounce hover:animate-none z-40"
      >
        <span className="text-sm font-semibold">Get Started</span>
      </button>

      {/* Navigation */}
      <nav className="fixed w-full bg-gray-900/95 backdrop-blur-md z-50 border-b border-gray-800/50 shadow-sm transition-all duration-300">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="flex justify-between items-center h-14">
            {/* Logo with hover animation */}
            <div 
              className="flex items-center space-x-2.5 group cursor-pointer"
              onClick={() => scrollToSection('home')}
            >
              <img 
                src="/logo.jpg" 
                alt="Logo" 
                className="h-8 w-auto rounded shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3" 
              />
              <span className="text-lg font-bold text-white tracking-tight group-hover:text-amber-400 transition-colors duration-300">
                LegalEase
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
                        ? 'text-amber-400 font-semibold' 
                        : 'text-gray-400 hover:text-amber-300'
                    }`}
                  >
                    <span className="relative z-10">{item}</span>
                    {activeSection === sectionId && (
                      <span className="absolute -bottom-1 left-0 w-full h-0.5 bg-gradient-to-r from-amber-500 to-amber-600 animate-pulse" />
                    )}
                    <span className="absolute inset-0 bg-amber-500/10 scale-0 group-hover:scale-100 rounded transition-transform duration-300" />
                  </button>
                );
              })}
            </div>

            {/* Auth Buttons */}
            <div className="hidden md:flex items-center space-x-3">
              <button
                onClick={() => setIsLoginModalOpen(true)}
                className="text-gray-400 hover:text-amber-400 font-medium text-sm transition-all duration-300 hover:scale-105"
              >
                Sign In
              </button>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="bg-gradient-to-r from-amber-500 to-amber-600 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg hover:shadow-amber-500/40 transition-all duration-300 hover:scale-105 active:scale-95 relative overflow-hidden group"
              >
                <span className="relative z-10">Get Started</span>
                <span className="absolute inset-0 bg-gradient-to-r from-amber-600 to-amber-700 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
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
          className={`md:hidden bg-gray-800/95 backdrop-blur-sm border-t border-gray-800 overflow-hidden transition-all duration-300 ${
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
                  className={`block w-full text-left text-gray-300 hover:text-amber-400 font-medium text-sm py-2 transition-all duration-300 transform hover:translate-x-2 ${
                    activeSection === sectionId ? 'text-amber-400' : ''
                  }`}
                  style={{ animationDelay: `${index * 50}ms` }}
                >
                  {item}
                </button>
              );
            })}
            <div className="pt-3 space-y-2 border-t border-gray-800">
              <button
                onClick={() => setIsLoginModalOpen(true)}
                className="block w-full text-left text-gray-300 hover:text-amber-400 font-medium text-sm py-2 transition-colors duration-300"
              >
                Sign In
              </button>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="w-full bg-gradient-to-r from-amber-500 to-amber-600 text-white py-2.5 rounded-lg font-semibold text-sm hover:shadow-md hover:shadow-amber-500/40 transition-all duration-300 hover:scale-105"
              >
                Get Started
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section id="home" className="pt-20 pb-16 md:pt-28 md:pb-20 relative">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid lg:grid-cols-2 gap-10 items-center">
            {/* Hero Content */}
            <div className="text-center lg:text-left" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className="inline-block mb-5 px-3 py-1.5 bg-amber-500/10 border border-amber-500/20 rounded-full animate-pulse">
                <span className="text-amber-300 text-xs font-semibold tracking-wide">✨ Trusted Legal Notary Service</span>
              </div>
              <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-white leading-snug mb-5">
                Professional Notary
                <span 
                  className="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-500 block"
                  style={{ backgroundSize: '200% 200%', animation: 'gradient 3s ease infinite' }}
                >
                  Services Made Easy
                </span>
              </h1>
              <p className="text-base md:text-lg text-gray-400 mb-7 leading-relaxed max-w-xl">
                Get your documents notarized online in minutes. Secure, convenient, and professional. No hidden fees, no complicated process.
              </p>
              <div className="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                <button
                  onClick={() => setIsRegisterModalOpen(true)}
                  className="bg-gradient-to-r from-amber-500 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg hover:shadow-amber-500/40 transition-all duration-300 hover:scale-105 active:scale-95 group relative overflow-hidden"
                >
                  <span className="relative z-10">Book Appointment</span>
                  <span className="absolute inset-0 bg-gradient-to-r from-amber-600 to-amber-700 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
                </button>
                <button
                  onClick={() => scrollToSection('howitworks')}
                  className="border border-amber-500/30 text-amber-300 px-6 py-3 rounded-xl font-semibold hover:bg-amber-500/10 hover:border-amber-400/50 transition-all duration-300 hover:scale-105 active:scale-95"
                >
                  Learn More
                </button>
              </div>

              {/* Trust Indicators */}
              <div className="mt-10 flex flex-wrap gap-6 pt-6 border-t border-gray-800" style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
                <div className="group">
                  <p className="text-xl font-bold text-amber-400 transition-all duration-300 group-hover:scale-110">
                    {stats.totalAppointments || '500+'}
                  </p>
                  <p className="text-gray-500 text-xs group-hover:text-gray-400 transition-colors">Documents Notarized</p>
                </div>
                <div className="group">
                  <p className="text-xl font-bold text-amber-400 transition-all duration-300 group-hover:scale-110">
                    {stats.totalUsers || '1000+'}
                  </p>
                  <p className="text-gray-500 text-xs group-hover:text-gray-400 transition-colors">Satisfied Clients</p>
                </div>
                <div className="group">
                  <p className="text-xl font-bold text-amber-400 transition-all duration-300 group-hover:scale-110">8/5</p>
                  <p className="text-gray-500 text-xs group-hover:text-gray-400 transition-colors">Available Anytime</p>
                </div>
              </div>
            </div>

            {/* Hero Visual with hover effect */}
            <div className="relative hidden lg:block" style={{ animation: 'float 6s ease-in-out infinite' }}>
              <div className="bg-gradient-to-br from-gray-800 to-gray-700/90 border border-gray-700 rounded-2xl shadow-lg p-6 overflow-hidden group hover:border-amber-500/30 transition-all duration-500">
                <div className="aspect-square bg-gradient-to-br from-amber-500/10 to-amber-600/10 border border-amber-500/10 rounded-xl flex items-center justify-center overflow-hidden group-hover:scale-105 transition-transform duration-700">
                  <img 
                    src="/hero.webp" 
                    alt="Legal Notary Services" 
                    className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" 
                  />
                </div>
                <div className="absolute inset-0 bg-gradient-to-t from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Stats Section with animated counters */}
      <section className="py-12 bg-gray-900/50 border-y border-gray-800/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {displayStats.map((stat, index) => (
              <div 
                key={index} 
                className="text-center group"
                style={{ animation: `fadeInUp 0.6s ease-out ${index * 100}ms forwards` }}
              >
                <div className="text-2xl md:text-3xl font-bold text-white mb-1 transition-all duration-300 group-hover:text-amber-400 group-hover:scale-110">
                  {stat.number}
                </div>
                <div className="text-gray-500 text-sm group-hover:text-gray-400 transition-colors">
                  {stat.label}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Services Section */}
      <section id="services" className="py-16">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className="inline-block mb-3 px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full animate-pulse">
              <span className="text-amber-300 text-xs font-semibold">Our Services</span>
            </div>
            <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">
              Complete Notary Solutions
            </h2>
            <p className="text-gray-400 max-w-2xl mx-auto">
              Professional notarization services tailored to your legal needs
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {features.map((feature, index) => (
              <div 
                key={index} 
                className="group bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-amber-500/30 transition-all duration-500 hover:scale-105 relative overflow-hidden"
                style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}
              >
                <div className="relative z-10">
                  <div className="text-4xl mb-4 transition-transform duration-500 group-hover:scale-125 group-hover:rotate-3">
                    {feature.icon}
                  </div>
                  <h3 className="text-lg font-bold text-white mb-2 group-hover:text-amber-300 transition-colors">
                    {feature.title}
                  </h3>
                  <p className="text-gray-400 text-sm leading-relaxed group-hover:text-gray-300 transition-colors">
                    {feature.description}
                  </p>
                </div>
                <div className="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-amber-500/0 group-hover:from-amber-500/5 group-hover:to-amber-500/10 transition-all duration-500 rounded-xl" />
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works Section */}
      <section id="howitworks" className="py-16 bg-gray-900/50 border-y border-gray-800/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className="inline-block mb-3 px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full animate-pulse">
              <span className="text-amber-300 text-xs font-semibold">How It Works</span>
            </div>
            <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">
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
                    <div className="flex items-center justify-center h-14 w-14 rounded-full bg-gradient-to-br from-amber-500 to-amber-600 text-white shadow border border-amber-400/30 transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-amber-500/30">
                      <span className="text-lg font-bold">{step.step}</span>
                    </div>
                    {index < processSteps.length - 1 && (
                      <div className="absolute top-full left-1/2 w-0.5 h-6 bg-gradient-to-b from-amber-500 to-amber-600/50 animate-pulse" />
                    )}
                  </div>
                  <div className="group-hover:translate-x-2 transition-transform duration-300">
                    <h3 className="text-xl font-bold text-white mb-1 group-hover:text-amber-300 transition-colors">
                      {step.title}
                    </h3>
                    <p className="text-gray-400 text-sm group-hover:text-gray-300 transition-colors">
                      {step.description}
                    </p>
                  </div>
                </div>
              ))}
            </div>

            {/* Process Visual */}
            <div className="relative hidden lg:block" style={{ animation: 'fadeInLeft 0.6s ease-out forwards' }}>
              <div className="bg-gradient-to-br from-gray-800 to-gray-700/90 border border-gray-700 rounded-2xl shadow p-6 group hover:border-amber-500/30 transition-all duration-500">
                <div className="aspect-square bg-gradient-to-br from-gray-800 to-gray-700 border border-amber-500/10 rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform duration-700">
                  <div className="text-center p-4">
                    <div className="text-5xl mb-3 animate-bounce">📋</div>
                    <p className="text-gray-300 font-semibold text-sm">Secure Document Processing</p>
                  </div>
                </div>
                <div className="absolute -inset-2 bg-gradient-to-r from-amber-500/0 to-amber-600/0 group-hover:from-amber-500/10 group-hover:to-amber-600/10 rounded-2xl blur-xl transition-all duration-500 opacity-0 group-hover:opacity-100" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Reviews/Testimonials Section */}
      <section id="reviews" className="py-16">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <div className="inline-block mb-3 px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full animate-pulse">
              <span className="text-amber-300 text-xs font-semibold">Testimonials</span>
            </div>
            <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">
              Trusted by Hundreds
            </h2>
            <p className="text-gray-400 max-w-2xl mx-auto">
              Real feedback from clients who have experienced our professional notary service
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {testimonials.length > 0 ? (
              // Real testimonials
              testimonials.map((item, index) => (
                <div 
                  key={item.id} 
                  className="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-amber-500/30 transition-all duration-500 hover:scale-105 group"
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}
                >
                  <div className="flex items-center mb-4">
                    <div className="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-white font-bold text-sm shadow border border-amber-400/30 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                      {item.clientName.charAt(0).toUpperCase()}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-white text-sm group-hover:text-amber-300 transition-colors">
                        {item.clientName}
                      </div>
                      <div className="text-amber-400 text-xs animate-pulse">
                        {'★'.repeat(item.rating)}
                      </div>
                    </div>
                  </div>
                  <p className="text-gray-400 text-sm mb-3 leading-relaxed group-hover:text-gray-300 transition-colors">
                    "{item.message || `Successfully completed ${item.serviceType}`}"
                  </p>
                  <span className="inline-block text-xs font-semibold text-amber-300 bg-amber-500/10 px-2 py-1 rounded border border-amber-500/20 group-hover:bg-amber-500/20 transition-colors">
                    {item.serviceType}
                  </span>
                </div>
              ))
            ) : (
              // Fallback testimonials
              [1, 2, 3].map((item, index) => (
                <div 
                  key={item} 
                  className="bg-gray-900/50 border border-gray-800 rounded-xl p-6 hover:border-amber-500/30 transition-all duration-500 hover:scale-105 group"
                  style={{ animation: `fadeInUp 0.6s ease-out ${index * 150}ms forwards` }}
                >
                  <div className="flex items-center mb-4">
                    <div className="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-white font-bold text-sm border border-amber-400/30 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                      C{item}
                    </div>
                    <div className="ml-3">
                      <div className="font-bold text-white text-sm group-hover:text-amber-300 transition-colors">
                        Client {item}
                      </div>
                      <div className="text-amber-400 text-xs">★★★★★</div>
                    </div>
                  </div>
                  <p className="text-gray-400 text-sm leading-relaxed group-hover:text-gray-300 transition-colors">
                    "Professional and reliable notary service. Made my document process so much easier!"
                  </p>
                </div>
              ))
            )}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-16 bg-gradient-to-r from-amber-600 to-amber-700 text-white relative overflow-hidden">
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
              onClick={() => setIsRegisterModalOpen(true)}
              className="bg-white text-amber-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-50 transition-all duration-300 hover:scale-105 active:scale-95 shadow-lg hover:shadow-xl relative overflow-hidden group"
            >
              <span className="relative z-10">Start Now</span>
              <span className="absolute inset-0 bg-gray-100 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
            </button>
            <button
              onClick={() => scrollToSection('howitworks')}
              className="border border-white text-white px-6 py-3 rounded-xl font-bold hover:bg-white/10 transition-all duration-300 hover:scale-105 active:scale-95"
            >
              Learn More
            </button>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-950 text-gray-400 pt-12 pb-6 border-t border-gray-800">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
            {/* Company Info */}
            <div style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
              <div className="flex items-center space-x-2.5 mb-3 group">
                <img 
                  src="/logo.jpg" 
                  alt="Logo" 
                  className="h-8 w-auto rounded transition-transform duration-300 group-hover:scale-110" 
                />
                <span className="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">
                  LegalEase
                </span>
              </div>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.
              </p>
            </div>

            {/* Quick Links */}
            <div style={{ animation: 'fadeInUp 0.6s ease-out 100ms forwards' }}>
              <h4 className="font-semibold text-white mb-3 text-sm">Services</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {['Notarization', 'Verification', 'Certification', 'Signing'].map((item, index) => (
                  <li key={item}>
                    <button 
                      className="hover:text-amber-400 transition-colors duration-300 hover:translate-x-1 block"
                      style={{ animationDelay: `${index * 50}ms` }}
                    >
                      {item}
                    </button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support */}
            <div style={{ animation: 'fadeInUp 0.6s ease-out 200ms forwards' }}>
              <h4 className="font-semibold text-white mb-3 text-sm">Support</h4>
              <ul className="space-y-1.5 text-xs text-gray-500">
                {['Help Center', 'Contact Us', 'FAQ'].map((item, index) => (
                  <li key={item}>
                    <button 
                      className="hover:text-amber-400 transition-colors duration-300 hover:translate-x-1 block"
                      style={{ animationDelay: `${index * 50}ms` }}
                    >
                      {item}
                    </button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Contact */}
            <div style={{ animation: 'fadeInUp 0.6s ease-out 300ms forwards' }}>
              <h4 className="font-semibold text-white mb-3 text-sm">Get Started</h4>
              <p className="text-gray-500 text-xs mb-3 hover:text-gray-400 transition-colors">
                Have questions? Our team is here to help.
              </p>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="w-full bg-gradient-to-r from-amber-500 to-amber-600 text-white py-2 rounded-lg font-semibold text-sm hover:shadow-md hover:shadow-amber-500/30 transition-all duration-300 hover:scale-105 active:scale-95 relative overflow-hidden group"
              >
                <span className="relative z-10">Get Started</span>
                <span className="absolute inset-0 bg-gradient-to-r from-amber-600 to-amber-700 translate-y-full group-hover:translate-y-0 transition-transform duration-300" />
              </button>
            </div>
          </div>

          <div className="border-t border-gray-800 pt-6 text-center" style={{ animation: 'fadeInUp 0.6s ease-out forwards' }}>
            <p className="text-gray-600 text-xs">
              &copy; 2024 LegalEase System. All rights reserved. | Privacy | Terms
            </p>
          </div>
        </div>
      </footer>

      {/* Add custom animations to global CSS */}
      <style jsx global>{`
        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        @keyframes fadeInRight {
          from {
            opacity: 0;
            transform: translateX(-20px);
          }
          to {
            opacity: 1;
            transform: translateX(0);
          }
        }
        @keyframes fadeInLeft {
          from {
            opacity: 0;
            transform: translateX(20px);
          }
          to {
            opacity: 1;
            transform: translateX(0);
          }
        }
        @keyframes float {
          0%, 100% {
            transform: translateY(0);
          }
          50% {
            transform: translateY(-10px);
          }
        }
        @keyframes gradient {
          0%, 100% {
            background-position: 0% 50%;
          }
          50% {
            background-position: 100% 50%;
          }
        }
        @keyframes progress {
          from {
            transform: scaleX(0);
          }
          to {
            transform: scaleX(1);
          }
        }
      `}</style>

      {/* Modals */}
      <LoginModal
        isOpen={isLoginModalOpen}
        onClose={() => setIsLoginModalOpen(false)}
        onSwitchToRegister={() => {
          setIsLoginModalOpen(false);
          setIsRegisterModalOpen(true);
        }}
      />
      
      <RegisterModal
        isOpen={isRegisterModalOpen}
        onClose={() => setIsRegisterModalOpen(false)}
        onSwitchToLogin={() => {
          setIsRegisterModalOpen(false);
          setIsLoginModalOpen(true);
        }}
      />
      <LandingPageChatbot />
    </div>
  );
};

export default LandingPage;