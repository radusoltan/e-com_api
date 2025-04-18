"use client";

import { Sidebar, SidebarItem, SidebarItemGroup, SidebarItems } from "flowbite-react";
import { BiBuoy } from "react-icons/bi";
import { HiArrowSmRight, HiChartPie, HiInbox, HiShoppingBag, HiTable, HiUser, HiViewBoards } from "react-icons/hi";
import { useSidebarContext } from "@/app/context/SidebarContext";
import {logout} from "@/app/actions/auth";

export function SidebarComponent() {
  const { isCollapsed } = useSidebarContext();

  return (
    <Sidebar
      aria-label="Admin sidebar"
      collapsed={isCollapsed}
      className="pt-16"
    >
      <SidebarItems>
        <SidebarItemGroup>
          <SidebarItem href="/admin/dashboard" icon={HiChartPie}>
            Dashboard
          </SidebarItem>
          <SidebarItem href="/admin/orders" icon={HiViewBoards}>
            Orders
          </SidebarItem>
          <SidebarItem href="/admin/messages" icon={HiInbox}>
            Messages
          </SidebarItem>
          <SidebarItem href="/admin/users" icon={HiUser}>
            Users
          </SidebarItem>
          <SidebarItem href="/admin/products" icon={HiShoppingBag}>
            Products
          </SidebarItem>
        </SidebarItemGroup>
        <SidebarItemGroup>
          <SidebarItem href="/admin/settings" icon={HiTable}>
            Settings
          </SidebarItem>
          <SidebarItem href="/help" icon={BiBuoy}>
            Help
          </SidebarItem>
          <form action={logout}>
            <button
              type="submit"
              className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors"
            >
              Logout
            </button>
          </form>
        </SidebarItemGroup>
      </SidebarItems>
    </Sidebar>
  );
}