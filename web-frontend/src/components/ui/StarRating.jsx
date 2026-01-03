import { useState } from 'react';

const StarRating = ({ value, rating, onChange, size = 'md', readOnly = false }) => {
  // Support both 'value' and 'rating' props for flexibility
  const currentValue = value ?? rating ?? 0;
  const [hovered, setHovered] = useState(0);

  const sizes = {
    sm: { star: 16, gap: 4 },
    md: { star: 24, gap: 6 },
    lg: { star: 32, gap: 8 }
  };

  const { star, gap } = sizes[size];

  const handleStarClick = (starNum) => {
    if (!readOnly && onChange) {
      onChange(starNum);
    }
  };

  return (
    <div className="flex items-center" style={{ gap: `${gap}px` }}>
      {[1, 2, 3, 4, 5].map((starNum) => (
        <button
          key={starNum}
          type="button"
          onClick={() => handleStarClick(starNum)}
          onMouseEnter={() => !readOnly && setHovered(starNum)}
          onMouseLeave={() => !readOnly && setHovered(0)}
          disabled={readOnly}
          className={`transition-all duration-200 ${readOnly ? 'cursor-default' : 'cursor-pointer hover:scale-110'}`}
        >
          <svg
            width={star}
            height={star}
            viewBox="0 0 24 24"
            fill={(hovered || currentValue) >= starNum ? 'currentColor' : 'none'}
            stroke="currentColor"
            strokeWidth="2"
            className={`transition-all duration-200 ${
              (hovered || currentValue) >= starNum
                ? 'text-amber-400'
                : 'text-gray-300'
            }`}
          >
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
        </button>
      ))}
      {currentValue > 0 && !readOnly && (
        <span className="text-sm text-gray-400 ml-2">{currentValue}/5</span>
      )}
    </div>
  );
};

export default StarRating;
