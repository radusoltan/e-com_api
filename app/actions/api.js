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

    const data = await response.json()

    if (!response.ok) {
      return {
        success: false,
        error: data.error || 'Authentication failed'
      }
    }

    // Parse the expiration date from the response
    const expiresAt = new Date(data.expires_at)


  }
}