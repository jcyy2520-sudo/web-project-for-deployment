import React from 'react';

/**
 * ProfileAvatar Component - Displays user profile picture or initials fallback
 * @param {Object} props
 * @param {String} props.profilePicture - URL to the profile picture
 * @param {String} props.firstName - User's first name
 * @param {String} props.lastName - User's last name
 * @param {String} props.size - Size class: 'sm', 'md', 'lg', 'xl' (defaults to 'md')
 * @param {String} props.className - Additional CSS classes
 */
const ProfileAvatar = ({
  profilePicture,
  firstName = '',
  lastName = '',
  size = 'md',
  className = ''
}) => {
  const sizeClasses = {
    sm: 'w-8 h-8 text-xs',
    md: 'w-12 h-12 text-sm',
    lg: 'w-16 h-16 text-2xl',
    xl: 'w-24 h-24 text-4xl'
  };

  const getInitials = () => {
    const first = firstName?.[0] || '';
    const last = lastName?.[0] || '';
    return (first + last).toUpperCase() || 'U';
  };

  return (
    <div
      className={`
        rounded-full overflow-hidden border-2 border-amber-400 
        bg-gradient-to-br from-amber-400 to-amber-600 
        flex items-center justify-center text-white font-bold flex-shrink-0
        ${sizeClasses[size]}
        ${className}
      `}
    >
      {profilePicture ? (
        <img
          src={profilePicture}
          alt={`${firstName} ${lastName}`}
          className="w-full h-full object-cover"
        />
      ) : (
        <span>{getInitials()}</span>
      )}
    </div>
  );
};

export default ProfileAvatar;
