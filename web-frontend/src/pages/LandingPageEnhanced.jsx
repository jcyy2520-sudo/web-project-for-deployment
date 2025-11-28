import { useState } from 'react';
import { 
  CheckCircleIcon, 
  StarIcon,
  ShieldCheckIcon,
  DocumentCheckIcon,
  ClockIcon,
  UserGroupIcon,
  SparklesIcon,
  ArrowRightIcon
} from '@heroicons/react/24/outline';
import { useNavigate } from 'react-router-dom';

const LandingPageEnhanced = () => {
  const navigate = useNavigate();
  const [expandedFaq, setExpandedFaq] = useState(null);

  const features = [
    {
      icon: DocumentCheckIcon,
      title: 'Digital Notarization',
      description: 'Quick and secure notarization services online'
    },
    {
      icon: ClockIcon,
      title: 'Available Always',
      description: 'Book appointments anytime that works for you'
    },
    {
      icon: ShieldCheckIcon,
      title: 'Secure & Verified',
      description: 'Enterprise-grade security for your documents'
    },
    {
      icon: UserGroupIcon,
      title: 'Expert Notaries',
      description: 'Certified and experienced notary professionals'
    }
  ];

  const testimonials = [
    {
      name: 'Sarah Johnson',
      role: 'Real Estate Agent',
      rating: 5,
      comment: 'Legal Ease made notarization incredibly easy. Highly recommended!'
    },
    {
      name: 'Michael Chen',
      role: 'Business Owner',
      rating: 5,
      comment: 'Professional service with quick turnaround. Great experience.'
    },
    {
      name: 'Emily Davis',
      role: 'Legal Professional',
      rating: 5,
      comment: 'Reliable and trustworthy. Use them for all my notarization needs.'
    }
  ];

  const faqs = [
    {
      question: 'How does online notarization work?',
      answer: 'Our notaries are certified professionals available 8/5. Simply book an appointment, upload your documents, and we\'ll verify your identity and notarize securely online.'
    },
    {
      question: 'Is it legally binding?',
      answer: 'Yes, all our notarizations are legally binding and recognized across all states. We follow strict regulations and maintain complete audit trails.'
    },
    {
      question: 'How long does notarization take?',
      answer: 'Most notarizations are completed within 24 hours. Express service available for urgent requests.'
    },
    {
      question: 'What documents can be notarized?',
      answer: 'We can notarize most types of documents including powers of attorney, affidavits, deeds, contracts, and more. Some restrictions may apply.'
    },
    {
      question: 'What are your pricing options?',
      answer: 'Pricing is transparent and competitive. Contact us for a quote based on your specific needs.'
    }
  ];

  const pricingTiers = [
    {
      name: 'Single Document',
      price: 'Starting at $50',
      features: ['One document', 'Same-day service', 'Digital signature', 'Email delivery'],
      highlighted: false
    },
    {
      name: 'Professional',
      price: 'Starting at $200',
      features: ['Up to 5 documents', 'Express service', 'Priority support', 'Bulk discount'],
      highlighted: true
    },
    {
      name: 'Enterprise',
      price: 'Custom pricing',
      features: ['Unlimited documents', 'Dedicated notary', 'API integration', '24/7 support'],
      highlighted: false
    }
  ];

  const trustBadges = [
    { icon: '✓', label: 'ABA Certified' },
    { icon: '🔒', label: 'SSL Encrypted' },
    { icon: '✓', label: 'ISO 27001' },
    { icon: '⭐', label: 'Top Rated' }
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-950 via-gray-900 to-black">
      {/* Navigation */}
      <nav className="sticky top-0 z-50 bg-gray-950/80 backdrop-blur-sm border-b border-amber-500/20">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <div className="text-2xl font-bold text-amber-400">Legal Ease</div>
          <div className="flex gap-4">
            <button
              onClick={() => navigate('/dashboard')}
              className="px-4 py-2 text-amber-50 hover:text-amber-400 transition-colors"
            >
              Dashboard
            </button>
            <button
              onClick={() => navigate('/?tab=signup')}
              className="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium transition-colors"
            >
              Get Started
            </button>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="max-w-7xl mx-auto px-4 py-20 text-center">
        <div className="space-y-6">
          <div className="inline-flex items-center px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-full">
            <SparklesIcon className="h-4 w-4 text-amber-400 mr-2" />
            <span className="text-xs font-semibold text-amber-400">
              Trusted by 10,000+ Customers
            </span>
          </div>

          <h1 className="text-5xl md:text-6xl font-bold text-amber-50">
            Professional Notary Services
            <span className="block text-amber-400">Made Simple</span>
          </h1>

          <p className="text-lg text-gray-400 max-w-2xl mx-auto">
            Get your documents notarized online in minutes. Secure, legally binding, and available 24/7.
          </p>

          <div className="flex flex-col sm:flex-row gap-4 justify-center pt-4">
            <button
              onClick={() => navigate('/dashboard')}
              className="px-8 py-3 bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white rounded-lg font-bold flex items-center justify-center gap-2 transition-all transform hover:scale-105"
            >
              Book Appointment <ArrowRightIcon className="h-5 w-5" />
            </button>
            <button
              className="px-8 py-3 border border-amber-500/30 text-amber-50 hover:bg-amber-500/10 rounded-lg font-bold transition-colors"
            >
              Learn More
            </button>
          </div>
        </div>
      </section>

      {/* Trust Badges */}
      <section className="bg-amber-500/5 border-y border-amber-500/20 py-8">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {trustBadges.map((badge, idx) => (
              <div key={idx} className="text-center">
                <div className="text-3xl mb-2">{badge.icon}</div>
                <p className="text-xs text-gray-400 font-medium">{badge.label}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="max-w-7xl mx-auto px-4 py-20">
        <div className="text-center mb-16">
          <h2 className="text-4xl font-bold text-amber-50 mb-4">Why Choose Legal Ease?</h2>
          <p className="text-gray-400">Everything you need for professional notarization</p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
          {features.map((feature, idx) => (
            <div
              key={idx}
              className="bg-gray-900 border border-amber-500/20 rounded-lg p-6 hover:border-amber-500/40 transition-all hover:shadow-lg hover:shadow-amber-500/10"
            >
              <feature.icon className="h-12 w-12 text-amber-400 mb-4" />
              <h3 className="text-lg font-semibold text-amber-50 mb-2">{feature.title}</h3>
              <p className="text-sm text-gray-400">{feature.description}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Pricing Section */}
      <section className="max-w-7xl mx-auto px-4 py-20">
        <div className="text-center mb-16">
          <h2 className="text-4xl font-bold text-amber-50 mb-4">Simple, Transparent Pricing</h2>
          <p className="text-gray-400">Choose the plan that fits your needs</p>
        </div>

        <div className="grid md:grid-cols-3 gap-6">
          {pricingTiers.map((tier, idx) => (
            <div
              key={idx}
              className={`rounded-lg p-8 transition-all ${
                tier.highlighted
                  ? 'bg-gradient-to-br from-amber-900/30 to-amber-950/20 border-2 border-amber-500 shadow-xl shadow-amber-500/20 transform scale-105'
                  : 'bg-gray-900 border border-amber-500/20'
              }`}
            >
              {tier.highlighted && (
                <div className="inline-block bg-amber-500/20 border border-amber-500/30 rounded-full px-3 py-1 text-xs font-semibold text-amber-400 mb-4">
                  Most Popular
                </div>
              )}
              <h3 className="text-2xl font-bold text-amber-50 mb-2">{tier.name}</h3>
              <div className="text-3xl font-bold text-amber-400 mb-6">{tier.price}</div>
              <ul className="space-y-3 mb-8">
                {tier.features.map((feature, fIdx) => (
                  <li key={fIdx} className="flex items-center gap-2 text-gray-300">
                    <CheckCircleIcon className="h-5 w-5 text-amber-400" />
                    {feature}
                  </li>
                ))}
              </ul>
              <button className={`w-full py-3 rounded-lg font-bold transition-all ${
                tier.highlighted
                  ? 'bg-amber-600 hover:bg-amber-700 text-white'
                  : 'border border-amber-500/30 text-amber-50 hover:bg-amber-500/10'
              }`}>
                Get Started
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* Testimonials Section */}
      <section className="bg-amber-500/5 border-y border-amber-500/20 py-20">
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-amber-50 mb-4">Loved by Our Customers</h2>
            <p className="text-gray-400">See what people are saying about Legal Ease</p>
          </div>

          <div className="grid md:grid-cols-3 gap-6">
            {testimonials.map((testimonial, idx) => (
              <div key={idx} className="bg-gray-900 border border-amber-500/20 rounded-lg p-6">
                <div className="flex gap-1 mb-4">
                  {[...Array(testimonial.rating)].map((_, i) => (
                    <StarIcon key={i} className="h-5 w-5 text-amber-400 fill-current" />
                  ))}
                </div>
                <p className="text-gray-300 mb-4 italic">"{testimonial.comment}"</p>
                <div>
                  <p className="font-semibold text-amber-50">{testimonial.name}</p>
                  <p className="text-xs text-gray-400">{testimonial.role}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <section className="max-w-7xl mx-auto px-4 py-20">
        <div className="text-center mb-16">
          <h2 className="text-4xl font-bold text-amber-50 mb-4">Frequently Asked Questions</h2>
        </div>

        <div className="max-w-3xl mx-auto space-y-4">
          {faqs.map((faq, idx) => (
            <div
              key={idx}
              className="bg-gray-900 border border-amber-500/20 rounded-lg overflow-hidden"
            >
              <button
                onClick={() => setExpandedFaq(expandedFaq === idx ? null : idx)}
                className="w-full p-6 text-left hover:bg-gray-800/50 transition-colors flex items-center justify-between"
              >
                <span className="font-semibold text-amber-50">{faq.question}</span>
                <span className={`transition-transform ${expandedFaq === idx ? 'rotate-180' : ''}`}>
                  ▼
                </span>
              </button>
              {expandedFaq === idx && (
                <div className="px-6 pb-6 border-t border-amber-500/20 text-gray-400">
                  {faq.answer}
                </div>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* CTA Section */}
      <section className="bg-gradient-to-r from-amber-900/30 to-amber-950/30 border-y border-amber-500/20 py-16">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-4xl font-bold text-amber-50 mb-4">Ready to Get Started?</h2>
          <p className="text-gray-400 mb-8 text-lg">
            Join thousands of satisfied customers. Start your notarization today.
          </p>
          <button
            onClick={() => navigate('/dashboard')}
            className="px-8 py-4 bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white rounded-lg font-bold text-lg transition-all transform hover:scale-105"
          >
            Book Your First Appointment
          </button>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-950 border-t border-gray-800 py-12">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid md:grid-cols-4 gap-8 mb-8">
            <div>
              <h3 className="font-bold text-amber-50 mb-4">Legal Ease</h3>
              <p className="text-xs text-gray-500">Professional notary services online</p>
            </div>
            <div>
              <h4 className="font-semibold text-amber-50 text-sm mb-4">Services</h4>
              <ul className="space-y-2 text-xs text-gray-400">
                <li>Online Notarization</li>
                <li>Document Verification</li>
                <li>Digital Signatures</li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-amber-50 text-sm mb-4">Company</h4>
              <ul className="space-y-2 text-xs text-gray-400">
                <li>About Us</li>
                <li>Contact</li>
                <li>Privacy Policy</li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-amber-50 text-sm mb-4">Support</h4>
              <ul className="space-y-2 text-xs text-gray-400">
                <li>Help Center</li>
                <li>FAQ</li>
                <li>Contact Support</li>
              </ul>
            </div>
          </div>
          <div className="border-t border-gray-800 pt-8 flex flex-col sm:flex-row justify-between items-center text-xs text-gray-500">
            <p>&copy; 2025 Legal Ease. All rights reserved.</p>
            <p>Made with ❤️ by Legal Ease Team</p>
          </div>
        </div>
      </footer>
    </div>
  );
};

export default LandingPageEnhanced;
