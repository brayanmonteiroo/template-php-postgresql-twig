/**
 * Precision Console — theme, sidebar collapse, active nav, confirm dialog (Bootstrap 6 ESM).
 */
import { Dialog } from "/assets/bootstrap/bootstrap.bundle.min.js";

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

function initConfirmDialog() {
  const dialogEl = document.getElementById("confirmDialog");
  const titleEl = document.getElementById("confirmDialogTitle");
  const messageEl = document.getElementById("confirmDialogMessage");
  const confirmBtn = document.getElementById("confirmDialogConfirm");
  if (!dialogEl || !titleEl || !messageEl || !confirmBtn) {
    return;
  }

  const dialog = Dialog.getOrCreateInstance(dialogEl);
  let pendingForm = null;

  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute("data-confirm")) {
        return;
      }
      if (form.dataset.confirmReady === "1") {
        delete form.dataset.confirmReady;
        return;
      }
      event.preventDefault();
      pendingForm = form;
      titleEl.textContent =
        form.getAttribute("data-confirm-title") || "Confirmar";
      messageEl.textContent =
        form.getAttribute("data-confirm-message") ||
        "Tem certeza que deseja continuar?";
      confirmBtn.textContent =
        form.getAttribute("data-confirm-confirm") || "Excluir";
      dialog.show();
    },
    true
  );

  confirmBtn.addEventListener("click", () => {
    if (!pendingForm) {
      return;
    }
    const form = pendingForm;
    pendingForm = null;
    form.dataset.confirmReady = "1";
    dialog.hide();
    if (typeof form.requestSubmit === "function") {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });

  dialogEl.addEventListener("hide.bs.dialog", () => {
    pendingForm = null;
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initTheme();
  initSidebarCollapse();
  markActiveNav();
  initConfirmDialog();
});
