const LoadingSpinner = ({ size = 'md', label = 'Loading...' }) => {
  const sizeClasses = {
    xs: 'h-3 w-3 border-2',
    sm: 'h-4 w-4 border-2',
    md: 'h-8 w-8 border-[3px]',
    lg: 'h-12 w-12 border-4',
    xl: 'h-16 w-16 border-4'
  };

  return (
    <div className="flex justify-center items-center" role="status" aria-label={label} aria-live="polite">
      <div 
        className={`animate-spin rounded-full border-amber-500/30 border-t-amber-400 ${sizeClasses[size]}`}
        aria-hidden="true"
      ></div>
      <span className="sr-only">{label}</span>
    </div>
  );
};

export default LoadingSpinner;