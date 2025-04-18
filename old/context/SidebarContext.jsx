"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { isBrowser } from "./../helpers/is-browser";
import { isSmallScreen } from "./../helpers/is-small-screen";

const SidebarContext = createContext({
  isCollapsed: true,
  setCollapsed: () => {},
});

export const SidebarProvider = function ({ children }) {
  const [isCollapsed, setCollapsed] = useState(false); // Default to collapsed on mobile

  // Initialize state from localStorage after component mounts
  useEffect(() => {
    const storedValue = isBrowser()
      ? localStorage.getItem("isSidebarCollapsed")
      : null;

    if (storedValue !== null) {
      setCollapsed(storedValue === "true");
    } else {
      // On first load, collapse sidebar on mobile, expand on desktop
      setCollapsed(isSmallScreen());
    }
  }, []);

  // Update localStorage when isCollapsed changes
  useEffect(() => {
    if (isBrowser()) {
      localStorage.setItem("isSidebarCollapsed", isCollapsed.toString());
    }
  }, [isCollapsed]);

  // Auto-collapse sidebar on small screens when route changes
  useEffect(() => {
    if (isSmallScreen()) {
      setCollapsed(true);
    }
  }, [isBrowser() && window.location.pathname]);

  // Close sidebar when clicking on main content on mobile
  useEffect(() => {
    if (!isBrowser()) return;

    const handleMainContentClick = (event) => {
      const mainContent = document.querySelector("main");
      if (isSmallScreen() && mainContent?.contains(event.target)) {
        setCollapsed(true);
      }
    };

    document.addEventListener("mousedown", handleMainContentClick);
    return () => {
      document.removeEventListener("mousedown", handleMainContentClick);
    };
  }, []);

  return (
    <SidebarContext.Provider
      value={{
        isCollapsed,
        setCollapsed,
      }}
    >
      {children}
    </SidebarContext.Provider>
  );
};

export function useSidebarContext() {
  const context = useContext(SidebarContext);

  if (context === undefined) {
    throw new Error(
      "useSidebarContext should be used within the SidebarProvider!"
    );
  }

  return context;
}