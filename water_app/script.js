const authView = document.getElementById("auth-view");
const authSection = document.getElementById("auth-section");
const appSection = document.getElementById("app-section");
const messageBox = document.getElementById("message");
const adminTabButton = document.getElementById("admin-tab-button");
const loginForm = document.getElementById("login-form");
const registerForm = document.getElementById("register-form");
const goalForm = document.getElementById("goal-form");
const entryForm = document.getElementById("entry-form");
const logoutBtn = document.getElementById("logout-btn");
const entriesBody = document.getElementById("entries-body");
const adminSection = document.getElementById("admin-section");
const adminUsersBody = document.getElementById("admin-users-body");
const adminEntriesBody = document.getElementById("admin-entries-body");
const roleText = document.getElementById("role-text");
const streakValue = document.getElementById("streak-value");
const dropPercent = document.getElementById("drop-percent");

function setActiveTab(group, targetId) {
  const buttons = document.querySelectorAll(`[data-tab-group="${group}"]`);
  const panels = document.querySelectorAll(`[data-tab-panel="${group}"]`);

  buttons.forEach((button) => {
    button.classList.toggle("is-active", button.dataset.tabTarget === targetId);
  });

  panels.forEach((panel) => {
    const isActive = panel.id === targetId;
    panel.classList.toggle("hidden", !isActive);
    panel.classList.toggle("is-active", isActive);
  });
}

function bindTabs(group) {
  const buttons = document.querySelectorAll(`[data-tab-group="${group}"]`);
  buttons.forEach((button) => {
    button.addEventListener("click", () => setActiveTab(group, button.dataset.tabTarget));
  });
}

function showMessage(text, isError = false) {
  messageBox.textContent = text;
  messageBox.classList.remove("hidden");
  messageBox.style.background = isError ? "#fff1f3" : "#ffffff";
  messageBox.style.color = isError ? "#c03d57" : "#17364d";
  messageBox.style.borderColor = isError ? "rgba(208, 82, 104, 0.18)" : "rgba(94, 155, 193, 0.16)";

  clearTimeout(showMessage.timer);
  showMessage.timer = setTimeout(() => {
    messageBox.classList.add("hidden");
  }, 3000);
}

function formatDateTimeLocal(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

async function sendForm(url, formData) {
  const response = await fetch(url, {
    method: "POST",
    body: formData
  });

  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.message || "Произошла ошибка");
  }

  return data;
}

function renderEntries(entries) {
  if (!entries.length) {
    entriesBody.innerHTML = '<tr><td colspan="4" class="empty">Записей пока нет</td></tr>';
    return;
  }

  entriesBody.innerHTML = "";

  entries.forEach((entry) => {
    const row = document.createElement("tr");

    const dateCell = document.createElement("td");
    dateCell.textContent = entry.date;

    const timeCell = document.createElement("td");
    timeCell.textContent = entry.time;

    const amountCell = document.createElement("td");
    amountCell.textContent = `${entry.amount} мл`;

    const actionCell = document.createElement("td");
    const deleteButton = document.createElement("button");
    deleteButton.type = "button";
    deleteButton.className = "button-danger";
    deleteButton.textContent = "Удалить";
    deleteButton.addEventListener("click", () => deleteEntry(entry.id));
    actionCell.appendChild(deleteButton);

    row.append(dateCell, timeCell, amountCell, actionCell);
    entriesBody.appendChild(row);
  });
}

function renderAdminUsers(users) {
  if (!users.length) {
    adminUsersBody.innerHTML = '<tr><td colspan="5" class="empty">Пользователей пока нет</td></tr>';
    return;
  }

  adminUsersBody.innerHTML = "";

  users.forEach((user) => {
    const row = document.createElement("tr");

    const usernameCell = document.createElement("td");
    usernameCell.textContent = user.username;

    const roleCell = document.createElement("td");
    roleCell.textContent = user.role === "admin" ? "Админ" : "Пользователь";

    const goalCell = document.createElement("td");
    goalCell.textContent = `${user.goal} мл`;

    const countCell = document.createElement("td");
    countCell.textContent = String(user.entries_count);

    const actionCell = document.createElement("td");
    const deleteButton = document.createElement("button");
    deleteButton.type = "button";

    if (user.is_current) {
      deleteButton.className = "button-disabled";
      deleteButton.textContent = "Текущий";
      deleteButton.disabled = true;
    } else {
      deleteButton.className = "button-danger";
      deleteButton.textContent = "Удалить";
      deleteButton.addEventListener("click", () => deleteAdminUser(user.id));
    }

    actionCell.appendChild(deleteButton);
    row.append(usernameCell, roleCell, goalCell, countCell, actionCell);
    adminUsersBody.appendChild(row);
  });
}

