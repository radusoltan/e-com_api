"use client"
import {Sidebar, SidebarCollapse, SidebarItem, SidebarItemGroup, SidebarItems} from "flowbite-react"
import { HiArrowSmRight, HiChartPie, HiInbox, HiShoppingBag, HiTable, HiUser, HiViewBoards } from "react-icons/hi"
import { useEffect, useState } from "react"

export const SideBarComponent = ()=>{
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    const syncState = () => {
      const isCollapsed = localStorage.getItem("isSidebarCollapsed") === "true";
      setCollapsed(isCollapsed);
    };

    // inițial
    syncState();

    // actualizare când se primește evenimentul personalizat
    window.addEventListener("sidebar-toggle", syncState);

    return () => {
      window.removeEventListener("sidebar-toggle", syncState);
    };
  }, []);

  return <>
    <Sidebar aria-label="Default sidebar example"
             className="fixed top-0 left-0 z-40 h-screen pt-14 transition-transform -translate-x-full duration-300 ease-in-out bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
             collapsed={collapsed}
    >
      <SidebarItems>
        <SidebarItemGroup>
          <SidebarItem href="#" icon={HiChartPie}>
            Dashboard
          </SidebarItem>
          <SidebarCollapse icon={HiShoppingBag} label="E-commerce">
            <SidebarItem href="#">Products</SidebarItem>
            <SidebarItem href="#">Sales</SidebarItem>
            <SidebarItem href="#">Refunds</SidebarItem>
            <SidebarItem href="#">Shipping</SidebarItem>
          </SidebarCollapse>
          <SidebarItem href="#" icon={HiViewBoards} label="Pro" labelColor="dark">
            Kanban
          </SidebarItem>
          <SidebarItem href="#" icon={HiInbox} label="3">
            Inbox
          </SidebarItem>
          <SidebarItem href="#" icon={HiUser}>
            Users
          </SidebarItem>
          <SidebarItem href="#" icon={HiShoppingBag}>
            Products
          </SidebarItem>
          <SidebarItem href="#" icon={HiArrowSmRight}>
            Sign In
          </SidebarItem>
          <SidebarItem href="#" icon={HiTable}>
            Sign Up
          </SidebarItem>
        </SidebarItemGroup>
      </SidebarItems>
    </Sidebar>
  </>
}

