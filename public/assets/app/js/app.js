/**
 * Precision Console — theme, sidebar collapse, active nav (Bootstrap 6 ESM).
 */
const THEME_KEY = "app-theme";
const SIDEBAR_KEY = "app-sidebar-collapsed";

function preferredTheme() {
  const stored = localStorage.getItem(THEME_KEY);
  if (stored === "light" || stored === "dark") {
    return stored;
  }
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

function applyTheme(theme) {
  document.documentElement.setAttribute("data-bs-theme", theme);
  localStorage.setItem(THEME_KEY, theme);
  document.querySelectorAll("[data-theme-icon]").forEach((el) => {
    el.classList.toggle("bi-moon-stars", theme === "light");
    el.classList.toggle("bi-sun", theme === "dark");
  });
}

function initTheme() {
  applyTheme(preferredTheme());
  document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const next =
        document.documentElement.getAttribute("data-bs-theme") === "dark"
          ? "light"
          : "dark";
      applyTheme(next);
    });
  });
}

function applySidebarCollapsed(collapsed) {
  const shell = document.querySelector(".app-shell");
  if (!shell) {
    return;
  }
  shell.classList.toggle("is-sidebar-collapsed", collapsed);
  localStorage.setItem(SIDEBAR_KEY, collapsed ? "1" : "0");
  document.querySelectorAll("[data-sidebar-collapse]").forEach((btn) => {
    btn.setAttribute(
      "aria-label",
      collapsed ? "Expandir menu" : "Recolher menu"
    );
  });
  document.querySelectorAll("[data-sidebar-collapse-icon]").forEach((el) => {
    el.classList.toggle("bi-layout-sidebar-inset", !collapsed);
    el.classList.toggle("bi-layout-sidebar", collapsed);
  });
}

function initSidebarCollapse() {
  const collapsed = localStorage.getItem(SIDEBAR_KEY) === "1";
  applySidebarCollapsed(collapsed);
  document.querySelectorAll("[data-sidebar-collapse]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const shell = document.querySelector(".app-shell");
      const next = !shell?.classList.contains("is-sidebar-collapsed");
      applySidebarCollapsed(next);
    });
  });
}

function markActiveNav() {
  const path = window.location.pathname.replace(/\/$/, "") || "/";
  document.querySelectorAll("[data-nav-link]").forEach((link) => {
    const href = link.getAttribute("href") || "";
    const normalized = href.replace(/\/$/, "") || "/";
    let matchPath = path;
    // Aliases legados de perfis
    if (path === "/roles" || path.startsWith("/roles/") || path === "/permissions") {
      matchPath = "/perfis";
    }
    const active =
      matchPath === normalized ||
      (normalized !== "/" &&
        normalized !== "/dashboard" &&
        matchPath.startsWith(normalized));
    const dashActive =
      (normalized === "/dashboard" || normalized === "/") &&
      (path === "/" || path === "/dashboard");
    link.classList.toggle("is-active", active || dashActive);
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initTheme();
  initSidebarCollapse();
  markActiveNav();
});
