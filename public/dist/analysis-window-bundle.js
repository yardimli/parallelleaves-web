(() => {
  // src/js/i18n.js
  var LANG_KEY = "app_lang";
  var translations = {};
  var enTranslations = {};
  var appLanguages = {
    en: "English",
    tr: "T\xFCrk\xE7e",
    no: "Norsk"
    // 'zh-TW': '繁體中文'
  };
  async function loadLanguageFile(lang) {
    try {
      const langData = await window.api.getLangFile(lang);
      return JSON.parse(langData);
    } catch (error) {
      console.error(`Could not load language file for: ${lang}`, error);
      return null;
    }
  }
  function getNested(obj, path) {
    return path.split(".").reduce((acc, part) => acc && acc[part], obj);
  }
  function t(key, substitutions = {}) {
    let result = getNested(translations, key);
    let isFallback = false;
    if (result === void 0) {
      result = getNested(enTranslations, key);
      isFallback = true;
    }
    if (result === void 0) {
      return key;
    }
    if (typeof result === "string") {
      for (const [subKey, subValue] of Object.entries(substitutions)) {
        result = result.replace(`{${subKey}}`, subValue);
      }
      if (isFallback && (localStorage.getItem(LANG_KEY) || "en") !== "en") {
        result += "*";
      }
    }
    return result;
  }
  function translateElement(element) {
    const key = element.dataset.i18n;
    if (key) {
      if (element.children.length === 0 || element.tagName.toLowerCase() === "title") {
        element.textContent = t(key);
      } else {
        for (const node of element.childNodes) {
          if (node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0) {
            node.textContent = ` ${t(key)} `;
            break;
          }
        }
      }
    }
    if (element.dataset.i18nTitle) {
      element.title = t(element.dataset.i18nTitle);
    }
    if (element.dataset.i18nPlaceholder) {
      element.placeholder = t(element.dataset.i18nPlaceholder);
    }
  }
  function applyTranslationsTo(rootElement) {
    if (!rootElement) return;
    if (rootElement.matches("[data-i18n], [data-i18n-title], [data-i18n-placeholder]")) {
      translateElement(rootElement);
    }
    rootElement.querySelectorAll("[data-i18n], [data-i18n-title], [data-i18n-placeholder]").forEach(translateElement);
  }
  function applyTranslations() {
    applyTranslationsTo(document.body);
    document.documentElement.lang = localStorage.getItem(LANG_KEY) || "en";
  }
  function populateLanguageSwitcher() {
    const menus = document.querySelectorAll("#js-lang-switcher-menu");
    if (menus.length === 0) return;
    const currentLang = localStorage.getItem(LANG_KEY) || "en";
    menus.forEach((menu) => {
      menu.innerHTML = "";
      for (const [code, name] of Object.entries(appLanguages)) {
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = "#";
        a.dataset.lang = code;
        a.textContent = name;
        if (code === currentLang) {
          a.classList.add("active");
        }
        a.addEventListener("click", (e) => {
          e.preventDefault();
          if (code !== currentLang) {
            setLanguage(code);
          }
        });
        li.appendChild(a);
        menu.appendChild(li);
      }
    });
  }
  function setLanguage(lang) {
    localStorage.setItem(LANG_KEY, lang);
    window.location.reload();
  }
  async function initI18n(isDashboard = false) {
    const lang = localStorage.getItem(LANG_KEY) || "en";
    enTranslations = await loadLanguageFile("en") || {};
    if (lang !== "en") {
      translations = await loadLanguageFile(lang) || {};
    } else {
      translations = enTranslations;
    }
    localStorage.setItem(LANG_KEY, lang);
    applyTranslations();
    populateLanguageSwitcher();
  }

  // src/js/analysis-window.js
  var novelId = null;
  var localStorageKey = null;
  var AI_SETTINGS_KEYS = {
    MODEL: "parallel-leaves-ai-model",
    TEMPERATURE: "parallel-leaves-ai-temperature"
  };
  var modelSelect = document.getElementById("js-llm-model-select");
  var tempSlider = document.getElementById("js-ai-temperature-slider");
  var tempValue = document.getElementById("js-ai-temperature-value");
  var startBtn = document.getElementById("js-start-analysis-btn");
  var applyBtn = document.getElementById("js-apply-results-btn");
  var resultsContainer = document.getElementById("js-analysis-results");
  var statusText = document.getElementById("js-status-text");
  function saveResultsToLocalStorage() {
    if (!localStorageKey) return;
    const resultCards = Array.from(resultsContainer.querySelectorAll(".card"));
    const dataToSave = resultCards.map((card) => {
      const marker = card.dataset.marker;
      const changes = {};
      card.querySelectorAll("tbody tr").forEach((row) => {
        const original = row.querySelector("td:nth-child(1) textarea").value.trim();
        const edited = row.querySelector("td:nth-child(2) textarea").value.trim();
        if (original && edited) {
          changes[original] = edited;
        }
      });
      return { marker, changes };
    }).filter((item) => Object.keys(item.changes).length > 0);
    try {
      const jsonString = JSON.stringify(dataToSave);
      if (jsonString.startsWith("[") && jsonString.endsWith("]")) {
        localStorage.setItem(localStorageKey, jsonString);
      } else {
        throw new Error("Attempted to save invalid JSON structure.");
      }
    } catch (error) {
      console.error("Failed to serialize and save analysis results to localStorage:", error);
    }
  }
  function loadResultsFromLocalStorage() {
    if (!localStorageKey) return;
    const savedData = localStorage.getItem(localStorageKey);
    if (savedData) {
      try {
        const results = JSON.parse(savedData);
        if (Array.isArray(results) && results.length > 0) {
          resultsContainer.innerHTML = "";
          const title = document.createElement("h2");
          title.className = "text-xl font-bold mb-2";
          title.setAttribute("data-i18n", "editor.analysis.resultsTitle");
          resultsContainer.appendChild(title);
          results.forEach((result) => renderResult(result));
          applyTranslationsTo(resultsContainer);
        }
      } catch (e) {
        console.error("Failed to parse analysis results from localStorage, clearing corrupted data:", e);
        localStorage.removeItem(localStorageKey);
      }
    }
    updateApplyButtonState();
  }
  async function populateModels() {
    try {
      const result = await window.api.getModels();
      if (result.success) {
        modelSelect.innerHTML = "";
        result.models.forEach((group) => {
          const optgroup = document.createElement("optgroup");
          optgroup.label = group.group;
          group.models.forEach((model) => {
            const option = new Option(`${model.name}`, model.id);
            optgroup.appendChild(option);
          });
          modelSelect.appendChild(optgroup);
        });
        const lastModel = localStorage.getItem(AI_SETTINGS_KEYS.MODEL);
        if (lastModel && modelSelect.querySelector(`option[value="${lastModel}"]`)) {
          modelSelect.value = lastModel;
        } else if (modelSelect.options.length > 0) {
          modelSelect.selectedIndex = 0;
          localStorage.setItem(AI_SETTINGS_KEYS.MODEL, modelSelect.value);
        }
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      console.error("Failed to load models:", error);
      modelSelect.innerHTML = `<option>${t("editor.chat.errorLoadModels")}</option>`;
      modelSelect.disabled = true;
    }
  }
  function updateApplyButtonState() {
    const remainingPairs = resultsContainer.querySelectorAll("tbody tr").length;
    applyBtn.disabled = remainingPairs === 0;
  }
  function renderResult(result) {
    if (!result || !result.changes || Object.keys(result.changes).length === 0) {
      return;
    }
    let card = resultsContainer.querySelector(`.card[data-marker="${result.marker}"]`);
    let tbody;
    if (!card) {
      card = document.createElement("div");
      card.className = "card bg-base-200 shadow-xl";
      card.dataset.marker = result.marker;
      const cardBody = document.createElement("div");
      cardBody.className = "card-body";
      const cardHeader = document.createElement("div");
      cardHeader.className = "card-title";
      const title = document.createElement("h3");
      title.className = "text-sm";
      title.textContent = t("editor.analysis.changesInMarker", { marker: result.marker });
      cardHeader.appendChild(title);
      cardBody.appendChild(cardHeader);
      const table = document.createElement("table");
      table.className = "table table-sm";
      const thead = document.createElement("thead");
      thead.innerHTML = `
	        <tr>
	            <th class="w-[calc(50%-1.5rem)]" data-i18n="editor.analysis.original">Original</th>
	            <th class="w-[calc(50%-1.5rem)]" data-i18n="editor.analysis.edited">Edited</th>
	            <th class="w-12"></th>
	        </tr>
	    `;
      table.appendChild(thead);
      tbody = document.createElement("tbody");
      table.appendChild(tbody);
      cardBody.appendChild(table);
      card.appendChild(cardBody);
      resultsContainer.appendChild(card);
    } else {
      tbody = card.querySelector("tbody");
    }
    for (const [original, edited] of Object.entries(result.changes)) {
      const row = document.createElement("tr");
      const escapeHtml = (text) => {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
      };
      row.innerHTML = `
            <td>
                <textarea class="textarea textarea-bordered textarea-sm w-full" rows="1">${escapeHtml(original)}</textarea>
            </td>
            <td>
                <textarea class="textarea textarea-bordered textarea-sm w-full" rows="1">${escapeHtml(edited)}</textarea>
            </td>
            <td>
                <button class="btn btn-ghost btn-xs btn-square js-delete-pair-btn" data-i18n-title="editor.analysis.deleteRow">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        `;
      tbody.appendChild(row);
    }
    card.querySelectorAll("textarea").forEach((textarea) => {
      const resize = () => {
        textarea.style.height = "auto";
        textarea.style.height = `${textarea.scrollHeight}px`;
      };
      textarea.addEventListener("input", () => {
        resize();
        saveResultsToLocalStorage();
      });
      setTimeout(resize, 0);
    });
    applyTranslationsTo(card);
  }
  async function handleStartAnalysis() {
    startBtn.disabled = true;
    startBtn.querySelector(".loading").classList.remove("hidden");
    applyBtn.disabled = true;
    statusText.textContent = t("editor.analysis.loading");
    const selectedModel = modelSelect.value;
    const temperature = parseFloat(tempSlider.value);
    try {
      await window.api.startAnalysis({ novelId, model: selectedModel, temperature });
    } catch (error) {
      statusText.textContent = t("editor.analysis.error", { message: error.message });
      startBtn.disabled = false;
      startBtn.querySelector(".loading").classList.add("hidden");
    }
  }
  async function handleApplyResults() {
    const resultCards = Array.from(resultsContainer.querySelectorAll(".card"));
    if (resultCards.length === 0) return;
    const newPairs = [];
    resultCards.forEach((card) => {
      const tableRows = card.querySelectorAll("tbody tr");
      tableRows.forEach((row) => {
        const originalTextarea = row.querySelector("td:nth-child(1) textarea");
        const editedTextarea = row.querySelector("td:nth-child(2) textarea");
        if (originalTextarea && editedTextarea && originalTextarea.value && editedTextarea.value) {
          newPairs.push({
            source: originalTextarea.value.trim(),
            target: editedTextarea.value.trim(),
            type: "rephrasing"
            // All analyzed pairs are for rephrasing context
          });
        }
      });
    });
    applyBtn.disabled = true;
    const applyBtnSpan = applyBtn.querySelector("span");
    if (applyBtnSpan) applyBtnSpan.classList.add("loading");
    try {
      if (newPairs.length > 0) {
        const existingDictionary = await window.api.getNovelDictionary(novelId) || [];
        const updatedDictionary = [...existingDictionary];
        newPairs.forEach((newPair) => {
          const isDuplicate = existingDictionary.some(
            (existingPair) => existingPair.source === newPair.source && existingPair.target === newPair.target
          );
          if (!isDuplicate) {
            updatedDictionary.push(newPair);
          }
        });
        await window.api.saveNovelDictionary(novelId, updatedDictionary);
      }
      if (localStorageKey) {
        localStorage.removeItem(localStorageKey);
      }
      window.close();
    } catch (error) {
      console.error("Failed to apply analysis results:", error);
      window.alert(`Error: ${error.message}`);
    } finally {
      applyBtn.disabled = false;
      if (applyBtnSpan) applyBtnSpan.classList.remove("loading");
    }
  }
  document.addEventListener("DOMContentLoaded", async () => {
    await initI18n();
    applyTranslationsTo(document.body);
    document.title = t("editor.analysis.title");
    const params = new URLSearchParams(window.location.search);
    novelId = params.get("novelId");
    const autoStart = params.get("autoStart") === "true";
    if (!novelId) {
      resultsContainer.innerHTML = `<p class="text-error p-4">${t("editor.analysis.error", { message: "Novel ID is missing." })}</p>`;
      startBtn.disabled = true;
      return;
    }
    localStorageKey = `analysis-results-${novelId}`;
    loadResultsFromLocalStorage();
    const lastTemp = localStorage.getItem(AI_SETTINGS_KEYS.TEMPERATURE) || "0.7";
    tempSlider.value = lastTemp;
    tempValue.textContent = parseFloat(lastTemp).toFixed(1);
    tempSlider.addEventListener("input", () => {
      tempValue.textContent = parseFloat(tempSlider.value).toFixed(1);
    });
    tempSlider.addEventListener("change", () => {
      localStorage.setItem(AI_SETTINGS_KEYS.TEMPERATURE, tempSlider.value);
    });
    modelSelect.addEventListener("change", () => {
      localStorage.setItem(AI_SETTINGS_KEYS.MODEL, modelSelect.value);
    });
    await populateModels();
    startBtn.addEventListener("click", handleStartAnalysis);
    applyBtn.addEventListener("click", handleApplyResults);
    resultsContainer.addEventListener("click", (event) => {
      const deleteBtn = event.target.closest(".js-delete-pair-btn");
      if (deleteBtn) {
        const row = deleteBtn.closest("tr");
        const tbody = row.parentElement;
        const card = tbody.closest(".card");
        row.remove();
        if (tbody.children.length === 0) {
          card.remove();
        }
        updateApplyButtonState();
        saveResultsToLocalStorage();
      }
    });
    if (autoStart) {
      handleStartAnalysis();
    }
    let hasResultsHeader = resultsContainer.querySelector("h2") !== null;
    window.api.onAnalysisUpdate((update) => {
      if (!update || typeof update.type === "undefined") {
        console.error("Received invalid update from main process:", update);
        statusText.textContent = t("editor.analysis.error", { message: "Received an invalid analysis update." });
        startBtn.disabled = false;
        startBtn.querySelector(".loading").classList.add("hidden");
        return;
      }
      let messageText = "";
      if (update.message) {
        messageText = t(update.message, update.params || {});
      }
      switch (update.type) {
        case "progress":
          statusText.textContent = messageText;
          break;
        case "results":
          if (update.data && update.data.length > 0) {
            if (!hasResultsHeader) {
              const title = document.createElement("h2");
              title.className = "text-xl font-bold mb-2";
              title.setAttribute("data-i18n", "editor.analysis.resultsTitle");
              resultsContainer.insertBefore(title, resultsContainer.firstChild);
              applyTranslationsTo(title.parentElement);
              hasResultsHeader = true;
            }
            update.data.forEach(renderResult);
            saveResultsToLocalStorage();
          }
          break;
        case "finished":
          statusText.textContent = messageText;
          startBtn.disabled = false;
          startBtn.querySelector(".loading").classList.add("hidden");
          updateApplyButtonState();
          if (update.message === "editor.analysis.complete" && resultsContainer.querySelectorAll("tbody tr").length === 0) {
            statusText.textContent = t("editor.analysis.noResults");
          }
          window.api.markEditsAsAnalyzed(novelId).catch((err) => {
            console.error("Failed to automatically mark edits as analyzed:", err);
          });
          break;
        case "error":
          statusText.textContent = messageText;
          startBtn.disabled = false;
          startBtn.querySelector(".loading").classList.add("hidden");
          break;
      }
    });
  });
})();
