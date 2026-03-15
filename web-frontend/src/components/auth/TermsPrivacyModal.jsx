import { useState } from 'react';
import Modal from '../Modal';

const TermsPrivacyModal = ({ isOpen, onClose, initialTab = 'terms', isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState(initialTab);

  const tabs = [
    { id: 'terms', label: 'Terms & Conditions' },
    { id: 'privacy', label: 'Privacy Policy' },
  ];

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={activeTab === 'terms' ? 'Terms & Conditions' : 'Privacy Policy'} size="lg" isDarkMode={isDarkMode}>
      {/* Tab Switcher */}
      <div className={`flex border-b mb-4 ${isDarkMode ? 'border-amber-500/20' : 'border-gray-200'}`}>
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setActiveTab(tab.id)}
            className={`flex-1 pb-2 text-xs font-medium transition-all border-b-2 ${
              activeTab === tab.id
                ? isDarkMode
                  ? 'border-amber-500 text-amber-400'
                  : 'border-blue-600 text-blue-600'
                : isDarkMode
                  ? 'border-transparent text-gray-500 hover:text-gray-300'
                  : 'border-transparent text-gray-400 hover:text-gray-600'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Content */}
      <div className={`overflow-y-auto max-h-[60vh] pr-1 text-sm leading-relaxed space-y-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
        {activeTab === 'terms' && <TermsContent isDarkMode={isDarkMode} />}
        {activeTab === 'privacy' && <PrivacyContent isDarkMode={isDarkMode} />}
      </div>

      {/* Close Button */}
      <div className="mt-4 flex justify-end">
        <button
          type="button"
          onClick={onClose}
          className={`px-5 py-2 rounded-lg text-sm font-medium transition-all ${
            isDarkMode
              ? 'bg-amber-500 text-gray-900 hover:bg-amber-400'
              : 'bg-blue-600 text-white hover:bg-blue-700'
          }`}
        >
          I Understand
        </button>
      </div>
    </Modal>
  );
};

const SectionTitle = ({ children, isDarkMode }) => (
  <h3 className={`text-sm font-semibold mt-4 mb-1 ${isDarkMode ? 'text-amber-300' : 'text-gray-900'}`}>{children}</h3>
);

const TermsContent = ({ isDarkMode }) => (
  <>
    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Last updated: March 9, 2026</p>

    <SectionTitle isDarkMode={isDarkMode}>1. Acceptance of Terms</SectionTitle>
    <p>By accessing and using this appointment management system ("Service"), you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the Service.</p>

    <SectionTitle isDarkMode={isDarkMode}>2. Account Registration</SectionTitle>
    <p>You must provide accurate, complete, and current information when creating an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. Notify us immediately of any unauthorized use.</p>

    <SectionTitle isDarkMode={isDarkMode}>3. Use of Service</SectionTitle>
    <p>You agree to use the Service only for lawful purposes and in accordance with these Terms. You shall not:</p>
    <ul className="list-disc pl-5 space-y-1">
      <li>Use the Service for any fraudulent or unlawful purpose</li>
      <li>Attempt to gain unauthorized access to any part of the Service</li>
      <li>Interfere with or disrupt the Service or its servers</li>
      <li>Upload malicious content or code</li>
      <li>Impersonate another person or entity</li>
    </ul>

    <SectionTitle isDarkMode={isDarkMode}>4. Appointments & Bookings</SectionTitle>
    <p>Appointment bookings are subject to availability. We reserve the right to cancel or reschedule appointments when necessary. Users are expected to attend booked appointments or cancel in advance. Repeated no-shows may result in account restrictions.</p>

    <SectionTitle isDarkMode={isDarkMode}>5. Payments & Fees</SectionTitle>
    <p>Applicable fees for services will be clearly displayed before confirmation. All payments are processed securely. Refund policies are subject to the specific service terms and applicable laws.</p>

    <SectionTitle isDarkMode={isDarkMode}>6. Intellectual Property</SectionTitle>
    <p>All content, features, and functionality of the Service are owned by us and are protected by copyright, trademark, and other intellectual property laws.</p>

    <SectionTitle isDarkMode={isDarkMode}>7. Limitation of Liability</SectionTitle>
    <p>The Service is provided "as is" without warranties of any kind. We shall not be liable for any indirect, incidental, special, or consequential damages arising from the use of the Service.</p>

    <SectionTitle isDarkMode={isDarkMode}>8. Account Termination</SectionTitle>
    <p>We reserve the right to suspend or terminate your account if you violate these Terms or engage in activity that may harm the Service or other users. You may also request account deletion by contacting support.</p>

    <SectionTitle isDarkMode={isDarkMode}>9. Changes to Terms</SectionTitle>
    <p>We may update these Terms from time to time. Continued use of the Service after changes constitutes acceptance of the revised Terms. We will notify users of significant changes via email or in-app notice.</p>

    <SectionTitle isDarkMode={isDarkMode}>10. Contact</SectionTitle>
    <p>If you have questions about these Terms, please contact us through the system's support features or the chatbot assistant.</p>
  </>
);

