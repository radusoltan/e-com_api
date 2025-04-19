"use client";

// API base URL

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL;

export const api = {

  get: async (endpoint, options = {}) => {

    console.log(API_BASE_URL);

  },

  login: async fields => {

    const response = await fetch(`${API_BASE_URL}/api/login_check`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(fields)
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(error.message || 'Login failed');
    }

    localStorage.setItem('token', response.token);


  }
}