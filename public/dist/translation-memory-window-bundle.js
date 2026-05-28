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

  // src/js/translation-memory-window.js
  var debounce = (func, delay) => {
    let timeout;
    const debounced = function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), delay);
    };
    debounced.cancel = () => {
      clearTimeout(timeout);
    };
    return debounced;
  };
  var novelId = null;
  var isGenerationRunning = false;
  var AI_SETTINGS_KEYS = {
    MODEL: "parallel-leaves-ai-model",
    TEMPERATURE: "parallel-leaves-ai-temperature"
  };
  var modelSelect = document.getElementById("js-llm-model-select");
  var tempSlider = document.getElementById("js-ai-temperature-slider");
  var tempValue = document.getElementById("js-ai-temperature-value");
  var startBtn = document.getElementById("js-start-generating-btn");
  var stopBtn = document.getElementById("js-stop-generating-btn");
  var editor = document.getElementById("js-memory-editor");
  var statusText = document.getElementById("js-status-text");
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
  function getProcessedMarkers() {
    if (!editor.value || !novelId) {
      return [];
    }
    const processedMarkers = [];
    const markerRegex = /#(\d+)-(\d+)/g;
    let match;
    while ((match = markerRegex.exec(editor.value)) !== null) {
      const foundNovelId = parseInt(match[1], 10);
      const markerNumber = parseInt(match[2], 10);
      if (foundNovelId === parseInt(novelId, 10)) {
        processedMarkers.push(markerNumber);
      }
    }
    return processedMarkers;
  }
  async function startGenerationProcess() {
    if (isGenerationRunning) {
      return;
    }
    isGenerationRunning = true;
    startBtn.disabled = true;
    startBtn.querySelector(".loading").classList.remove("hidden");
    stopBtn.classList.remove("hidden");
    statusText.textContent = t("editor.translationMemory.loading");
    await sendGenerationRequest();
  }
  function stopGenerationProcess() {
    isGenerationRunning = false;
    startBtn.disabled = false;
    startBtn.querySelector(".loading").classList.add("hidden");
    stopBtn.classList.add("hidden");
    statusText.textContent = "";
  }
  async function sendGenerationRequest() {
    if (!isGenerationRunning) {
      return;
    }
    const selectedModel = modelSelect.value;
    const temperature = parseFloat(tempSlider.value);
    const pairCountInput = document.getElementById("js-analysis-pairs-count");
    const pairCount = parseInt(pairCountInput.value, 10) || 2;
    const processedMarkerNumbers = getProcessedMarkers();
    try {
      await window.api.translationMemoryStart({
        novelId,
        model: selectedModel,
        temperature,
        processedMarkerNumbers,
        pairCount,
        lang: localStorage.getItem("app_lang") || "en"
      });
    } catch (error) {
      statusText.textContent = t("editor.translationMemory.error", { message: error.message });
      stopGenerationProcess();
    }
  }
  async function saveMemory() {
    try {
      await window.api.translationMemorySave({ novelId, content: editor.value });
      console.log("Translation memory auto-saved.");
    } catch (error) {
      console.error("Failed to auto-save translation memory:", error);
      statusText.textContent = t("editor.translationMemory.error", { message: error.message });
    }
  }
  var debouncedSave = debounce(saveMemory, 5e3);
  document.addEventListener("DOMContentLoaded", async () => {
    await initI18n();
    applyTranslationsTo(document.body);
    document.title = t("editor.translationMemory.windowTitle");
    const params = new URLSearchParams(window.location.search);
    novelId = params.get("novelId");
    if (!novelId) {
      editor.value = t("editor.translationMemory.error", { message: "Novel ID is missing." });
      startBtn.disabled = true;
      return;
    }
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
    const pairsCountInput = document.getElementById("js-analysis-pairs-count");
    const PAIRS_COUNT_KEY = `translation-memory-pairs-count-${novelId}`;
    const savedPairsCount = localStorage.getItem(PAIRS_COUNT_KEY) || "2";
    pairsCountInput.value = savedPairsCount;
    pairsCountInput.addEventListener("change", () => {
      let value = parseInt(pairsCountInput.value, 10);
      if (isNaN(value) || value < 1) value = 1;
      if (value > 10) value = 10;
      pairsCountInput.value = value;
      localStorage.setItem(PAIRS_COUNT_KEY, value);
    });
    await populateModels();
    try {
      const result = await window.api.translationMemoryLoad(novelId);
      if (result.success) {
        editor.value = result.content || "";
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      editor.value = t("editor.translationMemory.error", { message: `Failed to load memory: ${error.message}` });
    }
    editor.addEventListener("input", debouncedSave);
    editor.addEventListener("blur", () => {
      debouncedSave.cancel();
      saveMemory();
    });
    window.addEventListener("beforeunload", () => {
      debouncedSave.cancel();
      saveMemory();
    });
    startBtn.addEventListener("click", startGenerationProcess);
    stopBtn.addEventListener("click", stopGenerationProcess);
    window.api.onTranslationMemoryUpdate((update) => {
      if (!update || typeof update.type === "undefined") {
        console.error("Received invalid update from main process:", update);
        statusText.textContent = t("editor.translationMemory.error", { message: "Received an invalid update." });
        stopGenerationProcess();
        return;
      }
      let messageText = "";
      if (update.message) {
        messageText = t(update.message, update.params || {});
      }
      switch (update.type) {
        case "new_instructions":
          editor.value += update.data.formattedBlock;
          editor.scrollTop = editor.scrollHeight;
          saveMemory();
          if (isGenerationRunning) {
            statusText.textContent = t("editor.translationMemory.loading");
            sendGenerationRequest();
          }
          break;
        case "finished":
          statusText.textContent = messageText;
          stopGenerationProcess();
          break;
        case "error":
          statusText.textContent = messageText;
          stopGenerationProcess();
          break;
      }
    });
  });
})();
