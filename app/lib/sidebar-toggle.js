export function toggleSidebarCollapsed() {
  const isCollapsed = localStorage.getItem("isSidebarCollapsed") === "true";
  const newValue = (!isCollapsed).toString();
  localStorage.setItem("isSidebarCollapsed", newValue);

  // declanșează eveniment personalizat
  window.dispatchEvent(new Event("sidebar-toggle"));
}