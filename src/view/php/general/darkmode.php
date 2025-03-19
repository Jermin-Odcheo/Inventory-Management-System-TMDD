<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modern Theme Toggle</title>
  <style>
    /* Design tokens - Light theme (default) */
    :root {
      /* Core colors */
      --bg-primary: #f8fafc;
      --bg-secondary: #ffffff;
      --text-primary: #0f172a;
      --text-secondary: #64748b;
      --accent: #06b6d4;
      
      /* UI elements */
      --toggle-bg: #e2e8f0;
      --toggle-active: #06b6d4;
      --toggle-handle: #ffffff;
      --toggle-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* Dark theme */
    [data-theme="dark"] {
      --bg-primary: #0f172a;
      --bg-secondary: #1e293b;
      --text-primary: #f1f5f9;
      --text-secondary: #94a3b8;
      --accent: #22d3ee;
      
      --toggle-bg: #334155;
      --toggle-active: #22d3ee;
      --toggle-handle: #f1f5f9;
      --toggle-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .theme-card {
      background-color: var(--bg-secondary);
      padding: 24px 32px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .theme-status {
      font-size: 16px;
      font-weight: 500;
      color: var(--text-secondary);
      margin-bottom: 8px;
      letter-spacing: -0.01em;
    }

    .theme-label {
      font-weight: 600;
      color: var(--text-primary);
      display: inline-block;
      transition: color 0.3s ease;
    }

    .toggle-wrapper {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .toggle-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--text-secondary);
    }

    .theme-toggle {
      position: relative;
      width: 52px;
      height: 28px;
      border-radius: 28px;
      background-color: var(--toggle-bg);
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      display: flex;
      align-items: center;
      padding: 2px;
      border: none;
      overflow: hidden;
    }

    .toggle-handle {
      position: absolute;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background-color: var(--toggle-handle);
      box-shadow: var(--toggle-shadow);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      z-index: 2;
      left: 3px;
    }

    [data-theme="dark"] .toggle-handle {
      transform: translateX(24px);
    }

    .theme-toggle[data-active="true"] {
      background-color: var(--toggle-active);
    }

    .toggle-icons {
      position: relative;
      width: 100%;
      height: 100%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 6px;
      box-sizing: border-box;
    }

    .toggle-icon {
      width: 14px;
      height: 14px;
      opacity: 0.7;
      transition: opacity 0.3s ease;
      z-index: 1;
    }

    /* Improve focus states for accessibility */
    .theme-toggle:focus-visible {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }

    /* Subtle hover effect */
    .theme-toggle:hover .toggle-handle {
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
    }
  </style>
</head>
<body>
  <div class="theme-card">
    <div class="theme-status">
      Current appearance: <span id="mode-text" class="theme-label">Light</span>
    </div>
    <div class="toggle-wrapper">
      <span class="toggle-label">Light</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" data-active="false">
        <div class="toggle-handle"></div>
        <div class="toggle-icons">
          <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />
          </svg>
          <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd" />
          </svg>
        </div>
      </button>
      <span class="toggle-label">Dark</span>
    </div>
  </div>

  <script>
    // DOM elements
    const themeToggle = document.getElementById("themeToggle");
    const modeText = document.getElementById("mode-text");
    
    // Check for saved preference or system preference
    const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");
    const savedTheme = localStorage.getItem("theme");
    
    // Apply initial theme
    function setTheme(theme) {
      if (theme === "dark") {
        document.documentElement.setAttribute("data-theme", "dark");
        themeToggle.setAttribute("data-active", "true");
        modeText.textContent = "Dark";
      } else {
        document.documentElement.setAttribute("data-theme", "light");
        themeToggle.setAttribute("data-active", "false");
        modeText.textContent = "Light";
      }
    }
    
    // Initialize theme
    if (savedTheme) {
      setTheme(savedTheme);
    } else if (prefersDarkScheme.matches) {
      setTheme("dark");
    } else {
      setTheme("light");
    }
    
    // Toggle theme handler
    themeToggle.addEventListener("click", function() {
      const currentTheme = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
      const newTheme = currentTheme === "dark" ? "light" : "dark";
      
      localStorage.setItem("theme", newTheme);
      setTheme(newTheme);
    });
    
    // Listen for system preference changes
    prefersDarkScheme.addEventListener("change", (e) => {
      if (!localStorage.getItem("theme")) {
        setTheme(e.matches ? "dark" : "light");
      }
    });
  </script>
</body>
</html>