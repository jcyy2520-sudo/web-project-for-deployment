import React from 'react';

const LoadingSpinner = ({ size = 'md', className = '' }) => {
  const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-8 h-8',
    lg: 'w-12 h-12'
  };

  return (
    <div className={`flex justify-center items-center ${className}`}>
      <div 
        className={`
          ${sizeClasses[size]} 
          border-4 border-amber-500 border-t-transparent 
          rounded-full animate-spin
        `}
      />
    </div>
  );
};

export default LoadingSpinner;