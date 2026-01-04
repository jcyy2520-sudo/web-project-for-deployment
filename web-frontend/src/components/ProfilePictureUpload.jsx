import React, { useState, useRef } from 'react';
import { XMarkIcon, CameraIcon } from '@heroicons/react/24/solid';

const ProfilePictureUpload = ({ currentImage, user, onUploadSuccess, onDeleteSuccess }) => {
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [preview, setPreview] = useState(currentImage || null);
  const fileInputRef = useRef(null);

  const handleFileSelect = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      setError('File size must be less than 5MB');
      return;
    }

    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      setError('Please upload a valid image file (JPEG, PNG, GIF, or WebP)');
      return;
    }

    // Show preview
    const reader = new FileReader();
    reader.onload = (event) => {
      setPreview(event.target.result);
    };
    reader.readAsDataURL(file);

    // Upload file
    await uploadProfilePicture(file);
  };

  const uploadProfilePicture = async (file) => {
    setIsUploading(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('profile_picture', file);

      const response = await fetch('/api/profile/picture', {
        method: 'POST',
        body: formData,
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to upload profile picture');
      }

      setSuccess('Profile picture uploaded successfully');
      setError(null);
      onUploadSuccess?.(data.profile_picture);

      // Clear success message after 3 seconds
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.message);
      setSuccess(null);
      // Reset preview on error
      setPreview(currentImage || null);
    } finally {
      setIsUploading(false);
    }
  };

  const handleDeletePicture = async () => {
    if (!window.confirm('Are you sure you want to remove your profile picture?')) {
      return;
    }

    setIsUploading(true);
    setError(null);

    try {
      const response = await fetch('/api/profile/picture', {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to delete profile picture');
      }

      setSuccess('Profile picture removed');
      setError(null);
      setPreview(null);
      onDeleteSuccess?.();

      // Clear success message after 3 seconds
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.message);
      setSuccess(null);
    } finally {
      setIsUploading(false);
    }
  };

  const triggerFileInput = () => {
    fileInputRef.current?.click();
  };

  const getInitials = () => {
    if (user?.first_name && user?.last_name) {
      return (user.first_name[0] + user.last_name[0]).toUpperCase();
    } else if (user?.first_name) {
      return user.first_name[0].toUpperCase();
    }
    return 'U';
  };

  return (
    <div className="flex flex-col items-center gap-4">
      {/* Profile Picture Container */}
      <div className="relative">
        {/* Image Circle */}
        <div className="relative w-24 h-24 rounded-full overflow-hidden border-4 border-amber-400 bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0 group">
          {preview ? (
            <img
              src={preview}
              alt="Profile preview"
              className="w-full h-full object-cover"
            />
          ) : (
            <span className="text-white text-4xl font-bold">{getInitials()}</span>
          )}

          {/* Camera Icon Overlay on Hover */}
          <button
            onClick={triggerFileInput}
            disabled={isUploading}
            className="absolute inset-0 bg-black/0 hover:bg-black/40 rounded-full flex items-center justify-center transition-all duration-200 group-hover:bg-black/40 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
          >
            <CameraIcon className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
          </button>
        </div>

        {/* Delete Button - shows only if there's a profile picture */}
        {preview && (
          <button
            onClick={handleDeletePicture}
            disabled={isUploading}
            className="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 disabled:bg-gray-400 rounded-full p-1.5 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            title="Remove profile picture"
          >
            <XMarkIcon className="w-4 h-4 text-white" />
          </button>
        )}
      </div>

      {/* Hidden File Input */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        onChange={handleFileSelect}
        disabled={isUploading}
        className="hidden"
      />

      {/* Upload Button */}
      <button
        onClick={triggerFileInput}
        disabled={isUploading}
        className="px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-400 text-white font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
      >
        {isUploading ? 'Uploading...' : preview ? 'Change Photo' : 'Upload Photo'}
      </button>

      {/* Info Text */}
      <p className="text-sm text-gray-500 dark:text-gray-400 text-center">
        JPG, PNG, GIF, or WebP • Max 5MB
      </p>

      {/* Error Message */}
      {error && (
        <div className="w-full p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-600 dark:text-red-400 text-sm">
          {error}
        </div>
      )}

      {/* Success Message */}
      {success && (
        <div className="w-full p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-600 dark:text-green-400 text-sm">
          {success}
        </div>
      )}
    </div>
  );
};

export default ProfilePictureUpload;
