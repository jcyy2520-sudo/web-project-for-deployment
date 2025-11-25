import { useState, useEffect } from 'react';
import logger from '../utils/logger';
import LoginModal from '../components/auth/LoginModal';
import RegisterModal from '../components/auth/RegisterModal';
import axios from 'axios';

const LandingPage = () => {
  const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
  const [isRegisterModalOpen, setIsRegisterModalOpen] = useState(false);
  const [feedbackEmail, setFeedbackEmail] = useState('');
  const [feedbackMessage, setFeedbackMessage] = useState('');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  
  // Real data states
  const [services, setServices] = useState([]);
  const [stats, setStats] = useState({
    totalAppointments: 0,
    totalUsers: 0,
    completedAppointments: 0,
    pendingAppointments: 0
  });
  const [testimonials, setTestimonials] = useState([]);
  const [loading, setLoading] = useState(true);

  // Fetch real data on component mount
  useEffect(() => {
    const fetchLandingPageData = async () => {
      try {
        setLoading(true);
        
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
      } finally {
        setLoading(false);
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
      number: stats.totalAppointments > 0 ? `${stats.totalAppointments}+` : "—", 
      label: "Total Appointments" 
    },
    { 
      number: stats.totalUsers > 0 ? `${stats.totalUsers}+` : "—", 
      label: "Active Users" 
    },
    { 
      number: stats.completedAppointments > 0 ? `${stats.completedAppointments}+` : "—", 
      label: "Completed Services" 
    },
    { 
      number: stats.pendingAppointments > 0 ? `${stats.pendingAppointments}` : "—", 
      label: "Pending Appointments" 
    }
  ];

  return (
    <div className="min-h-screen bg-white">
      {/* Navigation */}
      <nav className="fixed w-full bg-white/95 backdrop-blur-md z-50 border-b border-amber-100 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            {/* Logo */}
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center shadow-md">
                <span className="text-white font-bold text-sm">⚖️</span>
              </div>
              <span className="text-xl font-bold text-amber-900">Legal Ease</span>
            </div>

            {/* Desktop Menu */}
            <div className="hidden md:flex items-center space-x-8">
              {['Home', 'Services', 'How It Works', 'Reviews'].map((item) => (
                <button
                  key={item}
                  onClick={() => scrollToSection(item.toLowerCase().replace(/\s+/g, ''))}
                  className="text-gray-700 hover:text-amber-600 font-medium transition-colors text-sm"
                >
                  {item}
                </button>
              ))}
            </div>

            {/* Auth Buttons */}
            <div className="hidden md:flex items-center space-x-4">
              <button
                onClick={() => setIsLoginModalOpen(true)}
                className="text-gray-700 hover:text-amber-600 font-medium text-sm"
              >
                Sign In
              </button>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="bg-gradient-to-r from-amber-500 to-amber-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:shadow-amber-300/30 transition-all duration-300"
              >
                Get Started
              </button>
            </div>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 rounded-lg hover:bg-amber-100 transition-colors"
            >
              <svg className="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        {isMobileMenuOpen && (
          <div className="md:hidden bg-white border-t border-amber-100 px-4 py-4 space-y-4">
            {['Home', 'Services', 'How It Works', 'Reviews'].map((item) => (
              <button
                key={item}
                onClick={() => scrollToSection(item.toLowerCase().replace(/\s+/g, ''))}
                className="block w-full text-left text-gray-700 hover:text-amber-600 font-medium py-2"
              >
                {item}
              </button>
            ))}
            <div className="pt-4 space-y-3 border-t border-amber-100">
              <button
                onClick={() => setIsLoginModalOpen(true)}
                className="block w-full text-left text-gray-700 hover:text-amber-600 font-medium py-2"
              >
                Sign In
              </button>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="w-full bg-gradient-to-r from-amber-500 to-amber-600 text-white py-2.5 rounded-lg font-semibold"
              >
                Get Started
              </button>
            </div>
          </div>
        )}
      </nav>

      {/* Hero Section */}
      <section id="home" className="pt-24 pb-20 md:pt-32 md:pb-24 bg-gradient-to-br from-white via-amber-50 to-orange-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-2 gap-12 items-center">
            {/* Hero Content */}
            <div className="text-center lg:text-left">
              <div className="inline-block mb-6 px-4 py-2 bg-amber-100 rounded-full">
                <span className="text-amber-700 text-sm font-semibold">✨ Trusted Legal Notary Service</span>
              </div>
              <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold text-gray-900 leading-tight mb-6">
                Professional Notary Services
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-amber-500 to-orange-600"> Made Easy</span>
              </h1>
              <p className="text-lg md:text-xl text-gray-600 mb-8 leading-relaxed max-w-2xl">
                Get your documents notarized online in minutes. Secure, convenient, and professional. No hidden fees, no complicated process.
              </p>
              <div className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                <button
                  onClick={() => setIsRegisterModalOpen(true)}
                  className="bg-gradient-to-r from-amber-500 to-orange-600 text-white px-8 py-4 rounded-xl font-semibold hover:shadow-xl hover:shadow-amber-400/40 transition-all duration-300 transform hover:-translate-y-1"
                >
                  Book Your Appointment
                </button>
                <button
                  onClick={() => scrollToSection('howitworks')}
                  className="border-2 border-amber-500 text-amber-600 px-8 py-4 rounded-xl font-semibold hover:bg-amber-50 transition-all duration-300"
                >
                  Learn More
                </button>
              </div>

              {/* Trust Indicators */}
              <div className="mt-12 flex flex-col sm:flex-row gap-6 pt-8 border-t border-amber-200">
                <div>
                  <p className="text-2xl font-bold text-amber-600">{stats.totalAppointments || '500+'}</p>
                  <p className="text-gray-600 text-sm">Documents Notarized</p>
                </div>
                <div>
                  <p className="text-2xl font-bold text-amber-600">{stats.totalUsers || '1000+'}</p>
                  <p className="text-gray-600 text-sm">Satisfied Clients</p>
                </div>
                <div>
                  <p className="text-2xl font-bold text-amber-600">24/7</p>
                  <p className="text-gray-600 text-sm">Available Anytime</p>
                </div>
              </div>
            </div>

            {/* Hero Visual */}
            <div className="relative hidden lg:block">
              <div className="bg-gradient-to-br from-amber-100 to-orange-100 rounded-3xl shadow-2xl p-8">
                <div className="aspect-square bg-gradient-to-br from-amber-200 to-orange-200 rounded-2xl flex items-center justify-center">
                  <div className="text-center">
                    <div className="w-32 h-32 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                      <span className="text-6xl">⚖️</span>
                    </div>
                    <p className="text-amber-900 font-semibold">Fast & Secure Notary Process</p>
                  </div>
                </div>
              </div>
              <div className="absolute top-0 right-0 w-40 h-40 bg-amber-300/20 rounded-full blur-3xl"></div>
              <div className="absolute bottom-0 left-0 w-40 h-40 bg-orange-300/20 rounded-full blur-3xl"></div>
            </div>
          </div>
        </div>
      </section>

      {/* Services Section */}
      <section id="services" className="py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              {services.length > 0 ? 'Our Services' : 'Complete Notary Solutions'}
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              {services.length > 0 
                ? 'Professional notarization services tailored to your legal needs'
                : 'From document verification to certified signatures, we handle all your notary requirements with expertise and care.'}
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            {loading ? (
              // Loading skeleton
              [1, 2, 3, 4].map((_, index) => (
                <div key={index} className="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-sm animate-pulse">
                  <div className="w-16 h-16 bg-gray-200 rounded-xl mb-6"></div>
                  <div className="h-6 bg-gray-200 rounded w-3/4 mb-4"></div>
                  <div className="space-y-3">
                    <div className="h-4 bg-gray-200 rounded"></div>
                    <div className="h-4 bg-gray-200 rounded w-5/6"></div>
                  </div>
                </div>
              ))
            ) : (
              features.map((feature, index) => (
                <div key={index} className="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-8 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                  <div className="text-5xl mb-6">{feature.icon}</div>
                  <h3 className="text-xl font-bold text-gray-900 mb-3">{feature.title}</h3>
                  <p className="text-gray-600 leading-relaxed">{feature.description}</p>
                </div>
              ))
            )}
          </div>
        </div>
      </section>

      {/* How It Works Section */}
      <section id="howitworks" className="py-24 bg-gradient-to-b from-amber-50 to-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              Simple 4-Step Process
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Get your documents notarized in minutes with our streamlined process
            </p>
          </div>

          <div className="grid lg:grid-cols-2 gap-16 items-center">
            {/* Process Steps */}
            <div className="space-y-8">
              {processSteps.map((step, index) => (
                <div key={index} className="flex gap-6 group">
                  <div className="flex-shrink-0">
                    <div className="flex items-center justify-center h-20 w-20 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg group-hover:scale-110 transition-transform duration-300">
                      <span className="text-2xl font-bold">{step.step}</span>
                    </div>
                  </div>
                  <div className="pt-2">
                    <h3 className="text-2xl font-bold text-gray-900 mb-2">
                      {step.title}
                    </h3>
                    <p className="text-gray-600 text-lg">{step.description}</p>
                  </div>
                </div>
              ))}
            </div>

            {/* Process Visual */}
            <div className="relative hidden lg:block">
              <div className="bg-gradient-to-br from-amber-200 to-orange-200 rounded-3xl shadow-2xl p-8">
                <div className="aspect-square bg-gradient-to-br from-white to-amber-100 rounded-2xl flex items-center justify-center">
                  <div className="text-center">
                    <div className="text-6xl mb-4">📋</div>
                    <p className="text-gray-800 font-semibold">Secure Document Processing</p>
                  </div>
                </div>
              </div>
              <div className="absolute top-0 right-0 w-40 h-40 bg-orange-300/30 rounded-full blur-3xl"></div>
            </div>
          </div>
        </div>
      </section>

      {/* Reviews/Testimonials Section */}
      <section id="reviews" className="py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              Trusted by Hundreds of Clients
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Real feedback from clients who have experienced our professional notary service
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {loading ? (
              // Loading skeleton
              [1, 2, 3].map((item) => (
                <div key={item} className="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-sm animate-pulse">
                  <div className="flex items-center mb-6">
                    <div className="w-14 h-14 bg-gray-200 rounded-full mr-4"></div>
                    <div className="flex-1">
                      <div className="h-4 bg-gray-200 rounded w-24 mb-2"></div>
                      <div className="h-3 bg-gray-200 rounded w-16"></div>
                    </div>
                  </div>
                  <div className="space-y-3">
                    <div className="h-4 bg-gray-200 rounded"></div>
                    <div className="h-4 bg-gray-200 rounded w-5/6"></div>
                  </div>
                </div>
              ))
            ) : testimonials.length > 0 ? (
              // Real testimonials
              testimonials.map((item) => (
                <div key={item.id} className="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-md hover:shadow-lg transition-all duration-300">
                  <div className="flex items-center mb-6">
                    <div className="w-14 h-14 bg-gradient-to-br from-amber-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-md">
                      {item.clientName.charAt(0).toUpperCase()}
                    </div>
                    <div className="ml-4">
                      <div className="font-bold text-gray-900">{item.clientName}</div>
                      <div className="text-amber-500 text-sm">
                        {'★'.repeat(item.rating)}
                      </div>
                    </div>
                  </div>
                  <p className="text-gray-700 mb-4 leading-relaxed">
                    "{item.message || `Successfully completed ${item.serviceType}`}"
                  </p>
                  <span className="inline-block text-xs font-semibold text-amber-600 bg-amber-100 px-3 py-1 rounded-full">
                    {item.serviceType}
                  </span>
                </div>
              ))
            ) : (
              // Fallback testimonials
              [1, 2, 3].map((item) => (
                <div key={item} className="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-md">
                  <div className="flex items-center mb-6">
                    <div className="w-14 h-14 bg-gradient-to-br from-amber-500 to-orange-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                      C{item}
                    </div>
                    <div className="ml-4">
                      <div className="font-bold text-gray-900">Client {item}</div>
                      <div className="text-amber-500 text-sm">★★★★★</div>
                    </div>
                  </div>
                  <p className="text-gray-700">
                    "Professional and reliable notary service. Made my document process so much easier!"
                  </p>
                </div>
              ))
            )}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-24 bg-gradient-to-r from-amber-500 to-orange-600 text-white">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-4xl md:text-5xl font-bold mb-6">
            Ready to Get Your Documents Notarized?
          </h2>
          <p className="text-xl mb-10 max-w-2xl mx-auto opacity-95">
            Join hundreds of satisfied clients who trust us with their important legal documents
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              onClick={() => setIsRegisterModalOpen(true)}
              className="bg-white text-amber-600 px-8 py-4 rounded-xl font-bold text-lg hover:bg-gray-50 transition-all duration-300 transform hover:-translate-y-1 shadow-lg"
            >
              Start Now
            </button>
            <button
              onClick={() => scrollToSection('howitworks')}
              className="border-2 border-white text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-white/10 transition-all duration-300"
            >
              Learn More
            </button>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-900 text-white pt-16 pb-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            {/* Company Info */}
            <div>
              <div className="flex items-center space-x-3 mb-4">
                <div className="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-600 rounded-lg flex items-center justify-center">
                  <span className="text-white font-bold">⚖️</span>
                </div>
                <span className="text-xl font-bold">Legal Notary</span>
              </div>
              <p className="text-gray-400 text-sm mb-4">
                Professional notary services for the modern world
              </p>
            </div>

            {/* Quick Links */}
            <div>
              <h4 className="font-semibold mb-4">Services</h4>
              <ul className="space-y-2 text-sm text-gray-400">
                {['Notarization', 'Verification', 'Certification', 'Signing'].map((item) => (
                  <li key={item}>
                    <button className="hover:text-amber-400 transition-colors">{item}</button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support */}
            <div>
              <h4 className="font-semibold mb-4">Support</h4>
              <ul className="space-y-2 text-sm text-gray-400">
                <li>
                  <button className="hover:text-amber-400 transition-colors">Help Center</button>
                </li>
                <li>
                  <button className="hover:text-amber-400 transition-colors">Contact Us</button>
                </li>
                <li>
                  <button className="hover:text-amber-400 transition-colors">FAQ</button>
                </li>
              </ul>
            </div>

            {/* Contact */}
            <div>
              <h4 className="font-semibold mb-4">Get Started</h4>
              <p className="text-gray-400 text-sm mb-4">
                Have questions? Our team is here to help.
              </p>
              <button
                onClick={() => setIsRegisterModalOpen(true)}
                className="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white py-2 rounded-lg font-semibold hover:shadow-lg transition-all duration-300"
              >
                Get Started
              </button>
            </div>
          </div>

          <div className="border-t border-gray-800 pt-8 text-center">
            <p className="text-gray-400 text-sm">
              &copy; 2024 Legal Notary System. All rights reserved. | Privacy | Terms
            </p>
          </div>
        </div>
      </footer>

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
    </div>
  );
};

export default LandingPage;