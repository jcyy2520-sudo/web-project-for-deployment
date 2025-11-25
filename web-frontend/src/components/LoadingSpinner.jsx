const LoadingSpinner = ({ size = 'md' }) => {
  const sizeClasses = {
    sm: 'h-4 w-4 border-2',
    md: 'h-8 w-8 border-3',
    lg: 'h-12 w-12 border-4',
    xl: 'h-16 w-16 border-4'
  };

  return (
    <div className="flex justify-center items-center">
      <div 
        className={`animate-spin rounded-full border-amber-500/30 border-t-amber-400 ${sizeClasses[size]}`}
        style={{ 
          animation: 'spin 1s linear infinite',
          background: 'conic-gradient(from 0deg, transparent, transparent 45deg, rgba(245, 158, 11, 0.1) 45deg, rgba(245, 158, 11, 0.1) 315deg, transparent 315deg)'
        }}
      ></div>
    </div>
  );
};

export default LoadingSpinner;