const PrivacyContent = ({ isDarkMode }) => (
  <>
    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Last updated: March 9, 2026</p>

    <SectionTitle isDarkMode={isDarkMode}>1. Information We Collect</SectionTitle>
    <p>We collect the following types of information:</p>
    <ul className="list-disc pl-5 space-y-1">
      <li><strong>Personal information:</strong> name, email address, phone number, and address provided during registration</li>
      <li><strong>Account information:</strong> username and encrypted password</li>
      <li><strong>Appointment data:</strong> booking history, service preferences, and scheduling information</li>
      <li><strong>Usage data:</strong> interactions with the chatbot, pages visited, and feature usage for service improvement</li>
    </ul>

    <SectionTitle isDarkMode={isDarkMode}>2. How We Use Your Information</SectionTitle>
    <p>Your information is used to:</p>
    <ul className="list-disc pl-5 space-y-1">
      <li>Provide and manage your account and appointments</li>
      <li>Communicate with you regarding bookings, updates, and notifications</li>
      <li>Improve our services, including AI chatbot responses</li>
      <li>Ensure security and prevent fraud</li>
      <li>Comply with legal obligations</li>
    </ul>

    <SectionTitle isDarkMode={isDarkMode}>3. Data Storage & Security</SectionTitle>
    <p>Your data is stored securely using industry-standard encryption and access controls. We implement technical and organizational measures to protect your personal information from unauthorized access, alteration, or destruction.</p>

    <SectionTitle isDarkMode={isDarkMode}>4. Data Sharing</SectionTitle>
    <p>We do not sell your personal information. We may share data only:</p>
    <ul className="list-disc pl-5 space-y-1">
      <li>With authorized staff who need it to provide services</li>
      <li>When required by law or legal process</li>
      <li>To protect the rights, safety, or property of our users or the public</li>
    </ul>

    <SectionTitle isDarkMode={isDarkMode}>5. Chatbot Conversations</SectionTitle>
    <p>Conversations with the AI chatbot may be stored to improve service quality. Chat data is associated with your account and is not shared with third parties. You may clear your chat history at any time.</p>

    <SectionTitle isDarkMode={isDarkMode}>6. Cookies & Local Storage</SectionTitle>
    <p>We use local storage and cookies to maintain your session, remember preferences, and improve your experience. You can manage these through your browser settings.</p>

    <SectionTitle isDarkMode={isDarkMode}>7. Your Rights</SectionTitle>
    <p>You have the right to:</p>
    <ul className="list-disc pl-5 space-y-1">
      <li>Access your personal data</li>
      <li>Request correction of inaccurate data</li>
      <li>Request deletion of your account and data</li>
      <li>Withdraw consent for non-essential data processing</li>
    </ul>

    <SectionTitle isDarkMode={isDarkMode}>8. Data Retention</SectionTitle>
    <p>We retain your personal information for as long as your account is active or as needed to provide services. After account deletion, data may be retained in anonymized form for analytics purposes.</p>

    <SectionTitle isDarkMode={isDarkMode}>9. Children's Privacy</SectionTitle>
    <p>The Service is not intended for users under 13 years of age. We do not knowingly collect personal information from children.</p>

    <SectionTitle isDarkMode={isDarkMode}>10. Changes to This Policy</SectionTitle>
    <p>We may update this Privacy Policy periodically. We will notify you of material changes via email or in-app notification.</p>

    <SectionTitle isDarkMode={isDarkMode}>11. Contact</SectionTitle>
    <p>For privacy-related inquiries, please contact us through the system's support features or the chatbot assistant.</p>
  </>
);

export default TermsPrivacyModal;
