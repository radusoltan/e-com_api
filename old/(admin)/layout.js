"use client"

import {SidebarProvider, useSidebarContext} from "@/app/context/SidebarContext";
import "@/app/globals.css"
import {SidebarComponent} from "@/app/ui/admin/components/SideBar";
import Header from "@/app/ui/admin/components/Header";

// Inner component that uses the sidebar context
const AdminLayoutContent = ({ children }) => {

  return (
    <div className="flex flex-col min-h-screen">
      <Header />

      <div className="flex flex-1">
        <SidebarComponent />
        <main className={`flex-1 p-4 transition-all`}>
          {children}
        </main>
      </div>
    </div>
  );
};

// Outer component that provides the context
const AdminLayout = ({ children }) => {
  return (
    <SidebarProvider>
      <AdminLayoutContent>{children}</AdminLayoutContent>
    </SidebarProvider>
  );
};

export default AdminLayout;