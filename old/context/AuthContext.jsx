'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

import { api } from '@/app/utils/api';

// Create the authentication context
const AuthContext = createContext();

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const router = useRouter();

  // Load user on initial render
  useEffect(() => {
    // Only run on client side
    if (typeof window === 'undefined') return;

    async function loadUserFromToken() {
      setLoading(true);
      setError(null);

      try {
        // Check if we have a token stored
        if (api.isAuthenticated()) {
          // Fetch the current user data
          const { user } = await api.getCurrentUser();
          setUser(user);
        }
      } catch (err) {
        console.error('Failed to load user:', err);
        setError('Session expired. Please login again.');
        // Clear any invalid token
        api.logout();
      } finally {
        setLoading(false);
      }
    }

    loadUserFromToken();
  }, []);

  // Login handler
  const login = async (username, password) => {
    setLoading(true);
    setError(null);

    try {
      const data = await api.login(username, password);
      setUser(data.user);
      return data;
    } catch (err) {
      setError(err.message || 'Login failed');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Register handler
  const register = async (userData) => {
    setLoading(true);
    setError(null);

    try {
      const data = await api.register(userData);
      setUser(data.user);
      return data;
    } catch (err) {
      setError(err.message || 'Registration failed');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Logout handler
  const logout = () => {
    api.logout();
    setUser(null);
    router.push('/login');
  };

  // Update user profile handler
  const updateProfile = async (userData) => {
    setLoading(true);
    setError(null);

    try {
      const data = await api.updateProfile(userData);
      setUser(data.user);
      return data;
    } catch (err) {
      setError(err.message || 'Failed to update profile');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Upload profile image handler
  const uploadProfileImage = async (imageFile, metadata) => {
    setLoading(true);
    setError(null);

    try {
      const data = await api.uploadProfileImage(imageFile, metadata);
      setUser(data.user);
      return data;
    } catch (err) {
      setError(err.message || 'Failed to upload profile image');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Delete profile image handler
  const deleteProfileImage = async () => {
    setLoading(true);
    setError(null);

    try {
      const data = await api.deleteProfileImage();
      setUser(data.user);
      return data;
    } catch (err) {
      setError(err.message || 'Failed to delete profile image');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Check if user has role
  const hasRole = (role) => {
    if (!user) return false;
    return user.roles?.includes(role);
  };

  // Check if user has permission
  const hasPermission = (permission) => {
    if (!user) return false;
    return user.roles?.includes(`PERMISSION_${permission.toUpperCase()}`);
  };

  // Expose the auth context values
  const value = {
    user,
    loading,
    error,
    login,
    register,
    logout,
    updateProfile,
    uploadProfileImage,
    deleteProfileImage,
    isAuthenticated: !!user,
    hasRole,
    hasPermission
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// Custom hook to use the auth context
export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}