function renderAdminEntries(entries) {
  if (!entries.length) {
    adminEntriesBody.innerHTML = '<tr><td colspan="5" class="empty">Записей пока нет</td></tr>';
    return;
  }

  adminEntriesBody.innerHTML = "";

  entries.forEach((entry) => {
    const row = document.createElement("tr");

    const usernameCell = document.createElement("td");
    usernameCell.textContent = entry.username;

    const dateCell = document.createElement("td");
    dateCell.textContent = entry.date;

    const timeCell = document.createElement("td");
    timeCell.textContent = entry.time;

    const amountCell = document.createElement("td");
    amountCell.textContent = `${entry.amount} мл`;

    const actionCell = document.createElement("td");
    const deleteButton = document.createElement("button");
    deleteButton.type = "button";
    deleteButton.className = "button-danger";
    deleteButton.textContent = "Удалить";
    deleteButton.addEventListener("click", () => deleteAdminEntry(entry.id));
    actionCell.appendChild(deleteButton);

    row.append(usernameCell, dateCell, timeCell, amountCell, actionCell);
    adminEntriesBody.appendChild(row);
  });
}

function updateDashboard(data) {
  authView.classList.add("hidden");
  appSection.classList.remove("hidden");

  document.getElementById("welcome-text").textContent = `Здравствуйте, ${data.username}`;
  roleText.classList.toggle("hidden", !data.is_admin);
  if (streakValue) {
    const days = Number(data.streak_days || 0);
    streakValue.textContent = `${days} ${days === 1 ? "день" : days >= 2 && days <= 4 ? "дня" : "дней"}`;
  }
  goalForm.goal.value = data.goal;
  document.getElementById("drunk-value").textContent = `${data.consumed} мл`;
  document.getElementById("left-value").textContent = `${data.remaining} мл`;
  document.getElementById("percent-value").textContent = `${data.percent}%`;
  document.getElementById("progress-bar").style.height = `${Math.min(data.percent, 100)}%`;
  if (dropPercent) {
    dropPercent.textContent = `${data.percent}%`;
  }

  renderEntries(data.entries);

  adminTabButton.classList.toggle("hidden", !data.is_admin);

  if (data.is_admin && data.admin) {
    adminSection.classList.remove("hidden");
    document.getElementById("admin-users-count").textContent = String(data.admin.users_count);
    document.getElementById("admin-entries-count").textContent = String(data.admin.entries_count);
    renderAdminUsers(data.admin.users);
    renderAdminEntries(data.admin.entries);
  } else {
    adminSection.classList.add("hidden");
    const activeAppTab = document.querySelector(`[data-tab-group="app"].is-active`);
    if (activeAppTab && activeAppTab.dataset.tabTarget === "admin-tab") {
      setActiveTab("app", "overview-tab");
    }
  }
}

async function loadData() {
  const response = await fetch("data.php?action=get");
  const data = await response.json();

  if (data.success) {
    updateDashboard(data);
    return;
  }

  authView.classList.remove("hidden");
  appSection.classList.add("hidden");
}

async function deleteEntry(id) {
  try {
    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);
    const data = await sendForm("data.php", formData);
    updateDashboard(data);
    showMessage("Запись удалена");
  } catch (error) {
    showMessage(error.message, true);
  }
}

async function deleteAdminUser(id) {
  try {
    const formData = new FormData();
    formData.append("action", "admin_delete_user");
    formData.append("user_id", id);
    const data = await sendForm("data.php", formData);
    updateDashboard(data);
    showMessage("Пользователь удалён");
  } catch (error) {
    showMessage(error.message, true);
  }
}

async function deleteAdminEntry(id) {
  try {
    const formData = new FormData();
    formData.append("action", "admin_delete_entry");
    formData.append("id", id);
    const data = await sendForm("data.php", formData);
    updateDashboard(data);
    showMessage("Запись удалена администратором");
  } catch (error) {
    showMessage(error.message, true);
  }
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  try {
    const data = await sendForm("auth.php", new FormData(loginForm));
    updateDashboard(data);
    showMessage("Вход выполнен");
    loginForm.reset();
  } catch (error) {
    showMessage(error.message, true);
  }
});

registerForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  try {
    const data = await sendForm("register.php", new FormData(registerForm));
    updateDashboard(data);
    showMessage("Регистрация завершена");
    registerForm.reset();
  } catch (error) {
    showMessage(error.message, true);
  }
});

goalForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  try {
    const formData = new FormData(goalForm);
    formData.append("action", "set_goal");
    const data = await sendForm("data.php", formData);
    updateDashboard(data);
    showMessage("Цель обновлена");
  } catch (error) {
    showMessage(error.message, true);
  }
});

entryForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  try {
    const formData = new FormData(entryForm);
    formData.append("action", "add");
    const data = await sendForm("data.php", formData);
    updateDashboard(data);
    showMessage("Запись добавлена");
    entryForm.amount.value = "";
    entryForm.datetime.value = formatDateTimeLocal();
  } catch (error) {
    showMessage(error.message, true);
  }
});

logoutBtn.addEventListener("click", async () => {
  try {
    const formData = new FormData();
    formData.append("action", "logout");
    await sendForm("auth.php", formData);
    appSection.classList.add("hidden");
    adminSection.classList.add("hidden");
    roleText.classList.add("hidden");
    authView.classList.remove("hidden");
    adminTabButton.classList.add("hidden");
    setActiveTab("auth", "login-panel");
    showMessage("Вы вышли из системы");
  } catch (error) {
    showMessage(error.message, true);
  }
});

bindTabs("auth");
bindTabs("app");
setActiveTab("auth", "login-panel");
setActiveTab("app", "overview-tab");
entryForm.datetime.value = formatDateTimeLocal();
loadData();
